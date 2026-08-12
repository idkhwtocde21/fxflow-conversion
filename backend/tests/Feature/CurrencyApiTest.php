<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyApiTest extends TestCase
{
    public function test_it_returns_latest_rates_for_a_base_currency(): void
    {
        Cache::flush();
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'USD',
                'date' => '2026-08-12',
                'rates' => ['EUR' => 0.91, 'JPY' => 147.52],
            ]),
        ]);

        $this->getJson('/api/rates?base=usd')
            ->assertOk()
            ->assertJsonPath('base', 'USD')
            ->assertJsonPath('rates.EUR', 0.91);
    }

    public function test_it_converts_an_amount_using_the_cached_base_rates(): void
    {
        Cache::flush();
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'USD',
                'date' => '2026-08-12',
                'rates' => ['EUR' => 0.91],
            ]),
        ]);

        $this->postJson('/api/convert', ['from' => 'usd', 'to' => 'eur', 'amount' => 100])
            ->assertOk()
            ->assertJsonPath('from', 'USD')
            ->assertJsonPath('to', 'EUR')
            ->assertJsonPath('rate', 0.91)
            ->assertJsonPath('converted_amount', 91);
    }

    public function test_it_rejects_invalid_conversion_input(): void
    {
        $this->postJson('/api/convert', ['from' => 'USD', 'to' => 'EUR', 'amount' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }
}
