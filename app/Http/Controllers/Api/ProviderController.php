<?php

namespace App\Http\Controllers\Api;

use App\Enums\LedgerEntryType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderProfileResource;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Package;
use App\Models\ProviderProfile;
use App\Services\LedgerService;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProviderController extends Controller
{
    public function index(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'sort' => ['nullable', 'in:distance,rating,price'],
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return ProviderProfileResource::collection($search->providers($filters));
    }

    public function show(Request $request, ProviderProfile $provider): ProviderProfileResource
    {
        $provider->load([
            'user',
            'categories',
            'packages' => fn ($q) => $q->where('is_active', true),
            'photos',
        ]);

        if ($request->filled('latitude') && $request->filled('longitude') && $provider->user?->latitude) {
            $distance = app(SearchService::class)->distanceSql();
            $row = ProviderProfile::query()
                ->whereKey($provider->id)
                ->join('users', 'users.id', '=', 'provider_profiles.user_id')
                ->selectRaw("{$distance} as distance_km", [
                    $request->input('latitude'),
                    $request->input('longitude'),
                    $request->input('latitude'),
                ])
                ->first();
            $provider->distance_km = $row?->distance_km;
        }

        return new ProviderProfileResource($provider);
    }

    public function mine(Request $request): ProviderProfileResource
    {
        $profile = $this->profile($request);
        $profile->load(['user', 'categories', 'packages', 'photos']);

        return new ProviderProfileResource($profile);
    }

    public function update(Request $request): ProviderProfileResource
    {
        $profile = $this->profile($request);
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'nrc_number' => ['nullable', 'string', 'max:40'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'service_radius_km' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'area' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'mobile_money_provider' => ['nullable', 'string', 'max:40'],
            'mobile_money_number' => ['nullable', 'string', 'max:20'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $profile->fill(collect($data)->only([
            'bio', 'nrc_number', 'years_experience', 'hourly_rate', 'service_radius_km',
        ])->all());
        $profile->save();

        $request->user()->fill(collect($data)->only([
            'area', 'latitude', 'longitude', 'mobile_money_provider', 'mobile_money_number',
        ])->all());
        $request->user()->save();

        if (array_key_exists('category_ids', $data)) {
            $profile->categories()->sync($data['category_ids']);
        }

        $profile->load(['user', 'categories', 'packages', 'photos']);

        return new ProviderProfileResource($profile);
    }

    public function storePackage(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $data = $request->validate([
            'category_id' => ['required_without:category', 'nullable', 'integer', 'exists:categories,id'],
            'category' => ['required_without:category_id', 'nullable', 'string', 'exists:categories,slug'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:1440'],
        ]);

        $categoryId = $data['category_id'] ?? Category::query()->where('slug', $data['category'])->value('id');

        $package = $profile->packages()->create([
            'category_id' => $categoryId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'duration_minutes' => $data['duration_minutes'] ?? config('xpressbine.default_job_duration_minutes'),
        ]);

        if (! $profile->categories()->where('categories.id', $categoryId)->exists()) {
            $profile->categories()->attach($categoryId);
        }

        return response()->json(['data' => $package], 201);
    }

    public function updatePackage(Request $request, Package $package): JsonResponse
    {
        $profile = $this->profile($request);
        abort_unless($package->provider_profile_id === $profile->id, 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:1'],
            'duration_minutes' => ['sometimes', 'integer', 'min:30', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $package->fill($data);
        $package->save();

        return response()->json(['data' => $package]);
    }

    public function storePhoto(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $data = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:160'],
        ]);

        $path = $data['photo']->store('provider-photos/'.$profile->id, 'public');
        $photo = $profile->photos()->create([
            'path' => $path,
            'caption' => $data['caption'] ?? null,
        ]);

        return response()->json(['data' => $photo], 201);
    }

    public function earnings(Request $request, LedgerService $ledger): JsonResponse
    {
        $user = $request->user();
        $entries = LedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [LedgerEntryType::Payout, LedgerEntryType::CallOutFee])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'currency' => config('xpressbine.currency'),
                'earnings' => $ledger->providerEarnings($user),
                'entries' => $entries,
            ],
        ]);
    }

    private function profile(Request $request): ProviderProfile
    {
        $profile = $request->user()->providerProfile;

        abort_unless($profile, 404, 'Create a provider account first.');

        return $profile;
    }
}
