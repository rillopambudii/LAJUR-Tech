<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDriverProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriverWithReview(string $reviewStatus = 'published'): User
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $driver = User::create(['tenant_id' => $tenant->id, 'name' => 'Rahmat Hidayat', 'email' => 'r-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
        DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $driver->id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 4, 'rating_friendliness' => 5,
            'rating_safety' => 4, 'rating_overall' => 4.5, 'comment' => 'Sangat baik',
            'status' => $reviewStatus,
        ]);

        return $driver;
    }

    public function test_public_profile_shows_published_review_masked_name(): void
    {
        $driver = $this->makeDriverWithReview('published');

        $this->get("/pengemudi/{$driver->id}")
            ->assertOk()
            ->assertSee('Rahmat Hidayat')
            ->assertSee('Budi S.')
            ->assertSee('Sangat baik');
    }

    public function test_pending_review_not_shown_on_public_profile(): void
    {
        $driver = $this->makeDriverWithReview('pending');

        $this->get("/pengemudi/{$driver->id}")
            ->assertOk()
            ->assertDontSee('Budi S.')
            ->assertDontSee('Sangat baik');
    }

    public function test_non_driver_user_returns_404(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'o-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true]);

        $this->get("/pengemudi/{$owner->id}")->assertNotFound();
    }

    public function test_driver_from_another_tenant_returns_404(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-tenant']);
        app(TenantManager::class)->set($other);
        $foreignDriver = User::create(['tenant_id' => $other->id, 'name' => 'Driver Asing', 'email' => 'f-'.uniqid().'@other.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);

        // Konteks tenant kembali ke 'lajur' sebelum request — meniru pengunjung yang
        // membuka /pengemudi/{id} dari subdomain tenant lain (bukan tenant milik driver ini).
        $lajur = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($lajur);

        $this->get("/pengemudi/{$foreignDriver->id}")->assertNotFound();
    }
}
