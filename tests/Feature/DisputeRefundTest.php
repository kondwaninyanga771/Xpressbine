<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;

class DisputeRefundTest extends MarketplaceTestCase
{
    public function test_dispute_blocks_auto_release_and_admin_can_refund_in_full(): void
    {
        $job = $this->completedPaidBooking();
        [$admin, $adminToken] = $this->makeUser(UserRole::Admin, ['name' => 'Mutale Zulu']);

        $dispute = $this->api($job['customerToken'])->post("/api/bookings/{$job['bookingId']}/dispute", [
            'reason' => 'The sink is still leaking after the visit.',
            'evidence' => $this->image('leak.jpg'),
        ], ['Accept' => 'application/json']);

        $dispute->assertSuccessful();
        $disputeId = $dispute->json('data.id');

        $this->api($job['providerToken'])->postJson("/api/disputes/{$disputeId}/respond", [
            'message' => 'I tightened the trap. Happy to look again.',
        ])->assertSuccessful();

        Carbon::setTestNow(now()->addHours(49));
        $this->artisan('escrow:auto-release')->assertSuccessful();

        $this->api($job['customerToken'])->getJson("/api/bookings/{$job['bookingId']}")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'disputed')
            ->assertJsonPath('data.escrow_balance', '350.00');

        $this->api($adminToken)->postJson("/api/admin/disputes/{$disputeId}/resolve", [
            'outcome' => 'full_refund',
            'notes' => 'Work was not finished.',
        ])->assertSuccessful()
            ->assertJsonPath('data.outcome', 'full_refund');

        $this->api($job['customerToken'])->getJson("/api/bookings/{$job['bookingId']}")
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.escrow_balance', '0.00');

        $types = collect($this->api($job['customerToken'])->getJson("/api/bookings/{$job['bookingId']}/ledger")->json('data'))->pluck('type');
        $this->assertTrue($types->contains('refund'));
        $this->assertFalse($types->contains('payout'));

        $this->assertInstanceOf(User::class, $admin);
        Carbon::setTestNow();
    }

    public function test_admin_partial_refund_pays_the_provider_the_remainder_minus_commission(): void
    {
        $job = $this->completedPaidBooking();
        [, $adminToken] = $this->makeUser(UserRole::Admin);

        $disputeId = $this->api($job['customerToken'])->post("/api/bookings/{$job['bookingId']}/dispute", [
            'reason' => 'Only the trap was replaced. The tap is still loose.',
            'evidence' => $this->image('tap.jpg'),
        ], ['Accept' => 'application/json'])->json('data.id');

        $this->api($adminToken)->postJson("/api/admin/disputes/{$disputeId}/resolve", [
            'outcome' => 'partial_refund',
            'amount' => 100,
            'notes' => 'Refund the unfinished tap work.',
        ])->assertSuccessful();

        $booking = $this->api($job['customerToken'])->getJson("/api/bookings/{$job['bookingId']}")->json('data');
        $this->assertSame('completed', $booking['status']);
        $this->assertSame('0.00', $booking['escrow_balance']);
        $this->assertSame('30.00', $booking['commission_amount']);
        $this->assertSame('220.00', $booking['payout_amount']);
    }

    public function test_customer_cannot_claim_without_evidence_or_after_release(): void
    {
        $job = $this->completedPaidBooking();

        $this->api($job['customerToken'])->postJson("/api/bookings/{$job['bookingId']}/dispute", [
            'reason' => 'No photo attached to this claim at all.',
        ])->assertStatus(422);

        $this->api($job['customerToken'])->postJson("/api/bookings/{$job['bookingId']}/confirm")->assertSuccessful();

        $this->api($job['customerToken'])->post("/api/bookings/{$job['bookingId']}/dispute", [
            'reason' => 'Too late, the money has already moved.',
            'evidence' => $this->image('late.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_fix_first_pauses_escrow_until_the_provider_finishes_again(): void
    {
        $job = $this->completedPaidBooking();
        [, $adminToken] = $this->makeUser(UserRole::Admin);

        $disputeId = $this->api($job['customerToken'])->post("/api/bookings/{$job['bookingId']}/dispute", [
            'reason' => 'The joint is still weeping. Please fix it.',
            'evidence' => $this->image('joint.jpg'),
        ], ['Accept' => 'application/json'])->json('data.id');

        $this->api($adminToken)->postJson("/api/admin/disputes/{$disputeId}/resolve", [
            'outcome' => 'fix_first',
            'notes' => 'One repair visit within the fix window.',
        ])->assertSuccessful()->assertJsonPath('data.outcome', 'fix_first');

        $this->api($job['customerToken'])->getJson("/api/bookings/{$job['bookingId']}")
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.escrow_balance', '350.00')
            ->assertJsonPath('data.fix_attempted', true);

        $this->api($job['providerToken'])->postJson("/api/bookings/{$job['bookingId']}/complete")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'completed');
    }
}
