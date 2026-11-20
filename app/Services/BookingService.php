<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\JobRequestStatus;
use App\Enums\PhotoKind;
use App\Enums\QuoteStatus;
use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\BusinessSite;
use App\Models\Package;
use App\Models\Quote;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private EscrowService $escrow,
        private LedgerService $ledger,
        private PlatformConfig $config,
        private ModerationService $moderation,
        private NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromPackage(User $customer, Package $package, array $data): Booking
    {
        $this->assertCustomer($customer);
        $package->loadMissing('provider.user', 'category');
        $this->assertProviderBookable($package->provider->user);

        if (! $package->is_active) {
            throw new DomainException('That package is not available.');
        }

        $site = $this->resolveSite($customer, $data['business_site_id'] ?? null);

        return DB::transaction(function () use ($customer, $package, $data, $site) {
            $booking = $this->make($customer, [
                'provider_profile_id' => $package->provider_profile_id,
                'category_id' => $package->category_id,
                'package_id' => $package->id,
                'business_site_id' => $site?->id,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $package->duration_minutes,
                'amount' => $package->price,
                'address' => $data['address'],
                'area' => $data['area'] ?? $site?->area,
                'latitude' => $data['latitude'] ?? $site?->latitude,
                'longitude' => $data['longitude'] ?? $site?->longitude,
                'notes' => $data['notes'] ?? null,
                'client_absent' => (bool) ($data['client_absent'] ?? false),
                'on_site_contact_name' => $data['on_site_contact_name'] ?? null,
                'on_site_contact_phone' => $data['on_site_contact_phone'] ?? null,
            ]);

            $this->notifications->send(
                $package->provider->user,
                'booking_requested',
                'New booking request',
                "{$customer->name} requested {$package->title} ({$booking->reference}). It can be accepted once escrow is funded.",
                ['booking_id' => $booking->id],
            );

            return $booking->load(['provider.user', 'category', 'package', 'customer']);
        });
    }

    public function createFromQuote(User $customer, Quote $quote): Booking
    {
        $this->assertCustomer($customer);
        $quote->loadMissing('jobRequest', 'provider.user', 'provider.categories');

        $job = $quote->jobRequest;

        if ($job->customer_id !== $customer->id) {
            throw new DomainException('You can only accept a quote on your own job.');
        }

        if (! in_array($job->status, [JobRequestStatus::Open, JobRequestStatus::Quoted], true)) {
            throw new DomainException('This job is no longer accepting quotes.');
        }

        if ($quote->status !== QuoteStatus::Pending) {
            throw new DomainException('This quote is no longer available.');
        }

        $this->assertProviderBookable($quote->provider->user);

        return DB::transaction(function () use ($customer, $quote, $job) {
            $booking = $this->make($customer, [
                'provider_profile_id' => $quote->provider_profile_id,
                'category_id' => $job->category_id ?? $quote->provider->categories->first()?->id,
                'job_request_id' => $job->id,
                'quote_id' => $quote->id,
                'business_site_id' => $job->business_site_id,
                'scheduled_at' => $quote->scheduled_at ?? $job->preferred_at ?? now()->addDay(),
                'duration_minutes' => $quote->duration_minutes
                    ?? (int) $this->config->get('default_job_duration_minutes'),
                'amount' => $quote->amount,
                'address' => $job->address,
                'area' => $job->area,
                'latitude' => $job->latitude,
                'longitude' => $job->longitude,
                'notes' => $job->description,
                'client_absent' => $job->client_absent,
                'on_site_contact_name' => $job->on_site_contact_name,
                'on_site_contact_phone' => $job->on_site_contact_phone,
            ]);

            if (! $booking->category_id) {
                throw new DomainException('The job needs a trade before a quote can be accepted.');
            }

            $quote->status = QuoteStatus::Accepted;
            $quote->save();

            Quote::query()
                ->where('job_request_id', $job->id)
                ->where('id', '!=', $quote->id)
                ->where('status', QuoteStatus::Pending)
                ->update(['status' => QuoteStatus::Rejected]);

            $job->status = JobRequestStatus::Booked;
            $job->save();

            $this->notifications->send(
                $quote->provider->user,
                'quote_accepted',
                'Quote accepted',
                "{$customer->name} accepted your quote for {$booking->reference}.",
                ['booking_id' => $booking->id],
            );

            return $booking->load(['provider.user', 'category', 'quote', 'customer']);
        });
    }

    public function accept(Booking $booking, User $providerUser): Booking
    {
        $this->assertOwnsBooking($booking, $providerUser);
        $this->moderation->assertProviderCanWork($providerUser);
        $booking->status->assertCanTransitionTo(BookingStatus::Accepted);

        if (! $this->ledger->isFunded($booking)) {
            throw new DomainException('Escrow must be funded before you can accept.');
        }

        $this->assertNoOverlap($booking);

        $booking->status = BookingStatus::Accepted;
        $booking->accepted_at = now();
        $booking->save();

        $this->notifications->send(
            $booking->customer,
            'booking_accepted',
            'Booking accepted',
            "{$providerUser->name} accepted {$booking->reference}.",
            ['booking_id' => $booking->id],
        );

        return $booking->fresh(['provider.user', 'customer', 'category']);
    }

    public function start(Booking $booking, User $providerUser): Booking
    {
        $this->assertOwnsBooking($booking, $providerUser);
        $this->moderation->assertProviderCanWork($providerUser);
        $booking->status->assertCanTransitionTo(BookingStatus::InProgress);
        $this->assertClientAbsentPhotos($booking, PhotoKind::Before, 'A before photo is required before starting a client-absent booking.');

        $booking->status = BookingStatus::InProgress;
        $booking->started_at = now();
        $booking->save();

        $this->notifications->send(
            $booking->customer,
            'booking_started',
            'Job started',
            "{$providerUser->name} has started {$booking->reference}.",
            ['booking_id' => $booking->id],
        );

        return $booking->fresh();
    }

    public function complete(Booking $booking, User $providerUser): Booking
    {
        $this->assertOwnsBooking($booking, $providerUser);
        $booking->status->assertCanTransitionTo(BookingStatus::Completed);
        $this->assertClientAbsentPhotos($booking, PhotoKind::After, 'An after photo is required to complete a client-absent booking.');

        $hours = $booking->client_absent
            ? (int) $this->config->get('client_absent_claim_hours')
            : (int) $this->config->get('auto_release_hours');

        $booking->status = BookingStatus::Completed;
        $booking->completed_at = now();
        $booking->auto_release_at = now()->addHours($hours);
        $booking->save();

        $this->notifications->send(
            $booking->customer,
            'booking_completed',
            'Job marked done',
            "Confirm {$booking->reference} or raise a claim before ".$booking->auto_release_at->toDateTimeString().'.',
            ['booking_id' => $booking->id],
        );

        return $booking->fresh();
    }

    public function confirm(Booking $booking, User $customer): Booking
    {
        $this->assertCustomerOwns($booking, $customer);

        if ($booking->status !== BookingStatus::Completed) {
            throw new DomainException('You can confirm a booking only after the provider marks it done.');
        }

        if ($booking->escrow_released_at) {
            return $booking;
        }

        $released = $this->escrow->release($booking, $customer, 'Customer confirmed the job');
        $released->customer_confirmed_at = now();
        $released->save();

        return $released->fresh();
    }

    public function cancel(Booking $booking, User $actor, string $reason): Booking
    {
        $isCustomer = $booking->customer_id === $actor->id;
        $isProvider = $actor->isProvider()
            && $actor->providerProfile
            && $booking->provider_profile_id === $actor->providerProfile->id;

        if (! $isCustomer && ! $isProvider && ! $actor->isAdmin()) {
            throw new DomainException('You cannot cancel this booking.');
        }

        if (! in_array($booking->status, [BookingStatus::Requested, BookingStatus::Accepted, BookingStatus::InProgress], true)) {
            throw new DomainException('This booking can no longer be cancelled.');
        }

        if ($isCustomer && $booking->status === BookingStatus::InProgress) {
            throw new DomainException('The job has started. A refund after work starts needs an admin decision.');
        }

        $funded = $this->ledger->isFunded($booking);

        $booking->cancelled_at = now();
        $booking->cancelled_by = $actor->id;
        $booking->cancellation_reason = $reason;

        if (! $funded) {
            $booking->status->assertCanTransitionTo(BookingStatus::Cancelled);
            $booking->status = BookingStatus::Cancelled;
            $booking->save();

            return $booking->fresh();
        }

        if ($isProvider || $actor->isAdmin()) {
            $this->escrow->refundFull($booking, $actor, $isProvider ? 'Provider cancelled' : 'Admin cancelled');
            $booking->status->assertCanTransitionTo(BookingStatus::Refunded);
            $booking->status = BookingStatus::Refunded;
            $booking->save();

            if ($isProvider) {
                $this->moderation->recordProviderCancel($booking->provider->user);
            }

            $this->notifications->send(
                $booking->customer,
                'booking_refunded',
                'Booking refunded',
                "{$booking->reference} was cancelled and the escrow was refunded.",
                ['booking_id' => $booking->id],
            );

            return $booking->fresh();
        }

        $hours = (int) $this->config->get('full_refund_cancel_hours');
        $fullRefund = now()->lt($booking->scheduled_at->copy()->subHours($hours));

        if ($fullRefund) {
            $this->escrow->refundFull($booking, $actor, 'Customer cancelled before the full-refund cutoff');
            $booking->status->assertCanTransitionTo(BookingStatus::Refunded);
            $booking->status = BookingStatus::Refunded;
        } else {
            $this->escrow->settleWithCallOutFee($booking, $actor, 'Customer cancelled inside the late-cancel window', 'late_cancel_fee_rate');
            $booking->status->assertCanTransitionTo(BookingStatus::Cancelled);
            $booking->status = BookingStatus::Cancelled;
        }

        $booking->save();

        $this->notifications->send(
            $booking->provider->user,
            'booking_cancelled',
            'Booking cancelled',
            "{$actor->name} cancelled {$booking->reference}.",
            ['booking_id' => $booking->id],
        );

        return $booking->fresh();
    }

    public function reportNoShow(Booking $booking, User $customer): Booking
    {
        $this->assertCustomerOwns($booking, $customer);

        if ($booking->status !== BookingStatus::Accepted) {
            throw new DomainException('A no-show can be reported only after the provider has accepted and before the job starts.');
        }

        $grace = (int) $this->config->get('no_show_grace_minutes');

        if (now()->lt($booking->scheduled_at->copy()->addMinutes($grace))) {
            throw new DomainException('The provider is still inside the '.$grace.' minute grace period.');
        }

        $this->escrow->refundFull($booking, $customer, 'Provider no-show');
        $booking->status->assertCanTransitionTo(BookingStatus::Refunded);
        $booking->status = BookingStatus::Refunded;
        $booking->cancelled_at = now();
        $booking->cancelled_by = $customer->id;
        $booking->cancellation_reason = 'Provider no-show';
        $booking->save();

        $this->moderation->recordNoShow($booking->provider->user);

        return $booking->fresh();
    }

    public function reportCustomerAbsent(Booking $booking, User $providerUser): Booking
    {
        $this->assertOwnsBooking($booking, $providerUser);

        if ($booking->status !== BookingStatus::Accepted) {
            throw new DomainException('Customer absence can be reported after acceptance and before the job starts.');
        }

        if (now()->lt($booking->scheduled_at)) {
            throw new DomainException('The booked time has not been reached yet.');
        }

        $this->escrow->settleWithCallOutFee($booking, $providerUser, 'Customer absent or no access at the booked time');
        $booking->status->assertCanTransitionTo(BookingStatus::Cancelled);
        $booking->status = BookingStatus::Cancelled;
        $booking->cancelled_at = now();
        $booking->cancelled_by = $providerUser->id;
        $booking->cancellation_reason = 'Customer absent or no access';
        $booking->save();

        $this->notifications->send(
            $booking->customer,
            'customer_absent',
            'Call-out fee charged',
            "You were marked absent for {$booking->reference}. A call-out fee was paid to the provider and the rest was refunded.",
            ['booking_id' => $booking->id],
        );

        return $booking->fresh();
    }

    public function addPhoto(Booking $booking, User $user, PhotoKind $kind, UploadedFile $file): \App\Models\BookingPhoto
    {
        if (! $booking->involves($user)) {
            throw new DomainException('You cannot add photos to this booking.');
        }

        if ($kind === PhotoKind::Evidence && $booking->customer_id !== $user->id && ! $user->isAdmin()) {
            throw new DomainException('Evidence photos are uploaded with a claim.');
        }

        $path = $file->store('booking-photos/'.$booking->id, 'public');

        return $booking->photos()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'path' => $path,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function make(User $customer, array $attributes): Booking
    {
        if (empty($attributes['category_id'])) {
            throw new DomainException('A trade is required to create a booking.');
        }

        $clientAbsent = (bool) $attributes['client_absent'];

        if ($clientAbsent && (empty($attributes['on_site_contact_name']) || empty($attributes['on_site_contact_phone']))) {
            throw new DomainException('A client-absent booking needs an on-site contact name and phone.');
        }

        $booking = Booking::query()->create(array_merge($attributes, [
            'reference' => 'XB-TMP',
            'customer_id' => $customer->id,
            'status' => BookingStatus::Requested,
            'client_absent' => $clientAbsent,
            'entry_pin' => $clientAbsent ? (string) random_int(100000, 999999) : null,
        ]));

        $booking->reference = sprintf('XB-%06d', $booking->id);
        $booking->save();

        return $booking;
    }

    private function assertCustomer(User $user): void
    {
        if ($user->isAdmin()) {
            throw new DomainException('Admin accounts cannot book services.');
        }

        if (! $user->booksAsCustomer()) {
            throw new DomainException('Only customers and business clients can book.');
        }

        if ($user->isBanned()) {
            throw new DomainException('This account is banned.');
        }
    }

    private function assertProviderBookable(User $providerUser): void
    {
        $this->moderation->assertProviderCanWork($providerUser);
    }

    private function assertOwnsBooking(Booking $booking, User $providerUser): void
    {
        $profileId = $providerUser->providerProfile?->id;

        if (! $providerUser->isProvider() || $profileId !== $booking->provider_profile_id) {
            throw new DomainException('This booking belongs to another provider.');
        }
    }

    private function assertCustomerOwns(Booking $booking, User $customer): void
    {
        if ($booking->customer_id !== $customer->id || ! $customer->booksAsCustomer()) {
            throw new DomainException('This booking belongs to another customer.');
        }
    }

    private function assertNoOverlap(Booking $booking): void
    {
        $start = $booking->scheduled_at;
        $end = $start->copy()->addMinutes($booking->duration_minutes);

        $conflict = Booking::query()
            ->where('provider_profile_id', $booking->provider_profile_id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', [BookingStatus::Accepted->value, BookingStatus::InProgress->value])
            ->get()
            ->contains(function (Booking $other) use ($start, $end) {
                $otherEnd = $other->scheduled_at->copy()->addMinutes((int) $other->duration_minutes);

                return $other->scheduled_at < $end && $otherEnd > $start;
            });

        if ($conflict) {
            throw new DomainException('That provider already has a booking at this time.');
        }
    }

    private function assertClientAbsentPhotos(Booking $booking, PhotoKind $kind, string $message): void
    {
        if (! $booking->client_absent) {
            return;
        }

        $exists = $booking->photos()->where('kind', $kind->value)->exists();

        if (! $exists) {
            throw new DomainException($message);
        }
    }

    private function resolveSite(User $customer, mixed $siteId): ?BusinessSite
    {
        if (! $customer->isBusinessClient()) {
            if ($siteId) {
                throw new DomainException('Sites belong to business accounts.');
            }

            return null;
        }

        $business = $customer->business;

        if (! $business) {
            throw new DomainException('Create the business profile before booking.');
        }

        if ($business->sites()->exists() && ! $siteId) {
            throw new DomainException('Choose which site this booking is for.');
        }

        if (! $siteId) {
            return null;
        }

        $site = $business->sites()->whereKey($siteId)->first();

        if (! $site) {
            throw new DomainException('That site is not on your business account.');
        }

        return $site;
    }
}
