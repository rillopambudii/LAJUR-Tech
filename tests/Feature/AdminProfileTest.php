<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(\App\Tenancy\TenantManager::class)->set($this->tenant);
    }

    private function makeOwner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner Lama', 'email' => 'owner@lajur.id',
            'password' => 'password-lama1', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf Lama', 'email' => 'staf@lajur.id',
            'password' => 'password-lama1', 'role' => User::ROLE_ADMIN, 'is_admin' => true,
        ]);
    }

    public function test_owner_can_view_own_profile(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get('/admin/profil')
            ->assertOk()
            ->assertSee('Owner Lama')
            ->assertSee('owner@lajur.id');
    }

    public function test_admin_can_view_own_profile(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin/profil')->assertOk()->assertSee('Staf Lama');
    }

    public function test_owner_can_update_own_name_phone_email(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->put('/admin/profil', [
            'name' => 'Owner Baru', 'email' => 'owner-baru@lajur.id', 'phone' => '0899999999',
        ])->assertRedirect(route('admin.profile.show'));

        $owner->refresh();
        $this->assertSame('Owner Baru', $owner->name);
        $this->assertSame('owner-baru@lajur.id', $owner->email);
        $this->assertSame('0899999999', $owner->phone);
    }

    public function test_email_must_be_unique_across_users(): void
    {
        $owner = $this->makeOwner();
        $this->makeAdmin(); // pakai staf@lajur.id

        $this->actingAs($owner)->put('/admin/profil', [
            'name' => 'Owner Lama', 'email' => 'staf@lajur.id',
        ])->assertSessionHasErrors('email');
    }

    public function test_owner_can_change_own_password_with_correct_current_password(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->put('/admin/profil/password', [
            'current_password' => 'password-lama1',
            'password' => 'password-baru2', 'password_confirmation' => 'password-baru2',
        ])->assertRedirect(route('admin.profile.show'));

        $this->assertTrue(Hash::check('password-baru2', $owner->fresh()->password));
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->put('/admin/profil/password', [
            'current_password' => 'password-salah',
            'password' => 'password-baru2', 'password_confirmation' => 'password-baru2',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password-lama1', $owner->fresh()->password));
    }
}
