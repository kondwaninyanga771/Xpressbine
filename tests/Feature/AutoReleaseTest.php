<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;

class AutoReleaseTest extends MarketplaceTestCase
{
    public function test_escrow_releases_automatically_after_the_confirmation_window(): void
    {
        $job = $this->completedPaidBooking();

        $this->artisan('escrow:auto-release')->assertSuccessful();
        $this->api($job['customerToken'])->getJson("/api/bookings/{$job['bookingId']}")
            ->assertJsonPath('data.escrow_balance', '350.00')
            ->assertJsonPath('data.escrow_released_at', null);

        Carbon::setTestNow(now()->addHours(49));
        $this->artisan('escrow:auto-release')->assertSuccessful();

        $booking = $this->api($job['customerToken'])->getJson("/api/bookings/{$job['bookingId']}")->json('data');
        $this->assertSame('completed', $booking['status']);
        $this->assertSame('0.00', $booking['escrow_balance']);
        $this->assertSame('42.00', $booking['commission_amount']);
        $this->assertSame('308.00', $booking['payout_amount']);
        $this->assertNotNull($booking['auto_released_at']);

        $earnings = $this->api($job['providerToken'])->getJson('/api/provider/earnings');
        $earnings->assertSuccessful()->assertJsonPath('data.earnings', '308.00');

        Carbon::setTestNow();
    }
}
