<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\MobileMoneyProvider;
use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EscrowService
{
    public function __construct(
        private LedgerService $ledger,
        private PlatformConfig $config,
        private NotificationService $notifications,
    ) {}

    public function payWithMobileMoney(Booking $booking, User $customer, string $provider, string $phone): Booking
    {
        if (! in_array($provider, array_column(MobileMoneyProvider::cases(), 'value'), true)) {
            throw new DomainException('Choose Airtel Money, MTN MoMo or Zamtel Kwacha.');
        }

        if ($customer->role === UserRole::BusinessClient) {
            throw new DomainException('Business clients pay from the Xpressbine Business Wallet.');
        }

        return DB::transaction(function () use ($booking, $customer, $provider, $phone) {
            $booking = $this->lockPayable($booking, $customer);

            $this->ledger->post(
                LedgerEntryType::Payment,
                $booking->amount,
                '0.00',
                "booking:{$booking->id}:payment",
                'Mock mobile money payment received',
                $booking,
                $customer,
                null,
                $customer,
                ['mobile_money_provider' => $provider, 'mobile_money_number' => $phone, 'mock' => true],
            );

            $this->hold($booking, $customer);

            return $booking->fresh();
        });
    }

    public function payWithWallet(Booking $booking, User $customer): Booking
    {
        if ($customer->role !== UserRole::BusinessClient) {
            throw new DomainException('Wallet payment is only for business clients.');
        }

        $wallet = $customer->business?->wallet;

        if (! $wallet) {
            throw new DomainException('This business does not have a wallet yet.');
        }

        return DB::transaction(function () use ($booking, $customer, $wallet) {
            $booking = $this->lockPayable($booking, $customer);
            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $balance = $this->ledger->walletBalance($wallet);

            if (Money::lt($balance, $booking->amount)) {
                throw new DomainException('The Business Wallet does not have enough for this booking.');
            }

            if ($booking->business_site_id) {
                $site = $booking->site;
                if ($site && $site->spending_limit !== null) {
                    $projected = Money::add($this->ledger->siteSpendThisMonth($site), $booking->amount);
                    if (Money::gt($projected, $site->spending_limit)) {
                        throw new DomainException('This booking is above the spending limit for that site.');
                    }
                }
            }

            $this->ledger->post(
                LedgerEntryType::WalletPayment,
                $booking->amount,
                '0.00',
                "booking:{$booking->id}:wallet_payment",
                'Business Wallet drawn into escrow',
                $booking,
                $customer,
                $wallet,
                $customer,
                ['mock' => true],
            );

            $this->hold($booking, $customer);

            return $booking->fresh();
        });
    }

    public function topUp(Wallet $wallet, User $actor, string $amount, string $method): \App\Models\LedgerEntry
    {
        $amount = Money::of($amount);

        if (! Money::isPositive($amount)) {
            throw new DomainException('Top-up amount must be greater than zero.');
        }

        return $this->ledger->post(
            LedgerEntryType::WalletTopup,
            $amount,
            '0.00',
            'wallet:'.$wallet->id.':topup:'.Str::uuid(),
            'Business Wallet top-up',
            null,
            $wallet->business->owner,
            $wallet,
            $actor,
            ['method' => $method, 'mock' => true],
        );
    }

    public function release(Booking $booking, ?User $actor, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($this->ledger->has($booking, LedgerEntryType::Release)) {
                return $booking;
            }

            $balance = $this->ledger->escrowBalance($booking);

            if (! Money::isPositive($balance)) {
                throw new DomainException('There is nothing left in escrow to release.');
            }

            $commission = Money::mul($balance, $this->config->rate('commission_rate'));
            $payout = Money::sub($balance, $commission);
            $providerUser = $booking->provider->user;

            $this->ledger->post(
                LedgerEntryType::Release,
                $balance,
                Money::neg($balance),
                "booking:{$booking->id}:release",
                $reason,
                $booking,
                $providerUser,
                null,
                $actor,
            );

            $this->ledger->post(
                LedgerEntryType::Commission,
                $commission,
                '0.00',
                "booking:{$booking->id}:commission",
                'Platform commission',
                $booking,
                null,
                null,
                $actor,
                ['rate' => $this->config->rate('commission_rate')],
            );

            $this->ledger->post(
                LedgerEntryType::Payout,
                $payout,
                '0.00',
                "booking:{$booking->id}:payout",
                'Provider payout from escrow',
                $booking,
                $providerUser,
                null,
                $actor,
                ['mock_mobile_money' => $providerUser->mobile_money_provider],
            );

            $booking->escrow_released_at = now();
            $booking->commission_amount = $commission;
            $booking->payout_amount = $payout;
            $booking->save();

            $this->notifications->send(
                $providerUser,
                'payout',
                'Payout sent',
                "K{$payout} is on its way to your mobile money for {$booking->reference}.",
                ['booking_id' => $booking->id],
            );

            $this->notifications->send(
                $booking->customer,
                'escrow_released',
                'Payment released',
                "Escrow for {$booking->reference} has been released to the provider.",
                ['booking_id' => $booking->id],
            );

            return $booking;
        });
    }

    public function autoRelease(Booking $booking): void
    {
        $booking->refresh();

        if ($booking->status !== BookingStatus::Completed) {
            return;
        }

        if ($booking->escrow_released_at !== null) {
            return;
        }

        if ($booking->auto_release_at === null || $booking->auto_release_at->isFuture()) {
            return;
        }

        $released = $this->release($booking, null, 'Automatic release after the confirmation window');
        $released->auto_released_at = now();
        $released->save();
    }

    public function refundFull(Booking $booking, ?User $actor, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $balance = $this->ledger->escrowBalance($booking);

            if (! Money::isPositive($balance)) {
                throw new DomainException('There is nothing left in escrow to refund.');
            }

            if ($this->ledger->has($booking, LedgerEntryType::Refund)) {
                return $booking;
            }

            $wallet = $this->ledger->fundedByWallet($booking);

            $this->ledger->post(
                LedgerEntryType::Refund,
                $balance,
                Money::neg($balance),
                "booking:{$booking->id}:refund",
                $reason,
                $booking,
                $booking->customer,
                $wallet,
                $actor,
            );

            return $booking->fresh();
        });
    }

    /**
     * Keep a call-out fee for the provider and refund the rest.
     * The fee is not commissioned: the refund policy pays it to the provider.
     */
    public function settleWithCallOutFee(Booking $booking, ?User $actor, string $reason, string $rateKey = 'call_out_fee_rate'): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason, $rateKey) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $balance = $this->ledger->escrowBalance($booking);

            if (! Money::isPositive($balance)) {
                throw new DomainException('There is nothing left in escrow to settle.');
            }

            $fee = Money::min($balance, Money::mul($booking->amount, $this->config->rate($rateKey)));
            $refund = Money::sub($balance, $fee);
            $wallet = $this->ledger->fundedByWallet($booking);

            if (Money::isPositive($refund)) {
                $this->ledger->post(
                    LedgerEntryType::PartialRefund,
                    $refund,
                    Money::neg($refund),
                    "booking:{$booking->id}:partial_refund",
                    $reason,
                    $booking,
                    $booking->customer,
                    $wallet,
                    $actor,
                );
            }

            if (Money::isPositive($fee)) {
                $this->ledger->post(
                    LedgerEntryType::CallOutFee,
                    $fee,
                    Money::neg($fee),
                    "booking:{$booking->id}:call_out_fee",
                    'Call-out fee paid to the provider',
                    $booking,
                    $booking->provider->user,
                    null,
                    $actor,
                );
            }

            return $booking->fresh();
        });
    }

    public function refundPartialAmount(Booking $booking, string $amount, ?User $actor, string $reason): void
    {
        $amount = Money::of($amount);
        $balance = $this->ledger->escrowBalance($booking);

        if (! Money::isPositive($amount) || Money::gt($amount, $balance)) {
            throw new DomainException('The refund amount must be within the escrow balance.');
        }

        $wallet = $this->ledger->fundedByWallet($booking);

        $this->ledger->post(
            LedgerEntryType::PartialRefund,
            $amount,
            Money::neg($amount),
            "booking:{$booking->id}:partial_refund",
            $reason,
            $booking,
            $booking->customer,
            $wallet,
            $actor,
        );
    }

    private function hold(Booking $booking, User $actor): void
    {
        $this->ledger->post(
            LedgerEntryType::Hold,
            $booking->amount,
            $booking->amount,
            "booking:{$booking->id}:hold",
            'Funds held in escrow until the job is confirmed',
            $booking,
            $actor,
            null,
            $actor,
        );

        $providerUser = $booking->provider->user;

        $this->notifications->send(
            $providerUser,
            'booking_funded',
            'A booking is funded',
            "{$booking->customer->name} paid K{$booking->amount} into escrow for {$booking->reference}. You can accept it.",
            ['booking_id' => $booking->id],
        );
    }

    private function lockPayable(Booking $booking, User $customer): Booking
    {
        $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

        if ($booking->customer_id !== $customer->id) {
            throw new DomainException('You can only pay for your own booking.');
        }

        if ($booking->status !== BookingStatus::Requested) {
            throw new DomainException('This booking is not waiting for payment.');
        }

        if ($this->ledger->isFunded($booking) || $this->ledger->has($booking, LedgerEntryType::Hold)) {
            throw new DomainException('This booking is already funded.');
        }

        return $booking;
    }
}
