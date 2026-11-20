<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Package;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class MarketplaceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function image(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) file_get_contents(base_path('tests/fixtures/pixel.jpg')),
        );
    }

    protected function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function category(string $slug, int $min = 150, int $max = 600): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace('-', ' ')->title()->toString(),
                'price_min' => $min,
                'price_max' => $max,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: string}
     */
    protected function makeUser(UserRole $role, array $overrides = []): array
    {
        $suffix = str()->lower(str()->random(8));
        $user = User::query()->create(array_merge([
            'name' => 'Test '.$role->value,
            'email' => $role->value.'.'.$suffix.'@example.com',
            'phone' => '096'.random_int(1000000, 9999999),
            'password' => 'password',
            'role' => $role,
            'area' => 'Chilenje',
            'latitude' => -15.4455,
            'longitude' => 28.3345,
            'mobile_money_provider' => 'mtn_momo',
            'mobile_money_number' => '0961111001',
        ], $overrides));

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /**
     * @param  array<string, mixed>  $userOverrides
     * @return array{0: User, 1: ProviderProfile, 2: Package, 3: string}
     */
    protected function makeProvider(string $trade = 'plumbing', array $userOverrides = [], float $price = 350): array
    {
        $category = $this->category($trade);
        [$user, $token] = $this->makeUser(UserRole::Provider, array_merge([
            'area' => 'Chilenje',
            'latitude' => -15.4460,
            'longitude' => 28.3360,
            'mobile_money_provider' => 'airtel_money',
        ], $userOverrides));

        $profile = ProviderProfile::query()->create([
            'user_id' => $user->id,
            'verification_status' => VerificationStatus::Verified,
            'hourly_rate' => 150,
            'service_radius_km' => 15,
            'verified_at' => now(),
            'nrc_number' => '123456/11/1',
        ]);
        $profile->categories()->attach($category->id);

        $package = Package::query()->create([
            'provider_profile_id' => $profile->id,
            'category_id' => $category->id,
            'title' => 'Standard '.$trade,
            'price' => $price,
            'duration_minutes' => 120,
        ]);

        return [$user, $profile, $package, $token];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{id: int, reference: string}
     */
    protected function bookPackage(string $customerToken, Package $package, array $extra = []): array
    {
        $response = $this->api($customerToken)->postJson('/api/bookings', array_merge([
            'package_id' => $package->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'address' => 'House 12, Chilenje South',
            'area' => 'Chilenje',
            'latitude' => -15.4455,
            'longitude' => 28.3345,
            'notes' => 'Kitchen sink is leaking',
        ], $extra));

        $response->assertSuccessful();

        return [
            'id' => (int) $response->json('data.id'),
            'reference' => (string) $response->json('data.reference'),
        ];
    }

    protected function payMomo(string $customerToken, int $bookingId): TestResponse
    {
        return $this->api($customerToken)->postJson("/api/bookings/{$bookingId}/pay", [
            'method' => 'mobile_money',
            'mobile_money_provider' => 'mtn_momo',
            'mobile_money_number' => '0961111001',
        ])->assertSuccessful();
    }

    protected function runJob(string $providerToken, int $bookingId, bool $photos = false, ?string $customerToken = null): void
    {
        if ($photos) {
            $this->api($providerToken)->post("/api/bookings/{$bookingId}/photos", [
                'kind' => 'before',
                'photo' => $this->image('before.jpg'),
            ], ['Accept' => 'application/json'])->assertSuccessful();
        }

        $this->api($providerToken)->postJson("/api/bookings/{$bookingId}/accept")->assertSuccessful();
        $this->api($providerToken)->postJson("/api/bookings/{$bookingId}/start")->assertSuccessful();

        if ($photos) {
            $this->api($providerToken)->post("/api/bookings/{$bookingId}/photos", [
                'kind' => 'after',
                'photo' => $this->image('after.jpg'),
            ], ['Accept' => 'application/json'])->assertSuccessful();
        }

        $this->api($providerToken)->postJson("/api/bookings/{$bookingId}/complete")->assertSuccessful()
            ->assertJsonPath('data.status', 'completed');
    }

    /**
     * @return array{customer: User, customerToken: string, provider: User, providerToken: string, bookingId: int}
     */
    protected function completedPaidBooking(): array
    {
        [$customer, $customerToken] = $this->makeUser(UserRole::Customer, ['name' => 'Natasha Mwila']);
        [$provider, , $package, $providerToken] = $this->makeProvider();
        $booking = $this->bookPackage($customerToken, $package);
        $this->payMomo($customerToken, $booking['id']);
        $this->runJob($providerToken, $booking['id']);

        return [
            'customer' => $customer,
            'customerToken' => $customerToken,
            'provider' => $provider,
            'providerToken' => $providerToken,
            'bookingId' => $booking['id'],
        ];
    }
}
