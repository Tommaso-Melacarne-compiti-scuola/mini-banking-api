<?php

// Fetch exchange rates from an external APIs:
// Frankfurter v2 
// Binance API

class ExchangeRateUtils
{
    private static function validateCurrencyCode(string $currency): void
    {
        // Check if the currency is valid via regex (3-letter ISO code)
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new Exception("Invalid currency code: $currency. Must be a 3-letter ISO code.");
        }
    }

    public static function getFiatExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        self::validateCurrencyCode($fromCurrency);
        self::validateCurrencyCode($toCurrency);

        $url = "https://api.frankfurter.dev/v2/rate/$fromCurrency/$toCurrency";

        $response = file_get_contents($url);

        if ($response === false) {
            throw new Exception("Failed to fetch exchange rate from Frankfurter API");
        }

        $data = json_decode($response, true);

        if (!isset($data['rate'])) {
            throw new Exception("Invalid response from Frankfurter API: " . $response);
        }

        return (float)$data['rate'];
    }

    private static function validateCryptoCurrencyCode(string $currency): void
    {
        // Check if the currency is valid via regex (3-letter ISO code for fiat, or common crypto symbols)
        if (!preg_match('/^[A-Z]+$/', $currency)) {
            throw new Exception("Invalid currency code: $currency. Must be a 3-letter ISO code or common crypto symbol.");
        }
    }

    public static function getCryptoExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        self::validateCryptoCurrencyCode($fromCurrency);
        self::validateCryptoCurrencyCode($toCurrency);

        $url = "https://api.binance.com/api/v3/ticker/price?symbol={$toCurrency}{$fromCurrency}";

        $response = file_get_contents($url);

        if ($response === false) {
            throw new Exception("Failed to fetch exchange rate from Binance API");
        }

        $data = json_decode($response, true);

        if (!isset($data['price'])) {
            throw new Exception("Invalid response from Binance API: " . $response);
        }

        $inverseConversionRate = (float)$data['price'];

        return 1 / $inverseConversionRate;
    }
}