<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Support\Carbon;

class RefundPolicyTest extends MarketplaceTestCase
{
    public function test_customer_cancel_more_than_a_day_ahead_refunds_everything(): void
    {
        [$customer, $customerToken] = $this->makeUser(UserRole::Customer);
        [, , $package, $providerToken] = $this->makeProvider();
        $booking = $this->bookPackage($customerToken, $package, [
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
        ]);
        $this->payMomo($customerToken, $booking['id']);
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/accept")->assertSuccessful();

        $this->api($customerToken)->postJson("/api/bookings/{$booking['id']}/cancel", [
            'reason' => 'We are travelling and do not need the plumber.',
        ])->assertSuccessful()->assertJsonPath('data.status', 'refunded');

        $this->api($customerToken)->getJson("/api/bookings/{$booking['id']}")
            ->assertJsonPath('data.escrow_balance', '0.00');

        $this->assertNotNull($customer);
    }

    public function test_late_cancel_keeps_a_call_out_fee_for_the_provider(): void
    {
        [, $customerToken] = $this->makeUser(UserRole::Customer);
        [, , $package, $providerToken] = $this->makeProvider();
        $booking = $this->bookPackage($customerToken, $package, [
            'scheduled_at' => now()->addHours(3)->toIso8601String(),
        ]);
        $this->payMomo($customerToken, $booking['id']);
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/accept")->assertSuccessful();

        $this->api($customerToken)->postJson("/api/bookings/{$booking['id']}/cancel", [
            'reason' => 'Something else came up this afternoon.',
        ])->assertSuccessful()->assertJsonPath('data.status', 'cancelled');

        $ledger = collect($this->api($customerToken)->getJson("/api/bookings/{$booking['id']}/ledger")->json('data'));
        $this->assertSame('297.50', $ledger->firstWhere('type', 'partial_refund')['amount']);
        $this->assertSame('52.50', $ledger->firstWhere('type', 'call_out_fee')['amount']);
        $this->api($customerToken)->getJson("/api/bookings/{$booking['id']}")
            ->assertJsonPath('data.escrow_balance', '0.00');
    }

    public function test_provider_cancel_and_no_show_refund_the_customer(): void
    {
        [, $customerToken] = $this->makeUser(UserRole::Customer);
        [, , $package, $providerToken] = $this->makeProvider();
        $booking = $this->bookPackage($customerToken, $package);
        $this->payMomo($customerToken, $booking['id']);
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/accept")->assertSuccessful();

        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/cancel", [
            'reason' => 'I cannot get the parts today.',
        ])->assertSuccessful()->assertJsonPath('data.status', 'refunded');

        [, , $packageTwo, $providerTwo] = $this->makeProvider();
        $second = $this->bookPackage($customerToken, $packageTwo, [
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ]);
        $this->payMomo($customerToken, $second['id']);
        $this->api($providerTwo)->postJson("/api/bookings/{$second['id']}/accept")->assertSuccessful();

        Carbon::setTestNow(now()->addHours(3));
        $this->api($customerToken)->postJson("/api/bookings/{$second['id']}/no-show")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'refunded');
        Carbon::setTestNow();
    }

    public function test_customer_cannot_cancel_for_a_refund_after_work_starts(): void
    {
        [, $customerToken] = $this->makeUser(UserRole::Customer);
        [, , $package, $providerToken] = $this->makeProvider();
        $booking = $this->bookPackage($customerToken, $package);
        $this->payMomo($customerToken, $booking['id']);
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/accept")->assertSuccessful();
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/start")->assertSuccessful();

        $this->api($customerToken)->postJson("/api/bookings/{$booking['id']}/cancel", [
            'reason' => 'I changed my mind once he started.',
        ])->assertStatus(422);
    }

    public function test_customer_absent_pays_the_provider_a_call_out_fee(): void
    {
        [, $customerToken] = $this->makeUser(UserRole::Customer);
        [, , $package, $providerToken] = $this->makeProvider();
        $booking = $this->bookPackage($customerToken, $package, [
            'scheduled_at' => now()->addMinutes(30)->toIso8601String(),
        ]);
        $this->payMomo($customerToken, $booking['id']);
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/accept")->assertSuccessful();

        Carbon::setTestNow(now()->addHour());
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/customer-absent")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'cancelled');

        $ledger = collect($this->api($customerToken)->getJson("/api/bookings/{$booking['id']}/ledger")->json('data'));
        $this->assertSame('52.50', $ledger->firstWhere('type', 'call_out_fee')['amount']);
        Carbon::setTestNow();
    }
}
