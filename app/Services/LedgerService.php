<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\Booking;
use App\Models\BusinessSite;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Money;

class LedgerService
{
    public function __construct(private PlatformConfig $config) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function post(
        LedgerEntryType $type,
        string $amount,
        string $escrowDelta,
        string $idempotencyKey,
        string $description,
        ?Booking $booking = null,
        ?User $user = null,
        ?Wallet $wallet = null,
        ?User $actor = null,
        array $metadata = [],
    ): LedgerEntry {
        $existing = LedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        return LedgerEntry::query()->create([
            'booking_id' => $booking?->id,
            'user_id' => $user?->id,
            'wallet_id' => $wallet?->id,
            'type' => $type,
            'amount' => Money::of($amount),
            'escrow_delta' => Money::of($escrowDelta),
            'currency' => $this->config->get('currency'),
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'created_by' => $actor?->id,
        ]);
    }

    public function escrowBalance(Booking $booking): string
    {
        $sum = LedgerEntry::query()->where('booking_id', $booking->id)->sum('escrow_delta');

        return Money::of($sum);
    }

    public function isFunded(Booking $booking): bool
    {
        return Money::isPositive($this->escrowBalance($booking));
    }

    public function has(Booking $booking, LedgerEntryType $type): bool
    {
        return LedgerEntry::query()
            ->where('booking_id', $booking->id)
            ->where('type', $type)
            ->exists();
    }

    public function fundedByWallet(Booking $booking): ?Wallet
    {
        $entry = LedgerEntry::query()
            ->where('booking_id', $booking->id)
            ->where('type', LedgerEntryType::WalletPayment)
            ->first();

        return $entry?->wallet;
    }

    public function walletBalance(Wallet $wallet): string
    {
        $balance = '0.00';

        $entries = LedgerEntry::query()->where('wallet_id', $wallet->id)->get(['type', 'amount']);

        foreach ($entries as $entry) {
            if (in_array($entry->type, [
                LedgerEntryType::WalletTopup,
                LedgerEntryType::Refund,
                LedgerEntryType::PartialRefund,
            ], true)) {
                $balance = Money::add($balance, $entry->amount);
            }

            if ($entry->type === LedgerEntryType::WalletPayment) {
                $balance = Money::sub($balance, $entry->amount);
            }
        }

        return $balance;
    }

    public function siteSpendThisMonth(BusinessSite $site): string
    {
        $bookingIds = Booking::query()->where('business_site_id', $site->id)->pluck('id');

        if ($bookingIds->isEmpty()) {
            return '0.00';
        }

        $payments = LedgerEntry::query()
            ->whereIn('booking_id', $bookingIds)
            ->where('type', LedgerEntryType::WalletPayment)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $refunds = LedgerEntry::query()
            ->whereIn('booking_id', $bookingIds)
            ->whereIn('type', [LedgerEntryType::Refund, LedgerEntryType::PartialRefund])
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        return Money::max('0.00', Money::sub($payments, $refunds));
    }

    public function providerEarnings(User $providerUser): string
    {
        $sum = LedgerEntry::query()
            ->where('user_id', $providerUser->id)
            ->whereIn('type', [LedgerEntryType::Payout, LedgerEntryType::CallOutFee])
            ->sum('amount');

        return Money::of($sum);
    }

    public function platformRevenue(): string
    {
        return Money::of(
            LedgerEntry::query()->where('type', LedgerEntryType::Commission)->sum('amount')
        );
    }

    public function totalEscrowHeld(): string
    {
        return Money::of(LedgerEntry::query()->sum('escrow_delta'));
    }
}
