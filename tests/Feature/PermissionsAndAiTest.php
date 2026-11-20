<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Package;
use App\Models\ProviderProfile;

class PermissionsAndAiTest extends MarketplaceTestCase
{
    public function test_search_hides_unverified_providers_and_private_nrc(): void
    {
        $this->category('plumbing');
        [, $near] = $this->makeProvider('plumbing', [
            'latitude' => -15.4460,
            'longitude' => 28.3360,
        ]);
        [, $far] = $this->makeProvider('plumbing', [
            'name' => 'Far Plumber',
            'area' => 'Ndola',
            'latitude' => -12.9700,
            'longitude' => 28.6400,
        ]);
        [$pendingUser] = $this->makeUser(UserRole::Provider, ['name' => 'Oliver Nkhoma', 'email' => 'oliver.search@example.com']);
        $pending = ProviderProfile::query()->create([
            'user_id' => $pendingUser->id,
            'verification_status' => VerificationStatus::Pending,
            'hourly_rate' => 100,
            'nrc_number' => '999999/11/1',
        ]);
        $categoryId = $near->categories()->first()->id;
        $pending->categories()->attach($categoryId);
        $pendingPackage = Package::query()->create([
            'provider_profile_id' => $pending->id,
            'category_id' => $categoryId,
            'title' => 'Unverified call-out',
            'price' => 200,
            'duration_minutes' => 60,
        ]);

        [, $customerToken] = $this->makeUser(UserRole::Customer);

        $search = $this->api($customerToken)->getJson('/api/providers?category=plumbing&latitude=-15.4455&longitude=28.3345&radius_km=15');
        $search->assertSuccessful();
        $ids = collect($search->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($near->id));
        $this->assertFalse($ids->contains($far->id));
        $this->assertFalse($ids->contains($pending->id));
        $search->assertJsonMissing(['nrc_number' => '123456/11/1']);

        $show = $this->api($customerToken)->getJson('/api/providers/'.$near->id);
        $show->assertSuccessful();
        $this->assertArrayNotHasKey('nrc_number', $show->json('data'));

        [, $adminToken] = $this->makeUser(UserRole::Admin);
        $this->api($adminToken)->getJson('/api/providers/'.$near->id)
            ->assertJsonPath('data.nrc_number', '123456/11/1');

        $this->api($customerToken)->postJson('/api/bookings', [
            'package_id' => $pendingPackage->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'address' => 'Somewhere',
            'area' => 'Roma',
        ])->assertStatus(422);
    }

    public function test_roles_cannot_cross_into_each_others_tools(): void
    {
        $job = $this->completedPaidBooking();
        [, $adminToken] = $this->makeUser(UserRole::Admin);
        [, $customerToken] = $this->makeUser(UserRole::Customer);

        $this->api($customerToken)->getJson('/api/admin/analytics')->assertForbidden();
        $this->api($job['providerToken'])->postJson("/api/bookings/{$job['bookingId']}/confirm")->assertForbidden();
        $this->api($adminToken)->postJson('/api/bookings', [
            'package_id' => 1,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'address' => 'Admin house',
            'area' => 'Northmead',
        ])->assertForbidden();

        $estimate = $this->api($job['customerToken'])->postJson('/api/ai/estimate', [
            'description' => 'My kitchen sink is leaking badly under the cupboard',
        ]);
        $estimate->assertSuccessful()
            ->assertJsonPath('data.trade', 'plumbing')
            ->assertJsonPath('data.urgency', 'high')
            ->assertJsonPath('data.driver', 'mock')
            ->assertJsonPath('data.currency', 'ZMW');
        $this->assertGreaterThan(0, (float) $estimate->json('data.price_min'));
        $this->assertGreaterThan((float) $estimate->json('data.price_min'), (float) $estimate->json('data.price_max'));
    }

    public function test_posted_job_can_be_quoted_and_accepted_into_a_booking(): void
    {
        [, $customerToken] = $this->makeUser(UserRole::Customer);
        $category = $this->category('electrical', 180, 700);
        [, , , $providerToken] = $this->makeProvider('electrical', [
            'area' => 'Matero',
            'latitude' => -15.3830,
            'longitude' => 28.2670,
        ], 280);

        $job = $this->api($customerToken)->postJson('/api/job-requests', [
            'category' => 'electrical',
            'title' => 'Socket not working',
            'description' => 'The kitchen socket stopped working after a storm.',
            'address' => 'Plot 4, Matero',
            'area' => 'Matero',
            'latitude' => -15.3840,
            'longitude' => 28.2680,
            'budget_max' => 400,
        ])->assertSuccessful();

        $jobId = $job->json('data.id');
        $this->assertSame($category->id, $job->json('data.category_id'));

        $quote = $this->api($providerToken)->postJson("/api/job-requests/{$jobId}/quotes", [
            'amount' => 280,
            'message' => 'I can replace the socket tomorrow morning.',
            'scheduled_at' => now()->addDays(1)->toIso8601String(),
        ])->assertSuccessful();

        $quoteId = $quote->json('data.id');
        $this->api($customerToken)->postJson("/api/quotes/{$quoteId}/accept")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.amount', '280.00');
    }
}
