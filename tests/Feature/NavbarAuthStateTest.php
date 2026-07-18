<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarAuthStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_daftar_button_on_public_site(): void
    {
        $response = $this->get('/demo');

        $response->assertOk();
        $response->assertSee('Daftar');
        $response->assertSee(route('signup.pricing'), false);
    }

    public function test_logged_in_owner_sees_dashboard_link_instead_of_daftar(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $response = $this->actingAs($owner)->get('/demo');

        $response->assertOk();
        $response->assertDontSee('Daftar');
        $response->assertSee(route('admin.dashboard'), false);
    }

    public function test_logged_in_driver_dashboard_link_points_to_driver_dashboard(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        $driver = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Sopir', 'email' => 'sopir@lajur.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER,
        ]);

        $response = $this->actingAs($driver)->get('/demo');

        $response->assertOk();
        $response->assertDontSee('Daftar');
        $response->assertSee(route('driver.dashboard'), false);
    }

    public function test_logged_in_super_admin_dashboard_link_points_to_superadmin_panel(): void
    {
        $superAdmin = User::create([
            'name' => 'Platform Owner', 'email' => 'platform@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $response = $this->actingAs($superAdmin)->get('/demo');

        $response->assertOk();
        $response->assertDontSee('Daftar');
        $response->assertSee(route('superadmin.plans.index'), false);
    }
}
