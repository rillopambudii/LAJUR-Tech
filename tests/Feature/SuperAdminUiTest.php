<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_plans_page_lists_all_three_plans(): void
    {
        $response = $this->actingAs($this->superAdmin())->get('/superadmin/plans');

        $response->assertOk();
        $response->assertSee('Basic');
        $response->assertSee('Pro');
        $response->assertSee('Business');
    }

    public function test_tenants_page_lists_existing_tenants(): void
    {
        $response = $this->actingAs($this->superAdmin())->get('/superadmin/tenants');

        $response->assertOk();
        $response->assertSee('Lajur — Rental Mobil Premium');
    }
}
