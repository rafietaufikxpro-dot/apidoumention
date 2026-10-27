<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitcoinPriceService
{
    /**
     * Current Bitcoin price in USD.
     *
     * @throws RuntimeException when the price provider fails or returns unexpected data.
     */
    public function current(): float
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get(config('services.bitcoin.price_url'), [
                'ids' => 'bitcoin',
                'vs_currencies' => 'usd',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Price provider returned HTTP '.$response->status());
        }

        $price = $response->json('bitcoin.usd');

        if (! is_numeric($price)) {
            throw new RuntimeException('Price provider returned an unexpected payload.');
        }

        return (float) $price;
    }
}
