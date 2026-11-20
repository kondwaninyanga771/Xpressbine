<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessSite;
use App\Models\Wallet;

class BusinessWalletTest extends MarketplaceTestCase
{
    public function test_business_client_pays_from_the_wallet_and_refunds_return_to_it(): void
    {
        [$owner, $token] = $this->makeUser(UserRole::BusinessClient, ['name' => 'Chanda Phiri']);
        $business = Business::query()->create(['user_id' => $owner->id, 'name' => 'Kabulonga Academy']);
        Wallet::query()->create(['business_id' => $business->id]);
        $site = BusinessSite::query()->create([
            'business_id' => $business->id,
            'name' => 'Kabulonga Campus',
            'area' => 'Kabulonga',
            'address' => 'Kabulonga Road',
            'latitude' => -15.4195,
            'longitude' => 28.3495,
            'spending_limit' => 5000,
        ]);

        $this->api($token)->postJson('/api/business/wallet/topup', [
            'amount' => 1000,
            'method' => 'mtn_momo',
        ])->assertSuccessful()->assertJsonPath('data.balance', '1000.00');

        [, , $package, $providerToken] = $this->makeProvider();
        $booking = $this->bookPackage($token, $package, ['business_site_id' => $site->id]);

        $this->api($token)->postJson("/api/bookings/{$booking['id']}/pay", [
            'method' => 'mobile_money',
            'mobile_money_provider' => 'mtn_momo',
            'mobile_money_number' => '0961111003',
        ])->assertStatus(422);

        $this->api($token)->postJson("/api/bookings/{$booking['id']}/pay", [
            'method' => 'wallet',
        ])->assertSuccessful()->assertJsonPath('data.escrow_balance', '350.00');

        $this->api($token)->getJson('/api/business')->assertSuccessful()->assertJsonPath('data.wallet.balance', '650.00');

        $this->api($providerToken)->postJson("/api/bookings/{$booking['id']}/cancel", [
            'reason' => 'Cannot make this campus today.',
        ])->assertSuccessful();

        $this->api($token)->getJson('/api/business')->assertJsonPath('data.wallet.balance', '1000.00');
    }

    public function test_site_spending_limit_blocks_a_booking_the_wallet_could_otherwise_pay(): void
    {
        [$owner, $token] = $this->makeUser(UserRole::BusinessClient);
        $business = Business::query()->create(['user_id' => $owner->id, 'name' => 'Small office']);
        Wallet::query()->create(['business_id' => $business->id]);
        $site = BusinessSite::query()->create([
            'business_id' => $business->id,
            'name' => 'Woodlands Campus',
            'area' => 'Woodlands',
            'spending_limit' => 100,
        ]);

        $this->api($token)->postJson('/api/business/wallet/topup', [
            'amount' => 5000,
            'method' => 'bank_transfer',
        ])->assertSuccessful();

        [, , $package] = $this->makeProvider();
        $booking = $this->bookPackage($token, $package, ['business_site_id' => $site->id]);

        $this->api($token)->postJson("/api/bookings/{$booking['id']}/pay", [
            'method' => 'wallet',
        ])->assertStatus(422);
    }
}
