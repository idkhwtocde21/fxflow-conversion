<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountDataController extends Controller
{
    private function pair(Request $request): array
    {
        $data = $request->validate(['from' => ['required', 'string', 'size:3'], 'to' => ['required', 'string', 'size:3']]);

        return ['from' => strtoupper($data['from']), 'to' => strtoupper($data['to'])];
    }

    public function conversions(Request $request): JsonResponse
    {
        return response()->json(DB::table('conversions')->where('user_id', $request->user()->id)->latest('id')->paginate(15));
    }

    public function deleteConversion(Request $request, int $id): JsonResponse
    {
        DB::table('conversions')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return response()->noContent();
    }

    public function favorites(Request $request): JsonResponse
    {
        return response()->json(['favorites' => DB::table('saved_pairs')->where('user_id', $request->user()->id)->latest('id')->get()]);
    }

    public function saveFavorite(Request $request): JsonResponse
    {
        $pair = $this->pair($request);
        $favorite = DB::table('saved_pairs')->updateOrInsert(['user_id' => $request->user()->id, 'from_currency' => $pair['from'], 'to_currency' => $pair['to']], ['created_at' => now()]);

        return response()->json(['saved' => $favorite], 201);
    }

    public function deleteFavorite(Request $request, int $id): JsonResponse
    {
        DB::table('saved_pairs')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return response()->noContent();
    }

    public function alerts(Request $request): JsonResponse
    {
        return response()->json(['alerts' => DB::table('rate_alerts')->where('user_id', $request->user()->id)->latest('id')->get()]);
    }

    public function saveAlert(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'string', 'size:3'], 'to' => ['required', 'string', 'size:3'], 'target_rate' => ['required', 'numeric', 'gt:0'], 'condition' => ['required', 'in:above,below']]);
        $id = DB::table('rate_alerts')->insertGetId(['user_id' => $request->user()->id, 'from_currency' => strtoupper($data['from']), 'to_currency' => strtoupper($data['to']), 'target_rate' => $data['target_rate'], 'condition' => $data['condition'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id], 201);
    }

    public function updateAlert(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['target_rate' => ['sometimes', 'numeric', 'gt:0'], 'condition' => ['sometimes', 'in:above,below'], 'status' => ['sometimes', 'in:active,cancelled']]);
        DB::table('rate_alerts')->where('id', $id)->where('user_id', $request->user()->id)->update([...$data, 'updated_at' => now()]);

        return response()->noContent();
    }

    public function deleteAlert(Request $request, int $id): JsonResponse
    {
        DB::table('rate_alerts')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return response()->noContent();
    }
}
