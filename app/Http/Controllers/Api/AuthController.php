<?php

namespace App\Http\Controllers\Api;

use App\Enums\MobileMoneyProvider;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Business;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([
                UserRole::Customer->value,
                UserRole::Provider->value,
                UserRole::BusinessClient->value,
            ])],
            'mobile_money_provider' => ['nullable', Rule::enum(MobileMoneyProvider::class)],
            'mobile_money_number' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'business_name' => ['required_if:role,business_client', 'nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'nrc_number' => ['nullable', 'string', 'max:40'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => $data['role'],
                'mobile_money_provider' => $data['mobile_money_provider'] ?? null,
                'mobile_money_number' => $data['mobile_money_number'] ?? null,
                'area' => $data['area'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            if ($user->role === UserRole::Provider) {
                $profile = ProviderProfile::query()->create([
                    'user_id' => $user->id,
                    'bio' => $data['bio'] ?? null,
                    'nrc_number' => $data['nrc_number'] ?? null,
                    'hourly_rate' => $data['hourly_rate'] ?? 0,
                    'service_radius_km' => config('xpressbine.default_service_radius_km'),
                    'verification_status' => VerificationStatus::Pending,
                ]);

                if (! empty($data['category_ids'])) {
                    $profile->categories()->sync($data['category_ids']);
                }
            }

            if ($user->role === UserRole::BusinessClient) {
                $business = Business::query()->create([
                    'user_id' => $user->id,
                    'name' => $data['business_name'],
                ]);
                Wallet::query()->create(['business_id' => $business->id]);
            }

            return $user;
        });

        $user->load(['providerProfile', 'business']);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! password_verify($data['password'], $user->password)) {
            return response()->json(['message' => 'Those credentials do not match.'], 422);
        }

        $user->load(['providerProfile', 'business']);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        $request->user()->load(['providerProfile', 'business']);

        return new UserResource($request->user());
    }
}
