<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PaymentTransaction;

class PersistentFeaturesTest extends MarketplaceTestCase
{
    public function test_chat_messages_are_stored_for_both_people(): void
    {
        [$customer, $customerToken] = $this->makeUser(UserRole::Customer);
        [$provider, , , $providerToken] = $this->makeProvider();

        $opened = $this->api($customerToken)->postJson('/api/conversations', [
            'user_id' => $provider->id,
        ])->assertCreated();

        $conversationId = $opened->json('data.id');

        $this->api($customerToken)->postJson("/api/conversations/{$conversationId}/messages", [
            'body' => 'The sink is leaking.',
        ])->assertCreated();

        $this->api($providerToken)->getJson("/api/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'The sink is leaking.')
            ->assertJsonPath('data.conversation.unread', 0);

        $this->api($providerToken)->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.last_message.body', 'The sink is leaking.');
    }

    public function test_mobile_money_payment_is_stored_as_a_transaction(): void
    {
        [$customer, $customerToken] = $this->makeUser(UserRole::Customer);
        [, , $package] = $this->makeProvider();
        $booking = $this->bookPackage($customerToken, $package);
        $this->payMomo($customerToken, $booking['id']);

        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $customer->id,
            'booking_id' => $booking['id'],
            'kind' => 'booking_payment',
            'provider' => 'mtn_momo',
            'status' => 'simulated_success',
        ]);

        $this->api($customerToken)->getJson('/api/payments')
            ->assertOk()
            ->assertJsonPath('data.0.booking_id', $booking['id']);

        $this->assertSame(1, PaymentTransaction::query()->count());
    }

    public function test_business_plan_and_finops_are_saved(): void
    {
        [$owner, $token] = $this->makeUser(UserRole::BusinessClient, ['name' => 'Chanda Phiri']);
        $business = $owner->business()->create(['name' => 'Kabulonga Academy']);
        $business->wallet()->create([]);

        $this->api($token)->postJson('/api/business/plans', [
            'month' => now()->format('Y-m'),
            'amount' => 8000,
        ])->assertCreated();

        $planId = $this->api($token)->getJson('/api/business/plans')->json('data.0.id');

        $this->api($token)->postJson("/api/business/plans/{$planId}/confirm")->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->api($token)->getJson('/api/business/finops')
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'confirmed')
            ->assertJsonPath('data.booking_count', 0);
    }

    public function test_an_estimate_is_saved_for_the_customer(): void
    {
        [, $token] = $this->makeUser(UserRole::Customer);

        $this->api($token)->postJson('/api/ai/estimate', [
            'description' => 'My kitchen sink is leaking badly under the cupboard',
        ])->assertOk()->assertJsonPath('data.trade', 'plumbing');

        $this->api($token)->getJson('/api/estimates')
            ->assertOk()
            ->assertJsonPath('data.0.trade', 'plumbing');
    }
}
