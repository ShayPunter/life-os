<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyConversionService
{
    protected string $apiUrl;

    protected string $baseCurrency = 'EUR';

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        // Use exchangerate-api.io by default (free tier available)
        // Can be configured to use Wise API if user has credentials
        $this->apiUrl = config('services.currency_api.url', 'https://api.exchangerate-api.com/v4/latest');
    }

    /**
     * Convert amount from source currency to EUR.
     */
    public function convertToEur(float $amount, string $fromCurrency): array
    {
        // If already in EUR, no conversion needed
        if (strtoupper($fromCurrency) === 'EUR') {
            return [
                'amount_eur' => $amount,
                'original_amount' => $amount,
                'original_currency' => 'EUR',
                'exchange_rate' => 1.0,
            ];
        }

        $rate = $this->getExchangeRate($fromCurrency);

        $amountInEur = $amount * $rate;

        return [
            'amount_eur' => round($amountInEur, 2),
            'original_amount' => $amount,
            'original_currency' => strtoupper($fromCurrency),
            'exchange_rate' => $rate,
        ];
    }

    /**
     * Get exchange rate from source currency to EUR.
     */
    protected function getExchangeRate(string $fromCurrency): float
    {
        $cacheKey = "exchange_rate_{$fromCurrency}_to_EUR";

        // Cache rates for 1 hour
        return Cache::remember($cacheKey, 3600, function () use ($fromCurrency) {
            $rates = $this->fetchRates($fromCurrency);

            if (! isset($rates['EUR'])) {
                throw new \Exception("Exchange rate for {$fromCurrency} to EUR not found");
            }

            return $rates['EUR'];
        });
    }

    /**
     * Fetch exchange rates from API.
     *
     * @return array<string, float>
     */
    protected function fetchRates(string $baseCurrency): array
    {
        $response = Http::timeout(10)->get("{$this->apiUrl}/{$baseCurrency}");

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch exchange rates');
        }

        $data = $response->json();

        if (! isset($data['rates'])) {
            throw new \Exception('Invalid exchange rate response');
        }

        return $data['rates'];
    }

    /**
     * Get supported currencies.
     *
     * @return array<string>
     */
    public function getSupportedCurrencies(): array
    {
        return ['GBP', 'EUR', 'CZK', 'USD'];
    }

    /**
     * Check if a currency is supported.
     */
    public function isSupported(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }
}
