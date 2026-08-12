<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FetchRates extends Command
{
    protected $signature = 'rates:fetch {base=EUR}';

    protected $description = 'Fetch and store the latest exchange-rate snapshot.';

    public function handle(ExchangeRateService $exchangeRates): int
    {
        $base = strtoupper($this->argument('base'));
        Cache::forget("rates:latest:{$base}");
        $data = $exchangeRates->latestRates($base);
        if (! $data) {
            return self::FAILURE;
        } $now = now();
        foreach ($data['rates'] as $code => $rate) {
            DB::table('exchange_rates')->insert(['base_currency' => $base, 'target_currency' => $code, 'rate' => $rate, 'fetched_at' => $data['fetched_at'] ?: $now, 'created_at' => $now, 'updated_at' => $now]);
        } foreach ($exchangeRates->currencies() as $currency) {
            DB::table('currencies')->updateOrInsert(['code' => $currency['code']], ['name' => $currency['name'], 'updated_at' => $now, 'created_at' => $now]);
        } $this->info('Rates stored.');

        return self::SUCCESS;
    }
}
