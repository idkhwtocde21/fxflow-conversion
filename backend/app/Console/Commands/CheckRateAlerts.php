<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckRateAlerts extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Trigger rate alerts whose target has been reached.';

    public function handle(ExchangeRateService $exchangeRates): int
    {
        foreach (DB::table('rate_alerts')->join('users', 'users.id', '=', 'rate_alerts.user_id')->where('status', 'active')->get() as $alert) {
            $details = $exchangeRates->rate($alert->from_currency, $alert->to_currency);
            if (! $details) {
                continue;
            } $hit = $alert->condition === 'above' ? $details['rate'] >= $alert->target_rate : $details['rate'] <= $alert->target_rate;
            if (! $hit) {
                continue;
            } DB::table('rate_alerts')->where('id', $alert->id)->update(['status' => 'triggered', 'triggered_at' => now(), 'updated_at' => now()]);
            Mail::raw("Your {$alert->from_currency}/{$alert->to_currency} alert has triggered at {$details['rate']}.", fn ($mail) => $mail->to($alert->email)->subject('Currency rate alert triggered'));
            $this->info("Triggered alert {$alert->id}");
        }

return self::SUCCESS;
    }
}
