<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    private const RATE_TTL_SECONDS = 3600;

    private const CURRENCIES_TTL_SECONDS = 86400;

    public function currencies(): array
    {
        return Cache::remember('currencies:all', self::CURRENCIES_TTL_SECONDS, function (): array {
            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get($this->endpoint('/currencies'));

                if (! $response->successful() || ! is_array($response->json())) {
                    return [];
                }

                return collect($response->json())
                    ->map(fn (string $name, string $code) => [
                        'code' => strtoupper($code),
                        'name' => $name,
                    ])
                    ->sortBy('code')
                    ->values()
                    ->all();
            } catch (ConnectionException) {
                return [];
            }
        });
    }

    public function latestRates(string $base): ?array
    {
        $base = strtoupper($base);

        return Cache::remember("rates:latest:{$base}", self::RATE_TTL_SECONDS, function () use ($base): ?array {
            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get($this->endpoint('/latest'), ['from' => $base]);

                if (! $response->successful() || ! is_array($response->json('rates'))) {
                    return null;
                }

                return [
                    'base' => strtoupper((string) $response->json('base', $base)),
                    'rates' => $response->json('rates'),
                    'fetched_at' => $response->json('date'),
                ];
            } catch (ConnectionException) {
                return null;
            }
        });
    }

    public function rate(string $from, string $to): ?array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return ['rate' => 1.0, 'fetched_at' => now()->toDateString()];
        }

        $rates = $this->latestRates($from);
        $rate = data_get($rates, "rates.{$to}");

        if (! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return [
            'rate' => (float) $rate,
            'fetched_at' => data_get($rates, 'fetched_at'),
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.frankfurter.url'), '/').$path;
    }
}
