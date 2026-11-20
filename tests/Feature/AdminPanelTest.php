<?php

namespace Tests\Feature;

use App\Enums\UserRole;

class AdminPanelTest extends MarketplaceTestCase
{
    public function test_only_admins_can_open_the_filament_panel(): void
    {
        $this->get('/admin/login')->assertOk();

        [$admin] = $this->makeUser(UserRole::Admin, ['name' => 'Mutale Zulu']);
        [$customer] = $this->makeUser(UserRole::Customer);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/provider-profiles')->assertOk();
        $this->actingAs($admin)->get('/admin/disputes')->assertOk();
        $this->actingAs($admin)->get('/admin/bookings')->assertOk();
        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }
}
