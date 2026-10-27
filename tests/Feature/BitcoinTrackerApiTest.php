<?php

namespace Tests\Feature;

use App\Models\PriceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BitcoinTrackerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tracker.api_key' => 'test-key',
            'services.twilio.sid' => 'ACtest',
            'services.twilio.token' => 'secret',
            'services.twilio.from' => '+15005550006',
        ]);
    }

    private function fakeHttp(float $price = 8000.0): void
    {
        Http::fake([
            'api.coingecko.com/*' => Http::response(['bitcoin' => ['usd' => $price]]),
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);
    }

    private function twilioRequests(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.twilio.com'))->count();
    }

    public function test_requests_without_api_key_are_rejected(): void
    {
        $this->getJson('/api/v1/rate')->assertUnauthorized();
    }

    public function test_server_without_configured_key_returns_503(): void
    {
        config(['services.tracker.api_key' => null]);

        $this->getJson('/api/v1/rate', ['X-API-Key' => 'anything'])->assertStatus(503);
    }

    public function test_rate_returns_current_price(): void
    {
        $this->fakeHttp(9123.45);

        $this->getJson('/api/v1/rate', ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.rate', 9123.45);
    }

    public function test_rate_returns_502_when_provider_fails(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response([], 500)]);

        $this->getJson('/api/v1/rate', ['X-API-Key' => 'test-key'])->assertStatus(502);
    }

    public function test_alert_can_be_created_listed_and_deleted(): void
    {
        $headers = ['X-API-Key' => 'test-key'];

        $id = $this->postJson('/api/v1/alerts', [
            'phone_number' => '+6281234567890',
            'target_rate' => 9000,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.target_rate', 9000)
            ->json('data.id');

        $this->getJson('/api/v1/alerts', $headers)->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/alerts/{$id}", [], $headers)->assertNoContent();
        $this->assertDatabaseCount('price_alerts', 0);
    }

    public function test_alert_validation_rejects_bad_input(): void
    {
        $this->postJson('/api/v1/alerts', [
            'phone_number' => '08123',
            'target_rate' => -5,
        ], ['X-API-Key' => 'test-key'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number', 'target_rate']);
    }

    public function test_check_sends_sms_when_price_is_below_target(): void
    {
        $this->fakeHttp(8000);
        $alert = PriceAlert::create(['phone_number' => '+6281234567890', 'target_rate' => 9000]);

        $this->postJson('/api/v1/alerts/check', [], ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('data.notified', 1)
            ->assertJsonPath('data.results.0.status', 'notified');

        $this->assertSame(1, $this->twilioRequests());
        $this->assertNotNull($alert->fresh()->last_notified_at);
    }

    public function test_check_does_not_send_sms_when_price_is_above_target(): void
    {
        $this->fakeHttp(12000);
        PriceAlert::create(['phone_number' => '+6281234567890', 'target_rate' => 9000]);

        $this->postJson('/api/v1/alerts/check', [], ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('data.notified', 0)
            ->assertJsonPath('data.results.0.status', 'above_target');

        $this->assertSame(0, $this->twilioRequests());
    }

    public function test_check_respects_one_hour_cooldown(): void
    {
        $this->fakeHttp(8000);
        PriceAlert::create([
            'phone_number' => '+6281234567890',
            'target_rate' => 9000,
            'last_notified_at' => now()->subMinutes(10),
        ]);

        $this->postJson('/api/v1/alerts/check', [], ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'cooldown');

        $this->assertSame(0, $this->twilioRequests());
    }

    public function test_check_marks_alert_failed_when_twilio_errors(): void
    {
        Http::fake([
            'api.coingecko.com/*' => Http::response(['bitcoin' => ['usd' => 8000]]),
            'api.twilio.com/*' => Http::response(['message' => 'bad'], 400),
        ]);
        $alert = PriceAlert::create(['phone_number' => '+6281234567890', 'target_rate' => 9000]);

        $this->postJson('/api/v1/alerts/check', [], ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'failed');

        $this->assertNull($alert->fresh()->last_notified_at);
    }
}
