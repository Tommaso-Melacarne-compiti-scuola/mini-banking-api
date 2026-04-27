<?php

require_once __DIR__ . '/../singleton/DbSingleton.php';

class BalanceUtils
{
    public static function calculateBalance($account_id)
    {
        $mysqli_connection = DbSingleton::getInstance();

        $stmt = $mysqli_connection->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as balance FROM transactions WHERE account_id = ?"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return (float)($row['balance'] ?? 0);
    }
}
