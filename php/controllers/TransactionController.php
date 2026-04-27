<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../singleton/DbSingleton.php';
require_once __DIR__ . '/../utils/ResponseUtils.php';
require_once __DIR__ . '/../utils/BalanceUtils.php';
require_once __DIR__ . '/../utils/RequestUtils.php';

class TransactionController
{
  public function index(Request $request, Response $response, array $args){
    $mysqli_connection = DbSingleton::getInstance();

    try {
        $accountId = RequestUtils::getIntArg($args, 'account_id');
    } catch (InvalidArgumentException $e) {
        return ResponseUtils::error($response, $e->getMessage(), 400);
    }

    $stmt = $mysqli_connection->prepare("SELECT * FROM transactions WHERE account_id = ?");
    if (!$stmt) {
        return ResponseUtils::error($response, 'Database prepare failed', 502);
    }
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        return ResponseUtils::error($response, 'Database query failed', 502);
    }

    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    return ResponseUtils::json($response, $transactions);
  }

  public function show(Request $request, Response $response, array $args){
    $mysqli_connection = DbSingleton::getInstance();

    try {
        $accountId = RequestUtils::getIntArg($args, 'account_id');
        $transactionId = RequestUtils::getIntArg($args, 'id');
    } catch (InvalidArgumentException $e) {
        return ResponseUtils::error($response, $e->getMessage(), 400);
    }

    if ($accountId <= 0 || $transactionId <= 0) {
        return ResponseUtils::error($response, 'Invalid identifiers', 400);
    }

    $stmt = $mysqli_connection->prepare("SELECT * FROM transactions WHERE account_id = ? AND id = ?");
    if (!$stmt) {
        return ResponseUtils::error($response, 'Database prepare failed', 502);
    }
    $stmt->bind_param('ii', $accountId, $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        return ResponseUtils::error($response, 'Database query failed', 502);
    }

    $transaction = $result->fetch_assoc();
    if (!$transaction) {
        return ResponseUtils::error($response, 'Transaction not found', 404);
    }

    return ResponseUtils::json($response, $transaction);
  }

  public function createDeposit(Request $request, Response $response, array $args){
    return $this->createTransaction($request, $response, $args, 'deposit');
  }

  public function createWithdrawal(Request $request, Response $response, array $args){
    return $this->createTransaction($request, $response, $args, 'withdrawal');
  }

  private function createTransaction(Request $request, Response $response, array $args, string $type){
    $mysqli_connection = DbSingleton::getInstance();

    try {
        $accountId = RequestUtils::getIntArg($args, 'account_id');
    } catch (InvalidArgumentException $e) {
        return ResponseUtils::error($response, $e->getMessage(), 400);
    }

    $body = (string)$request->getBody();
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return ResponseUtils::error($response, 'Invalid JSON body', 400);
    }

    $amount = $data['amount'] ?? null;
    $description = $data['description'] ?? null;

    if (!is_numeric($amount) || $amount <= 0) {
      return ResponseUtils::error($response, 'Amount must be a positive number', 400);
    }
    $amount = (float)$amount;

    if ($description === null || (is_string($description) && trim($description) === '')) {
      return ResponseUtils::error($response, 'Description cannot be null or empty', 400);
    }

    if (!is_string($description)) {
      return ResponseUtils::error($response, 'Description must be a string', 400);
    }

    // Calculate current balance using shared utility
    $currentBalance = BalanceUtils::calculateBalance($accountId);

    // Calculate balance_after
    $balanceAfter = $type === 'deposit' ? $currentBalance + $amount : $currentBalance - $amount;

    // Validate withdrawal won't result in negative balance
    if ($type === 'withdrawal' && $balanceAfter < 0) {
      return ResponseUtils::error($response, 'Insufficient funds', 400);
    }

    $stmt = $mysqli_connection->prepare(
      "INSERT INTO transactions (account_id, type, amount, description, balance_after, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
      return ResponseUtils::error($response, 'Database prepare failed', 502);
    }
    $stmt->bind_param('isdsd', $accountId, $type, $amount, $description, $balanceAfter);

    if (!$stmt->execute()) {
      return ResponseUtils::error($response, 'Failed to create transaction', 500);
    }

    $insertId = $mysqli_connection->insert_id;
    $fetch = $mysqli_connection->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    if (!$fetch) {
      return ResponseUtils::error($response, 'Database prepare failed', 502);
    }
    $fetch->bind_param('ii', $insertId, $accountId);
    $fetch->execute();
    $res = $fetch->get_result();
    $created = $res ? $res->fetch_assoc() : null;

    return ResponseUtils::json($response, $created ?: ['id' => $insertId], 201);
  }

  public function update(Request $request, Response $response, array $args){
    $mysqli_connection = DbSingleton::getInstance();
  
    try {
        $accountId = RequestUtils::getIntArg($args, 'account_id');
        $transactionId = RequestUtils::getIntArg($args, 'id');
    } catch (InvalidArgumentException $e) {
        return ResponseUtils::error($response, $e->getMessage(), 400);
    }

    $body = (string)$request->getBody();
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ResponseUtils::error($response, 'Invalid JSON body', 400);
    }

    $description = $data['description'] ?? null;
    if ($description === null) {
        return ResponseUtils::error($response, 'Description is required', 400);
    }

    $stmt = $mysqli_connection->prepare("UPDATE transactions SET description = ? WHERE account_id = ? AND id = ?");
    if (!$stmt) {
        return ResponseUtils::error($response, 'Database prepare failed', 502);
    }
    $stmt->bind_param('sii', $description, $accountId, $transactionId);
    if (!$stmt->execute()) {
        return ResponseUtils::error($response, 'Failed to update transaction', 500);
    }

    // fetch updated
    $fetch = $mysqli_connection->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    if (!$fetch) {
        return ResponseUtils::error($response, 'Database prepare failed', 502);
    }
    $fetch->bind_param('ii', $transactionId, $accountId);
    $fetch->execute();
    $res = $fetch->get_result();
    $updated = $res ? $res->fetch_assoc() : null;

    return ResponseUtils::json($response, $updated ?: ['success' => true]);
  }

  public function delete(Request $request, Response $response, array $args){
    $mysqli_connection = DbSingleton::getInstance();
    
    try {
        $accountId = RequestUtils::getIntArg($args, 'account_id');
        $transactionId = RequestUtils::getIntArg($args, 'id');
    } catch (InvalidArgumentException $e) {
        return ResponseUtils::error($response, $e->getMessage(), 400);
    }

    if ($accountId <= 0 || $transactionId <= 0) {
        return ResponseUtils::error($response, 'Invalid identifiers', 400);
    }

    $stmt = $mysqli_connection->prepare("DELETE FROM transactions WHERE account_id = ? AND id = ?");
    if (!$stmt) {
        return ResponseUtils::error($response, 'Database prepare failed', 502);
    }
    $stmt->bind_param('ii', $accountId, $transactionId);
    if (!$stmt->execute()) {
        return ResponseUtils::error($response, 'Failed to delete transaction', 500);
    }

    // Return 204 No Content to conform with typical REST delete semantics
    return $response->withStatus(204);
  }
}
