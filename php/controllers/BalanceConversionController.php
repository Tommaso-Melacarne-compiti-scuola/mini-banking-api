<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/BalanceController.php';
require_once __DIR__ . '/../utils/ExchangeRateUtils.php';
require_once __DIR__ . '/../utils/ResponseUtils.php';

class BalanceConversionController
{
    // Conversione del saldo
    // GET /accounts/1/balance/convert/fiat?to=USD per convertire il saldo in una valuta fiat
    // GET /accounts/1/balance/convert/crypto?to=BTC per convertire il saldo in una criptovaluta
    public function convertToFiat(Request $request, Response $response, array $args)
    {
        $account_id = (int)$args['account_id'];
    
        $balance = BalanceController::calculateBalance($account_id);

        $queryParams = $request->getQueryParams();
        $toCurrency = $queryParams['to'] ?? null;

        if (!$toCurrency) {
            return ResponseUtils::json($response, ['error' => 'Missing "to" query parameter'], 400);
        }

        try {
            $exchangeRate = ExchangeRateUtils::getFiatExchangeRate('EUR', $toCurrency);
            $convertedBalance = $balance * $exchangeRate;

            return ResponseUtils::json($response, [
                'account_id' => $account_id,
                'original_balance' => $balance,
                'converted_balance' => $convertedBalance,
                'currency' => $toCurrency
            ]);
        } catch (Exception $e) {
            return ResponseUtils::json($response, ['error' => $e->getMessage()], 502);
        }
    }

    public function convertToCrypto(Request $request, Response $response, array $args)
    {
        $account_id = (int)$args['account_id'];
    
        $balance = BalanceController::calculateBalance($account_id);

        $queryParams = $request->getQueryParams();
        $toCurrency = $queryParams['to'] ?? null;

        if (!$toCurrency) {
            return ResponseUtils::json($response, ['error' => 'Missing "to" query parameter'], 400);
        }

        try {
            $exchangeRate = ExchangeRateUtils::getCryptoExchangeRate('EUR', $toCurrency);
            $convertedBalance = $balance * $exchangeRate;

            return ResponseUtils::json($response, [
                'account_id' => $account_id,
                'original_balance' => $balance,
                'converted_balance' => $convertedBalance,
                'currency' => $toCurrency
            ]);
        } catch (Exception $e) {
            return ResponseUtils::json($response, ['error' => $e->getMessage()], 502);
        }
    }
}
