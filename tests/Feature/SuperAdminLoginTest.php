<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_log_in_and_is_redirected_to_the_plans_page(): void
    {
        User::create([
            'name' => 'Platform Owner', 'email' => 'platform@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->post('/login', ['email' => 'platform@lajur.id', 'password' => 'password'])
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertAuthenticated();
    }
}
