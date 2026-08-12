<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public function currencies(): JsonResponse
    {
        $currencies = $this->exchangeRates->currencies();

        if ($currencies === []) {
            return response()->json([
                'message' => 'Unable to load currencies right now.',
            ], 503);
        }

        return response()->json([
            'currencies' => $currencies,
            'source' => 'frankfurter',
        ]);
    }

    public function rates(Request $request): JsonResponse
    {
        $validated = Validator::make($request->query(), [
            'base' => ['required', 'string', 'size:3'],
        ])->validate();

        $rates = $this->exchangeRates->latestRates(strtoupper($validated['base']));

        if ($rates === null) {
            return response()->json(['message' => 'Unable to fetch exchange rates right now.'], 503);
        }

        return response()->json([...$rates, 'source' => 'frankfurter']);
    }

    public function history(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), ['from' => ['required', 'string', 'size:3'], 'to' => ['required', 'string', 'size:3'], 'range' => ['nullable', 'in:7d,30d,90d']])->validate();
        $days = (int) rtrim($data['range'] ?? '7d', 'd');
        $rates = DB::table('exchange_rates')->where('base_currency', strtoupper($data['from']))->where('target_currency', strtoupper($data['to']))->where('fetched_at', '>=', now()->subDays($days))->orderBy('fetched_at')->get(['rate', 'fetched_at']);

        return response()->json(['from' => strtoupper($data['from']), 'to' => strtoupper($data['to']), 'rates' => $rates]);
    }

    public function convert(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ])->validate();

        $from = strtoupper($validated['from']);
        $to = strtoupper($validated['to']);
        $amount = (float) $validated['amount'];

        if ($from === $to) {
            return response()->json([
                'from' => $from,
                'to' => $to,
                'amount' => $amount,
                'rate' => 1,
                'converted_amount' => $amount,
                'source' => 'local',
                'fetched_at' => now()->toIso8601String(),
            ]);
        }

        $rateDetails = $this->exchangeRates->rate($from, $to);

        if ($rateDetails === null) {
            return response()->json([
                'message' => 'Unable to find a current exchange rate for this currency pair.',
            ], 503);
        }

        $rate = $rateDetails['rate'];
        $convertedAmount = round($amount * $rate, 4);

        if ($user = $request->user('sanctum')) {
            DB::table('conversions')->insert([
                'user_id' => $user->id, 'from_currency' => $from, 'to_currency' => $to, 'amount' => $amount,
                'converted_amount' => $convertedAmount, 'rate_used' => $rate, 'created_at' => now(),
            ]);
        }

        return response()->json([
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'rate' => $rate,
            'converted_amount' => $convertedAmount,
            'source' => 'frankfurter',
            'fetched_at' => $rateDetails['fetched_at'],
        ]);
    }
}
