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
        return ResponseUtils::badRequest($response, $e->getMessage());
    }

    $stmt = $mysqli_connection->prepare("SELECT * FROM transactions WHERE account_id = ?");
    if (!$stmt) {
        return ResponseUtils::internalServerError($response, 'Database prepare failed');
    }
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        return ResponseUtils::internalServerError($response, 'Database query failed');
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
        return ResponseUtils::badRequest($response, $e->getMessage());
    }

    if ($accountId <= 0 || $transactionId <= 0) {
        return ResponseUtils::badRequest($response, 'Invalid identifiers');
    }

    $stmt = $mysqli_connection->prepare("SELECT * FROM transactions WHERE account_id = ? AND id = ?");
    if (!$stmt) {
        return ResponseUtils::internalServerError($response, 'Database prepare failed');
    }
    $stmt->bind_param('ii', $accountId, $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        return ResponseUtils::internalServerError($response, 'Database query failed');
    }

    $transaction = $result->fetch_assoc();
    if (!$transaction) {
        return ResponseUtils::notFound($response, 'Transaction not found');
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
        return ResponseUtils::badRequest($response, $e->getMessage());
    }

    $body = (string)$request->getBody();
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return ResponseUtils::badRequest($response, 'Invalid JSON body');
    }

    $amount = $data['amount'] ?? null;
    $description = $data['description'] ?? null;

    if (!is_numeric($amount) || $amount <= 0) {
      return ResponseUtils::badRequest($response, 'Amount must be a positive number');
    }
    $amount = (float)$amount;

    if ($description === null || (is_string($description) && trim($description) === '')) {
      return ResponseUtils::badRequest($response, 'Description cannot be null or empty');
    }

    if (!is_string($description)) {
      return ResponseUtils::badRequest($response, 'Description must be a string');
    }

    // Calculate current balance using shared utility
    $currentBalance = BalanceUtils::calculateBalance($accountId);

    // Calculate balance_after
    $balanceAfter = $type === 'deposit' ? $currentBalance + $amount : $currentBalance - $amount;

    // Validate withdrawal won't result in negative balance
    if ($type === 'withdrawal' && $balanceAfter < 0) {
      return ResponseUtils::badRequest($response, 'Insufficient funds');
    }

    $stmt = $mysqli_connection->prepare(
      "INSERT INTO transactions (account_id, type, amount, description, balance_after, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
      return ResponseUtils::internalServerError($response, 'Database prepare failed');
    }
    $stmt->bind_param('isdsd', $accountId, $type, $amount, $description, $balanceAfter);

    if (!$stmt->execute()) {
      return ResponseUtils::internalServerError($response, 'Failed to create transaction');
    }

    $insertId = $mysqli_connection->insert_id;
    $fetch = $mysqli_connection->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    if (!$fetch) {
      return ResponseUtils::internalServerError($response, 'Database prepare failed');
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
        return ResponseUtils::badRequest($response, $e->getMessage());
    }

    $body = (string)$request->getBody();
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ResponseUtils::badRequest($response, 'Invalid JSON body');
    }

    $description = $data['description'] ?? null;
    if ($description === null) {
        return ResponseUtils::badRequest($response, 'Description is required');
    }

    $stmt = $mysqli_connection->prepare("UPDATE transactions SET description = ? WHERE account_id = ? AND id = ?");
    if (!$stmt) {
        return ResponseUtils::internalServerError($response, 'Database prepare failed');
    }
    $stmt->bind_param('sii', $description, $accountId, $transactionId);
    if (!$stmt->execute()) {
        return ResponseUtils::internalServerError($response, 'Failed to update transaction');
    }

    // fetch updated
    $fetch = $mysqli_connection->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    if (!$fetch) {
        return ResponseUtils::internalServerError($response, 'Database prepare failed');
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
        return ResponseUtils::badRequest($response, $e->getMessage());
    }

    if ($accountId <= 0 || $transactionId <= 0) {
        return ResponseUtils::badRequest($response, 'Invalid identifiers');
    }

    $stmt = $mysqli_connection->prepare("DELETE FROM transactions WHERE account_id = ? AND id = ?");
    if (!$stmt) {
        return ResponseUtils::internalServerError($response, 'Database prepare failed');
    }
    $stmt->bind_param('ii', $accountId, $transactionId);
    if (!$stmt->execute()) {
        return ResponseUtils::internalServerError($response, 'Failed to delete transaction');
    }

    // Return 204 No Content to conform with typical REST delete semantics
    return $response->withStatus(204);
  }
}
