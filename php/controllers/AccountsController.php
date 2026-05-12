<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../singleton/DbSingleton.php';
require_once __DIR__ . '/../utils/ResponseUtils.php';
require_once __DIR__ . '/../utils/RequestUtils.php';

class AccountsController
{
    public function index(Request $request, Response $response, array $args)
    {
        try {
            $db = DbSingleton::getInstance();
            $result = $db->query('SELECT * FROM accounts');
            
            if (!$result) {
                return ResponseUtils::internalServerError($response, $db->error);
            }
            
            $accounts = [];
            while ($row = $result->fetch_assoc()) {
                $accounts[] = $row;
            }
            
            return ResponseUtils::json($response, [
                'accounts' => $accounts,
                'count' => count($accounts)
            ]);
        } catch (Exception $e) {
            return ResponseUtils::internalServerError($response, $e->getMessage());
        }
    }

    public function show(Request $request, Response $response, array $args)
    {
        try {
            $account_id = RequestUtils::getIntArg($args, 'account_id');
        } catch (InvalidArgumentException $e) {
            return ResponseUtils::badRequest($response, $e->getMessage());
        }

        try {
            $db = DbSingleton::getInstance();
            $query = $db->prepare('SELECT * FROM accounts WHERE id = ?');
            $query->bind_param('i', $account_id);
            $query->execute();
            
            $result = $query->get_result();
            $account = $result->fetch_assoc();
            
            if (!$account) {
                return ResponseUtils::notFound($response, 'Account not found');
            }
            
            return ResponseUtils::json($response, $account);
        } catch (Exception $e) {
            return ResponseUtils::internalServerError($response, $e->getMessage());
        }
    }
}
