<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(\App\Tenancy\TenantManager::class)->set($this->tenant);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    private function makeAdmin(?int $tenantId = null, string $email = 'staf@lajur.id'): User
    {
        return User::create([
            'tenant_id' => $tenantId ?? $this->tenant->id, 'name' => 'Staf Lama', 'email' => $email,
            'password' => 'password', 'role' => User::ROLE_ADMIN, 'is_admin' => true,
        ]);
    }

    public function test_owner_can_create_staff_admin(): void
    {
        $this->actingAs($this->owner)->post('/admin/staff', [
            'name' => 'Budi Staf', 'email' => 'budi@lajur.id', 'phone' => '0812345678', 'password' => 'secret123',
        ])->assertRedirect('/admin/staff');

        $staff = User::where('email', 'budi@lajur.id')->first();
        $this->assertNotNull($staff);
        $this->assertSame(User::ROLE_ADMIN, $staff->role);
        $this->assertSame($this->tenant->id, $staff->tenant_id);
        $this->assertTrue(Hash::check('secret123', $staff->password));
    }

    public function test_regular_admin_cannot_access_staff_management(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin/staff')->assertForbidden();
        $this->actingAs($admin)->get('/admin/staff/create')->assertForbidden();
        $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Coba', 'email' => 'coba@lajur.id', 'password' => 'secret123',
        ])->assertForbidden();
    }

    public function test_staff_from_another_tenant_cannot_be_edited(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $foreign = $this->makeAdmin($other->id, 'foreign@other.id');

        $this->actingAs($this->owner)->get("/admin/staff/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_owner_can_update_and_delete_staff(): void
    {
        $staff = $this->makeAdmin();

        $this->actingAs($this->owner)->put("/admin/staff/{$staff->id}", [
            'name' => 'Staf Baru', 'email' => 'staf@lajur.id',
        ])->assertRedirect('/admin/staff');
        $this->assertSame('Staf Baru', $staff->fresh()->name);

        $this->actingAs($this->owner)->delete("/admin/staff/{$staff->id}")->assertRedirect('/admin/staff');
        $this->assertNull(User::find($staff->id));
    }

    public function test_staff_admin_can_login_and_access_admin_dashboard(): void
    {
        $staff = $this->makeAdmin();

        $this->actingAs($staff)->get('/admin')->assertOk();
    }
}
