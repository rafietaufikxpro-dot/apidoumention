<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BitcoinPriceService;
use Illuminate\Http\JsonResponse;
use Throwable;

class RateController extends Controller
{
    public function show(BitcoinPriceService $prices): JsonResponse
    {
        try {
            $rate = $prices->current();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Price provider is unavailable.'], 502);
        }

        return response()->json(['data' => [
            'currency' => 'USD',
            'rate' => $rate,
            'retrieved_at' => now()->toIso8601String(),
        ]]);
    }
}
