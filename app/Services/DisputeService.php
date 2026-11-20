<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\DisputeStatus;
use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DisputeService
{
    public function __construct(
        private EscrowService $escrow,
        private LedgerService $ledger,
        private PlatformConfig $config,
        private NotificationService $notifications,
    ) {}

    public function open(Booking $booking, User $customer, string $reason, UploadedFile $evidence): Dispute
    {
        if ($booking->customer_id !== $customer->id) {
            throw new DomainException('You can only raise a claim on your own booking.');
        }

        if ($booking->status !== BookingStatus::Completed) {
            throw new DomainException('A claim can be raised after the provider marks the job done, before funds are released.');
        }

        if ($booking->escrow_released_at || ! $this->ledger->isFunded($booking)) {
            throw new DomainException('The claim window has closed and the escrow has already moved.');
        }

        if ($booking->auto_release_at === null || now()->gte($booking->auto_release_at)) {
            throw new DomainException('The claim window has closed.');
        }

        if ($booking->disputes()->whereIn('status', [DisputeStatus::Open->value, DisputeStatus::ProviderResponded->value])->exists()) {
            throw new DomainException('This booking already has an open claim.');
        }

        $path = $evidence->store('dispute-evidence/'.$booking->id, 'public');

        $booking->status->assertCanTransitionTo(BookingStatus::Disputed);
        $booking->status = BookingStatus::Disputed;
        $booking->save();

        $dispute = Dispute::query()->create([
            'booking_id' => $booking->id,
            'raised_by' => $customer->id,
            'reason' => $reason,
            'evidence_path' => $path,
            'status' => DisputeStatus::Open,
        ]);

        $this->flagIfExcessive($customer);

        $this->notifications->send(
            $booking->provider->user,
            'dispute_opened',
            'A claim was opened',
            "{$customer->name} opened a claim on {$booking->reference}. You can respond in the app.",
            ['dispute_id' => $dispute->id, 'booking_id' => $booking->id],
        );

        return $dispute;
    }

    public function respond(Dispute $dispute, User $providerUser, string $message): Dispute
    {
        $dispute->loadMissing('booking.provider');
        $profileId = $providerUser->providerProfile?->id;

        if (! $providerUser->isProvider() || $dispute->booking->provider_profile_id !== $profileId) {
            throw new DomainException('Only the booked provider can respond.');
        }

        if (! in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::ProviderResponded], true)) {
            throw new DomainException('This claim is no longer waiting for a response.');
        }

        $dispute->provider_response = $message;
        $dispute->provider_responded_at = now();
        $dispute->status = DisputeStatus::ProviderResponded;
        $dispute->save();

        $this->notifications->send(
            $dispute->booking->customer,
            'dispute_response',
            'Provider responded',
            "{$providerUser->name} responded to the claim on {$dispute->booking->reference}.",
            ['dispute_id' => $dispute->id],
        );

        return $dispute;
    }

    public function resolve(Dispute $dispute, User $admin, string $outcome, ?string $amount, ?string $notes): Dispute
    {
        if (! $admin->isAdmin()) {
            throw new DomainException('Only an admin can decide a claim.');
        }

        return DB::transaction(function () use ($dispute, $admin, $outcome, $amount, $notes) {
            $dispute = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($dispute->booking_id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== BookingStatus::Disputed) {
                throw new DomainException('This booking is not in dispute.');
            }

            if ($outcome === 'full_refund') {
                $balance = $this->ledger->escrowBalance($booking);
                $this->escrow->refundFull($booking, $admin, 'Admin full refund');
                $booking->status->assertCanTransitionTo(BookingStatus::Refunded);
                $booking->status = BookingStatus::Refunded;
                $dispute->outcome = 'full_refund';
                $dispute->refund_amount = $balance;
                $dispute->status = DisputeStatus::Resolved;
            } elseif ($outcome === 'partial_refund') {
                if ($amount === null) {
                    throw new DomainException('A partial refund needs an amount.');
                }

                $refund = Money::of($amount);
                $balance = $this->ledger->escrowBalance($booking);

                if (Money::gte($refund, $balance)) {
                    throw new DomainException('Use a full refund when the whole escrow goes back to the customer.');
                }

                $this->escrow->refundPartialAmount($booking, $refund, $admin, 'Admin partial refund');
                $this->escrow->release($booking->fresh(), $admin, 'Remainder released after a partial refund');
                $booking->refresh();
                $booking->status->assertCanTransitionTo(BookingStatus::Completed);
                $booking->status = BookingStatus::Completed;
                $dispute->outcome = 'partial_refund';
                $dispute->refund_amount = $refund;
                $dispute->status = DisputeStatus::Resolved;
            } elseif ($outcome === 'release') {
                $this->escrow->release($booking, $admin, 'Claim rejected and escrow released');
                $booking->refresh();
                $booking->status->assertCanTransitionTo(BookingStatus::Completed);
                $booking->status = BookingStatus::Completed;
                $dispute->outcome = 'release';
                $dispute->refund_amount = '0.00';
                $dispute->status = DisputeStatus::Resolved;
            } elseif ($outcome === 'fix_first') {
                if ($booking->fix_attempted) {
                    throw new DomainException('The provider has already had a chance to fix this job.');
                }

                $booking->status->assertCanTransitionTo(BookingStatus::InProgress);
                $booking->status = BookingStatus::InProgress;
                $booking->fix_attempted = true;
                $booking->fix_due_at = now()->addHours((int) $this->config->get('fix_window_hours'));
                $booking->auto_release_at = null;
                $dispute->outcome = 'fix_first';
                $dispute->status = DisputeStatus::FixRequested;
            } else {
                throw new DomainException('Outcome must be full_refund, partial_refund, release or fix_first.');
            }

            $booking->save();
            $dispute->admin_id = $admin->id;
            $dispute->admin_notes = $notes;
            $dispute->resolved_at = $dispute->status === DisputeStatus::Resolved ? now() : null;
            $dispute->save();

            $this->notifications->send(
                $booking->customer,
                'dispute_resolved',
                'Claim updated',
                "An admin set {$booking->reference} to {$outcome}.",
                ['dispute_id' => $dispute->id],
            );

            $this->notifications->send(
                $booking->provider->user,
                'dispute_resolved',
                'Claim updated',
                "An admin set {$booking->reference} to {$outcome}.",
                ['dispute_id' => $dispute->id],
            );

            return $dispute->fresh('booking');
        });
    }

    private function flagIfExcessive(User $customer): void
    {
        $days = (int) $this->config->get('claim_review_window_days');
        $threshold = (int) $this->config->get('claim_review_threshold');

        $count = Dispute::query()
            ->where('raised_by', $customer->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        if ($count >= $threshold && $customer->flagged_at === null) {
            $customer->flagged_at = now();
            $customer->save();
        }
    }
}
