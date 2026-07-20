<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminLandingContentTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_super_admin_can_view_edit_form(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/superadmin/konten-landing')
            ->assertOk()
            ->assertSee('Kelola seluruh operasional armada'); // placeholder default hero
    }

    public function test_non_super_admin_cannot_view_edit_form(): void
    {
        $tenant = Tenant::create(['name' => 'Owner Co', 'slug' => 'owner-co', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/superadmin/konten-landing')->assertForbidden();
    }

    public function test_non_super_admin_cannot_update_content(): void
    {
        $tenant = Tenant::create(['name' => 'Owner Co', 'slug' => 'owner-co', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)
            ->patch('/superadmin/konten-landing', ['hero_title_lead' => 'Hack'])
            ->assertForbidden();
    }
}
