<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../singleton/DbSingleton.php';
require_once __DIR__ . '/../utils/ResponseUtils.php';
require_once __DIR__ . '/../utils/BalanceUtils.php';

class BalanceController
{
    public function index(Request $request, Response $response, array $args){
        $account_id = (int)$args['account_id'];

        $balance = BalanceUtils::calculateBalance($account_id);

        return ResponseUtils::json($response, [
            'account_id' => $account_id,
            'balance' => $balance
        ]);
    }
}
