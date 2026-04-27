<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controllers/TransactionController.php';
require_once __DIR__ . '/controllers/BalanceController.php';
require_once __DIR__ . '/controllers/BalanceConversionController.php';

$app = AppFactory::create();


// Endpoint richiesti
// Movimenti
// GET /accounts/1/transactions per ottenere l'elenco dei movimenti
// GET /accounts/1/transactions/5 per ottenere il dettaglio di un movimento
// POST /accounts/1/deposits per registrare un deposito
// POST /accounts/1/withdrawals per registrare un prelievo
// PUT /accounts/1/transactions/5 per modificare la descrizione di un movimento
// DELETE /accounts/1/transactions/5 per eliminare un movimento secondo la regola scelta
// Saldo
// GET /accounts/1/balance per ottenere il saldo attuale
// Conversione del saldo
// GET /accounts/1/balance/convert/fiat?to=USD per convertire il saldo in una valuta fiat
// GET /accounts/1/balance/convert/crypto?to=BTC per convertire il saldo in una criptovaluta
// Potete scegliere nomi leggermente diversi per gli endpoint, ma la struttura deve rimanere chiara e coerente.

// Transactions
$app->get('/accounts/{account_id:[0-9]+}/transactions', [TransactionController::class, 'index']);
$app->get('/accounts/{account_id:[0-9]+}/transactions/{id:[0-9]+}', [TransactionController::class, 'show']);
$app->post('/accounts/{account_id:[0-9]+}/deposits', [TransactionController::class, 'createDeposit']);
$app->post('/accounts/{account_id:[0-9]+}/withdrawals', [TransactionController::class, 'createWithdrawal']);
$app->put('/accounts/{account_id:[0-9]+}/transactions/{id:[0-9]+}', [TransactionController::class, 'update']);
$app->delete('/accounts/{account_id:[0-9]+}/transactions/{id:[0-9]+}', [TransactionController::class, 'delete']);

// Balance
$app->get('/accounts/{account_id:[0-9]+}/balance', [BalanceController::class, 'index']);

// Balance conversion
$app->get('/accounts/{account_id:[0-9]+}/balance/convert/fiat', [BalanceConversionController::class, 'convertToFiat']);
$app->get('/accounts/{account_id:[0-9]+}/balance/convert/crypto', [BalanceConversionController::class, 'convertToCrypto']);

$notFoundHandler = function (Request $request, Response $response, array $args) {
    $response->getBody()->write('404 - Not Found');
    return $response->withStatus(404);
};

$app->any('/', $notFoundHandler);
$app->any('/{routes:.+}', $notFoundHandler);

$app->run();
