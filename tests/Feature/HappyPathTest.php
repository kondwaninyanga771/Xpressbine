<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Package;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_search_book_pay_complete_release_and_rate(): void
    {
        $category = Category::query()->create([
            'name' => 'Plumbing',
            'slug' => 'plumbing',
            'price_min' => 150,
            'price_max' => 600,
        ]);

        $provider = User::query()->create([
            'name' => 'Grace Tembo',
            'email' => 'grace.tembo@example.com',
            'phone' => '0972222001',
            'password' => 'password',
            'role' => UserRole::Provider,
            'area' => 'Chilenje',
            'latitude' => -15.4460,
            'longitude' => 28.3360,
            'mobile_money_provider' => 'airtel_money',
            'mobile_money_number' => '0972222001',
        ]);

        $profile = ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'verification_status' => VerificationStatus::Verified,
            'hourly_rate' => 150,
            'service_radius_km' => 15,
            'verified_at' => now(),
        ]);
        $profile->categories()->attach($category->id);

        $package = Package::query()->create([
            'provider_profile_id' => $profile->id,
            'category_id' => $category->id,
            'title' => 'Sink and tap repair',
            'price' => 350,
            'duration_minutes' => 120,
        ]);

        $register = $this->postJson('/api/register', [
            'name' => 'Natasha Mwila',
            'email' => 'natasha.mwila@example.com',
            'phone' => '0961111001',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'customer',
            'area' => 'Chilenje',
            'mobile_money_provider' => 'mtn_momo',
            'mobile_money_number' => '0961111001',
        ]);

        $register->assertCreated();
        $customerToken = $register->json('token');

        $login = $this->postJson('/api/login', [
            'email' => 'natasha.mwila@example.com',
            'password' => 'password',
        ]);
        $login->assertOk()->assertJsonPath('user.role', 'customer');

        $search = $this->withToken($customerToken)->getJson('/api/providers?category=plumbing&latitude=-15.4455&longitude=28.3345&radius_km=15');
        $search->assertOk();
        $search->assertJsonPath('data.0.id', $profile->id);
        $search->assertJsonPath('data.0.packages.0.id', $package->id);
        $search->assertJsonMissing(['nrc_number' => 'secret']);

        $booking = $this->withToken($customerToken)->postJson('/api/bookings', [
            'package_id' => $package->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'address' => 'House 12, Chilenje South',
            'area' => 'Chilenje',
            'latitude' => -15.4455,
            'longitude' => 28.3345,
            'notes' => 'Kitchen sink is leaking',
        ]);
        $booking->assertSuccessful()->assertJsonPath('data.status', 'requested');
        $bookingId = $booking->json('data.id');

        $this->withToken($customerToken)->postJson("/api/bookings/{$bookingId}/pay", [
            'method' => 'mobile_money',
            'mobile_money_provider' => 'mtn_momo',
            'mobile_money_number' => '0961111001',
        ])->assertSuccessful()->assertJsonPath('data.escrow_balance', '350.00');

        $providerLogin = $this->postJson('/api/login', [
            'email' => 'grace.tembo@example.com',
            'password' => 'password',
        ])->assertOk();
        $providerToken = $providerLogin->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($providerToken)->postJson("/api/bookings/{$bookingId}/accept")->assertSuccessful()->assertJsonPath('data.status', 'accepted');
        $this->app['auth']->forgetGuards();
        $this->withToken($providerToken)->postJson("/api/bookings/{$bookingId}/start")->assertSuccessful()->assertJsonPath('data.status', 'in_progress');
        $this->app['auth']->forgetGuards();
        $this->withToken($providerToken)->postJson("/api/bookings/{$bookingId}/complete")->assertSuccessful()->assertJsonPath('data.status', 'completed');

        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)->postJson("/api/bookings/{$bookingId}/confirm")
            ->assertSuccessful()
            ->assertJsonPath('data.escrow_balance', '0.00')
            ->assertJsonPath('data.payout_amount', '308.00')
            ->assertJsonPath('data.commission_amount', '42.00');

        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)->postJson("/api/bookings/{$bookingId}/ratings", [
            'stars' => 5,
            'comment' => 'Arrived on time and fixed the leak.',
        ])->assertSuccessful()->assertJsonPath('data.stars', 5);

        $this->app['auth']->forgetGuards();
        $this->withToken($providerToken)->postJson("/api/bookings/{$bookingId}/ratings", [
            'stars' => 5,
            'comment' => 'Clear instructions and easy access.',
        ])->assertSuccessful();

        $this->app['auth']->forgetGuards();
        $ledger = $this->withToken($customerToken)->getJson("/api/bookings/{$bookingId}/ledger");
        $ledger->assertOk();
        $types = collect($ledger->json('data'))->pluck('type')->all();
        $this->assertEqualsCanonicalizing(
            ['payment', 'hold', 'release', 'commission', 'payout'],
            $types,
        );

        $profile->refresh();
        $this->assertSame('5.00', (string) $profile->rating_average);
        $this->assertSame(1, $profile->rating_count);
    }
}
