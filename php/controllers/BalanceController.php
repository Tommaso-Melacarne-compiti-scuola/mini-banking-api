<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../singleton/DbSingleton.php';
require_once __DIR__ . '/../utils/ResponseUtils.php';

class BalanceController
{
    private static function calculateBalance($account_id) {
        $mysqli_connection = DbSingleton::getInstance();

        $stmt = $mysqli_connection->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
            FROM transactions
            WHERE account_id = ?
        ");
        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return (float)($result['balance'] ?? 0);
    }

    public function index(Request $request, Response $response, array $args){
        $account_id = (int)$args['account_id'];

        $balance = $this->calculateBalance($account_id);

        return ResponseUtils::json($response, [
            'account_id' => $account_id,
            'balance' => $balance
        ]);
    }
}
