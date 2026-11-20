<?php

namespace App\Services;

use App\Enums\JobRequestStatus;
use App\Enums\QuoteStatus;
use App\Exceptions\DomainException;
use App\Models\JobRequest;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class JobRequestService
{
    public function __construct(
        private ModerationService $moderation,
        private SearchService $search,
        private PlatformConfig $config,
        private NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $customer, array $data): JobRequest
    {
        if (! $customer->booksAsCustomer()) {
            throw new DomainException('Only customers and business clients can post a job.');
        }

        if ($customer->isBusinessClient() && $customer->business?->sites()->exists() && empty($data['business_site_id'])) {
            throw new DomainException('Choose which site this job is for.');
        }

        if (! empty($data['business_site_id'])) {
            $owns = $customer->business?->sites()->whereKey($data['business_site_id'])->exists();
            if (! $owns) {
                throw new DomainException('That site is not on your business account.');
            }
        }

        $clientAbsent = (bool) ($data['client_absent'] ?? false);

        if ($clientAbsent && (empty($data['on_site_contact_name']) || empty($data['on_site_contact_phone']))) {
            throw new DomainException('A client-absent job needs an on-site contact name and phone.');
        }

        return JobRequest::query()->create([
            'customer_id' => $customer->id,
            'category_id' => $data['category_id'] ?? null,
            'business_site_id' => $data['business_site_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'],
            'address' => $data['address'],
            'area' => $data['area'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'budget_min' => $data['budget_min'] ?? null,
            'budget_max' => $data['budget_max'] ?? null,
            'preferred_at' => $data['preferred_at'] ?? null,
            'status' => JobRequestStatus::Open,
            'client_absent' => $clientAbsent,
            'on_site_contact_name' => $data['on_site_contact_name'] ?? null,
            'on_site_contact_phone' => $data['on_site_contact_phone'] ?? null,
        ]);
    }

    public function visibleTo(User $user): LengthAwarePaginator
    {
        $query = JobRequest::query()->with(['category', 'quotes.provider.user', 'customer'])->latest();

        if ($user->isAdmin()) {
            return $query->paginate(15);
        }

        if ($user->booksAsCustomer()) {
            return $query->where('customer_id', $user->id)->paginate(15);
        }

        if (! $user->isProvider() || ! $user->providerProfile) {
            return $query->whereRaw('1 = 0')->paginate(15);
        }

        $profile = $user->providerProfile()->with('categories')->first();
        $categoryIds = $profile->categories->pluck('id');

        $query->whereIn('status', [JobRequestStatus::Open->value, JobRequestStatus::Quoted->value])
            ->where(function ($q) use ($categoryIds) {
                $q->whereNull('category_id')->orWhereIn('category_id', $categoryIds);
            });

        if ($user->latitude !== null && $user->longitude !== null && DB::connection()->getDriverName() === 'pgsql') {
            $distance = $this->search->distanceSql('job_requests.latitude', 'job_requests.longitude');
            $radius = $profile->service_radius_km ?: $this->config->get('default_service_radius_km');

            $query->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select('job_requests.*')
                ->selectRaw("{$distance} as distance_km", [$user->latitude, $user->longitude, $user->latitude])
                ->whereRaw("{$distance} <= ?", [$user->latitude, $user->longitude, $user->latitude, $radius]);
        }

        return $query->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function quote(JobRequest $job, User $providerUser, array $data): Quote
    {
        $this->moderation->assertProviderCanWork($providerUser);
        $profile = $providerUser->providerProfile()->with('categories')->first();

        if (! in_array($job->status, [JobRequestStatus::Open, JobRequestStatus::Quoted], true)) {
            throw new DomainException('This job is not open for quotes.');
        }

        if ($job->category_id && ! $profile->categories->contains('id', $job->category_id)) {
            throw new DomainException('This job is outside your trades.');
        }

        if ($job->latitude && $job->longitude && $providerUser->latitude && $providerUser->longitude) {
            $distance = $this->approximateKm(
                (float) $providerUser->latitude,
                (float) $providerUser->longitude,
                (float) $job->latitude,
                (float) $job->longitude,
            );
            $radius = (float) $profile->service_radius_km;

            if ($distance > $radius) {
                throw new DomainException('This job is outside your service area.');
            }
        }

        $quote = Quote::query()->updateOrCreate(
            [
                'job_request_id' => $job->id,
                'provider_profile_id' => $profile->id,
            ],
            [
                'amount' => $data['amount'],
                'message' => $data['message'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? $job->preferred_at,
                'duration_minutes' => $data['duration_minutes'] ?? (int) $this->config->get('default_job_duration_minutes'),
                'status' => QuoteStatus::Pending,
            ],
        );

        if ($job->status === JobRequestStatus::Open) {
            $job->status = JobRequestStatus::Quoted;
            $job->save();
        }

        $this->notifications->send(
            $job->customer,
            'quote_received',
            'New quote',
            "{$providerUser->name} quoted K{$quote->amount} on {$job->title}.",
            ['job_request_id' => $job->id, 'quote_id' => $quote->id],
        );

        return $quote->load('provider.user');
    }

    private function approximateKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
