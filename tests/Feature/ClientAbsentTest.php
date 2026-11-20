<?php

namespace Tests\Feature;

use App\Enums\UserRole;

class ClientAbsentTest extends MarketplaceTestCase
{
    public function test_client_absent_booking_requires_a_contact_pin_and_photos(): void
    {
        [, $customerToken] = $this->makeUser(UserRole::Customer);
        [, , $package, $providerToken] = $this->makeProvider();

        $this->api($customerToken)->postJson('/api/bookings', [
            'package_id' => $package->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'address' => 'House 12, Chilenje South',
            'area' => 'Chilenje',
            'client_absent' => true,
        ])->assertStatus(422);

        $booking = $this->bookPackage($customerToken, $package, [
            'client_absent' => true,
            'on_site_contact_name' => 'Auntie Banda',
            'on_site_contact_phone' => '0973333444',
        ]);

        $show = $this->api($customerToken)->getJson("/api/bookings/{$booking['id']}")->assertSuccessful();
        $show->assertJsonPath('data.client_absent', true);
        $pin = $show->json('data.entry_pin');
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $pin);

        $this->payMomo($customerToken, $booking['id']);
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/accept")->assertSuccessful();
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/start")->assertStatus(422);

        $this->api($providerToken)->post("/api/bookings/{$booking['id']}/photos", [
            'kind' => 'before',
            'photo' => $this->image('before.jpg'),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/start")->assertSuccessful();
        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/complete")->assertStatus(422);

        $this->api($providerToken)->post("/api/bookings/{$booking['id']}/photos", [
            'kind' => 'after',
            'photo' => $this->image('after.jpg'),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/complete")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'completed');

        $providerView = $this->api($providerToken)->getJson("/api/bookings/{$booking['id']}");
        $providerView->assertJsonPath('data.entry_pin', $pin);
        $providerView->assertJsonPath('data.on_site_contact_name', 'Auntie Banda');
    }
}
