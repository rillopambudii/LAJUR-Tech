<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    private function makeDriver(?int $tenantId = null, string $email = 'drv@lajur.id'): User
    {
        return User::create([
            'tenant_id' => $tenantId ?? $this->tenant->id, 'name' => 'Driver Joni', 'email' => $email,
            'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false,
        ]);
    }

    public function test_owner_can_create_a_driver(): void
    {
        $this->actingAs($this->owner)->post('/admin/drivers', [
            'name' => 'Joni', 'email' => 'joni@lajur.id', 'phone' => '0812345678', 'password' => 'secret123',
        ])->assertRedirect('/admin/drivers');

        $driver = User::where('email', 'joni@lajur.id')->first();
        $this->assertNotNull($driver);
        $this->assertSame(User::ROLE_DRIVER, $driver->role);
        $this->assertSame($this->tenant->id, $driver->tenant_id);
        $this->assertTrue(Hash::check('secret123', $driver->password));
    }

    public function test_driver_from_another_tenant_cannot_be_edited(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $foreign = $this->makeDriver($other->id, 'foreign@other.id');

        $this->actingAs($this->owner)->get("/admin/drivers/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_can_assign_and_unassign_driver_on_a_booking(): void
    {
        $car = Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);
        $driver = $this->makeDriver();
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Ani',
            'customer_email' => 'a@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->owner)
            ->patch("/admin/bookings/{$booking->id}/driver", ['driver_id' => $driver->id])
            ->assertRedirect();
        $this->assertSame($driver->id, $booking->fresh()->driver_id);

        // Unassign.
        $this->actingAs($this->owner)
            ->patch("/admin/bookings/{$booking->id}/driver", ['driver_id' => ''])
            ->assertRedirect();
        $this->assertNull($booking->fresh()->driver_id);
    }

    public function test_cannot_assign_driver_from_another_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $foreign = $this->makeDriver($other->id, 'foreign@other.id');
        $car = Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Ani',
            'customer_email' => 'a@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->owner)
            ->patch("/admin/bookings/{$booking->id}/driver", ['driver_id' => $foreign->id])
            ->assertSessionHasErrors('driver_id');
        $this->assertNull($booking->fresh()->driver_id);
    }

    public function test_destination_saves_and_shows_maps_button_on_driver_dashboard(): void
    {
        $driver = $this->makeDriver();
        $car = Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Ani',
            'customer_email' => 'a@x.id', 'customer_phone' => '0811', 'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(), 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->owner)
            ->patch("/admin/bookings/{$booking->id}/driver", [
                'driver_id' => $driver->id,
                'destination' => 'Bandara APT Pranoto, Samarinda',
            ])
            ->assertRedirect();
        $this->assertSame('Bandara APT Pranoto, Samarinda', $booking->fresh()->destination);

        $res = $this->actingAs($driver)->get('/driver');
        $res->assertOk()
            ->assertSee('Bandara APT Pranoto, Samarinda')
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=Bandara+APT+Pranoto%2C+Samarinda', false);

        // Tanpa tujuan: tombol Maps tidak dirender.
        $booking->update(['destination' => null]);
        $this->actingAs($driver)->get('/driver')->assertDontSee('maps/dir');
    }

    public function test_driver_sees_only_their_assignments_on_dashboard(): void
    {
        $driver = $this->makeDriver();
        $car = Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);
        $mine = Booking::create([
            'car_id' => $car->id, 'car_name' => 'Mine', 'driver_id' => $driver->id, 'customer_name' => 'A',
            'customer_email' => 'a@x.id', 'customer_phone' => '1', 'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(), 'days' => 2, 'price_per_day' => 1, 'total_price' => 2,
            'status' => 'confirmed',
        ]);
        Booking::create([
            'car_id' => $car->id, 'car_name' => 'NotMine', 'customer_name' => 'B',
            'customer_email' => 'b@x.id', 'customer_phone' => '2', 'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(), 'days' => 2, 'price_per_day' => 1, 'total_price' => 2,
            'status' => 'confirmed',
        ]);

        $res = $this->actingAs($driver)->get('/driver');
        $res->assertOk()->assertSee('Mine')->assertDontSee('NotMine');
    }

    public function test_owner_can_create_driver_with_avatar_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->post('/admin/drivers', [
            'name' => 'Foto Driver', 'email' => 'foto@lajur.id', 'phone' => '0812345678',
            'password' => 'secret123', 'avatar' => UploadedFile::fake()->image('driver.jpg'),
        ])->assertRedirect('/admin/drivers');

        $driver = User::where('email', 'foto@lajur.id')->firstOrFail();
        $this->assertNotNull($driver->avatar_path);
        Storage::disk('public')->assertExists($driver->avatar_path);
        $this->assertNotNull($driver->avatarUrl());
    }

    public function test_owner_can_replace_and_remove_driver_avatar(): void
    {
        Storage::fake('public');
        $driver = $this->makeDriver();
        $driver->update(['avatar_path' => 'avatars/old.jpg']);
        Storage::disk('public')->put('avatars/old.jpg', 'fake-bytes');

        // Replace: old file deleted, new one stored.
        $this->actingAs($this->owner)->put("/admin/drivers/{$driver->id}", [
            'name' => $driver->name, 'email' => $driver->email,
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ])->assertRedirect();

        $driver->refresh();
        Storage::disk('public')->assertMissing('avatars/old.jpg');
        Storage::disk('public')->assertExists($driver->avatar_path);

        // Remove: checkbox clears it.
        $this->actingAs($this->owner)->put("/admin/drivers/{$driver->id}", [
            'name' => $driver->name, 'email' => $driver->email, 'remove_avatar' => '1',
        ])->assertRedirect();

        $this->assertNull($driver->refresh()->avatar_path);
    }

    public function test_driver_without_photo_gets_initials_placeholder(): void
    {
        $driver = $this->makeDriver();
        $driver->name = 'Budi Santoso';
        $driver->save();

        $this->assertNull($driver->avatarUrl());
        $this->assertSame('BS', $driver->initials());
    }

    public function test_driver_can_view_own_profile_page(): void
    {
        $driver = $this->makeDriver();
        $driver->update(['phone' => '0812999888']);

        $this->actingAs($driver)->get('/driver/profil')
            ->assertOk()
            ->assertSee('Driver Joni')
            ->assertSee('0812999888')
            ->assertSee($driver->email);
    }

    public function test_login_redirects_by_role(): void
    {
        $driver = $this->makeDriver();

        $this->post('/login', ['email' => $driver->email, 'password' => 'password'])
            ->assertRedirect(route('driver.dashboard'));
        $this->post('/logout');

        $this->post('/login', ['email' => $this->owner->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }
}
