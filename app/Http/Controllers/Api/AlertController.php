<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceAlert;
use App\Services\BitcoinPriceService;
use App\Services\TwilioSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class AlertController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PriceAlert::query()->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'target_rate' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
        ]);

        $alert = PriceAlert::create($data);

        return response()->json(['data' => $alert], 201);
    }

    public function destroy(PriceAlert $alert): Response
    {
        $alert->delete();

        return response()->noContent();
    }

    /**
     * Compare the current Bitcoin price with every alert and send an SMS
     * for each alert whose target is above the current price.
     * An alert is not notified again within one hour.
     */
    public function check(BitcoinPriceService $prices, TwilioSmsService $sms): JsonResponse
    {
        try {
            $rate = $prices->current();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Price provider is unavailable.'], 502);
        }

        $results = [];

        foreach (PriceAlert::query()->orderBy('id')->get() as $alert) {
            $status = 'above_target';

            if ($rate < $alert->target_rate) {
                if ($alert->last_notified_at?->gt(now()->subHour())) {
                    $status = 'cooldown';
                } else {
                    try {
                        $sms->send($alert->phone_number, sprintf(
                            'Bitcoin is at $%s, below your target of $%s.',
                            number_format($rate, 2),
                            number_format($alert->target_rate, 2),
                        ));
                        $alert->update(['last_notified_at' => now()]);
                        $status = 'notified';
                    } catch (Throwable $e) {
                        report($e);
                        $status = 'failed';
                    }
                }
            }

            $results[] = ['id' => $alert->id, 'status' => $status];
        }

        return response()->json(['data' => [
            'rate' => $rate,
            'checked' => count($results),
            'notified' => count(array_filter($results, fn ($r) => $r['status'] === 'notified')),
            'results' => $results,
        ]]);
    }
}
