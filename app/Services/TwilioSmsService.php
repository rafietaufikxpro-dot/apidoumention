<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioSmsService
{
    /**
     * Send an SMS through the Twilio Messages API.
     *
     * @throws RuntimeException when credentials are missing or Twilio rejects the request.
     */
    public function send(string $to, string $body): void
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        $response = Http::timeout(10)
            ->withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Twilio returned HTTP '.$response->status());
        }
    }
}
