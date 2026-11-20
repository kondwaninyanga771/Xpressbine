<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DisputeResource;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\ProviderProfileResource;
use App\Models\Appeal;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\LedgerEntry;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\DisputeService;
use App\Services\LedgerService;
use App\Services\ModerationService;
use App\Services\PlatformConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function providers(Request $request): JsonResponse
    {
        $query = ProviderProfile::query()->with(['user', 'categories'])->latest();

        if ($request->filled('status')) {
            $query->where('verification_status', $request->string('status'));
        }

        return ProviderProfileResource::collection($query->paginate(20))->response();
    }

    public function verify(Request $request, ProviderProfile $provider): ProviderProfileResource
    {
        $provider->verification_status = VerificationStatus::Verified;
        $provider->verified_at = now();
        $provider->verified_by = $request->user()->id;
        $provider->rejection_reason = null;
        $provider->save();

        return new ProviderProfileResource($provider->load(['user', 'categories']));
    }

    public function reject(Request $request, ProviderProfile $provider): ProviderProfileResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $provider->verification_status = VerificationStatus::Rejected;
        $provider->rejection_reason = $data['reason'];
        $provider->verified_at = null;
        $provider->save();

        return new ProviderProfileResource($provider->load('user'));
    }

    public function feature(Request $request, ProviderProfile $provider): ProviderProfileResource
    {
        $data = $request->validate(['is_featured' => ['required', 'boolean']]);
        $provider->is_featured = $data['is_featured'];
        $provider->save();

        return new ProviderProfileResource($provider);
    }

    public function disputes(): JsonResponse
    {
        $disputes = Dispute::query()->with(['booking.customer', 'booking.provider.user'])->latest()->paginate(20);

        return DisputeResource::collection($disputes)->response();
    }

    public function resolveDispute(Request $request, Dispute $dispute, DisputeService $disputes): DisputeResource
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['full_refund', 'partial_refund', 'release', 'fix_first'])],
            'amount' => ['required_if:outcome,partial_refund', 'nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $resolved = $disputes->resolve(
            $dispute,
            $request->user(),
            $data['outcome'],
            isset($data['amount']) ? (string) $data['amount'] : null,
            $data['notes'] ?? null,
        );

        return new DisputeResource($resolved);
    }

    public function moderate(Request $request, User $user, ModerationService $moderation): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['warning', 'suspend', 'ban', 'reinstate'])],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'days' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        $record = $moderation->moderate($user, $request->user(), $data['action'], $data['reason'], $data['days'] ?? null);

        return response()->json(['data' => $record]);
    }

    public function appeals(): JsonResponse
    {
        return response()->json([
            'data' => Appeal::query()->with(['user', 'action'])->latest()->paginate(20),
        ]);
    }

    public function decideAppeal(Request $request, Appeal $appeal, ModerationService $moderation): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json([
            'data' => $moderation->decideAppeal($appeal, $request->user(), $data['decision'], $data['notes'] ?? null),
        ]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $query = LedgerEntry::query()->latest('id');

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->integer('booking_id'));
        }

        return LedgerEntryResource::collection($query->paginate(50))->response();
    }

    public function analytics(LedgerService $ledger, PlatformConfig $config): JsonResponse
    {
        $byStatus = Booking::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $popular = Booking::query()
            ->join('categories', 'categories.id', '=', 'bookings.category_id')
            ->selectRaw('categories.slug, categories.name, count(*) as total')
            ->groupBy('categories.slug', 'categories.name')
            ->orderByDesc('total')
            ->get();

        $areas = Booking::query()
            ->selectRaw('area, count(*) as total')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $threshold = $config->get('low_rating_threshold');
        $minJobs = (int) $config->get('low_rating_min_jobs');

        return response()->json([
            'data' => [
                'currency' => $config->get('currency'),
                'bookings_by_status' => $byStatus,
                'popular_categories' => $popular,
                'busiest_areas' => $areas,
                'platform_revenue' => $ledger->platformRevenue(),
                'escrow_held' => $ledger->totalEscrowHeld(),
                'providers_pending' => ProviderProfile::query()->where('verification_status', VerificationStatus::Pending)->count(),
                'providers_verified' => ProviderProfile::query()->where('verification_status', VerificationStatus::Verified)->count(),
                'open_disputes' => Dispute::query()->whereIn('status', ['open', 'provider_responded'])->count(),
                'flagged_customers' => User::query()->whereNotNull('flagged_at')->count(),
                'providers_below_rating_threshold' => ProviderProfile::query()
                    ->where('rating_count', '>=', $minJobs)
                    ->where('rating_average', '<', $threshold)
                    ->count(),
            ],
        ]);
    }

    public function settings(PlatformConfig $config): JsonResponse
    {
        return response()->json(['data' => $config->allEditable()]);
    }

    public function updateSettings(Request $request, PlatformConfig $config): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        return response()->json(['data' => $config->update($data['settings'])]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_min' => ['required', 'numeric', 'min:0'],
            'price_max' => ['required', 'numeric', 'gte:price_min'],
        ]);

        $category = Category::query()->create([
            ...$data,
            'slug' => $data['slug'] ?? Str::slug($data['name']),
        ]);

        return response()->json(['data' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_min' => ['sometimes', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->fill($data);
        $category->save();

        return response()->json(['data' => $category]);
    }

    public function bookings(): JsonResponse
    {
        $counts = [];
        foreach (BookingStatus::cases() as $status) {
            $counts[$status->value] = Booking::query()->where('status', $status)->count();
        }

        return response()->json(['data' => $counts]);
    }
}
