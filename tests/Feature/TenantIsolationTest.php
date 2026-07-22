<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\ContactMessage;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolasi antar-tenant pada route-model binding.
 *
 * Ditemukan lewat soak 2026-07-22: `IdentifyTenant` di-append ke grup middleware
 * `web`, sehingga berjalan SESUDAH `SubstituteBindings`. Saat binding {car},
 * {booking}, {testimonial}, {message} di-resolve, TenantManager masih kosong →
 * global scope BelongsToTenant tidak menyaring → owner tenant A bisa membaca,
 * mengubah, bahkan MENGHAPUS data tenant B.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $victim;   // tenant korban
    private Tenant $attacker; // tenant penyerang
    private User $attackerOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->victim = Tenant::where('slug', 'lajur')->firstOrFail();
        $this->attacker = Tenant::create([
            'name' => 'Penyerang', 'slug' => 'penyerang',
            'plan' => 'business', 'subscription_status' => 'active',
        ]);

        $this->attackerOwner = User::create([
            'tenant_id' => $this->attacker->id, 'name' => 'Owner Penyerang',
            'email' => 'penyerang@x.id', 'password' => 'password',
            'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    /** Buat sumber daya milik tenant korban. */
    private function victimCar(): Car
    {
        app(TenantManager::class)->set($this->victim);

        return Car::create([
            'name' => 'Mobil Korban', 'brand' => 'Toyota', 'type' => 'SUV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 500000,
        ]);
    }

    private function victimBooking(Car $car): Booking
    {
        app(TenantManager::class)->set($this->victim);

        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Korban',
            'customer_email' => 'k@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 500000, 'total_price' => 1000000, 'status' => 'confirmed',
        ]);
    }

    public function test_owner_cannot_view_another_tenants_car(): void
    {
        $car = $this->victimCar();

        $this->actingAs($this->attackerOwner)
            ->get("/admin/cars/{$car->id}/edit")
            ->assertNotFound();
    }

    public function test_owner_cannot_delete_another_tenants_car(): void
    {
        $car = $this->victimCar();

        $this->actingAs($this->attackerOwner)->delete("/admin/cars/{$car->id}");

        $this->assertNotNull(
            Car::withoutGlobalScopes()->find($car->id),
            'Mobil tenant lain TERHAPUS oleh owner tenant asing.'
        );
    }

    public function test_owner_cannot_update_another_tenants_car(): void
    {
        $car = $this->victimCar();

        $this->actingAs($this->attackerOwner)->put("/admin/cars/{$car->id}", [
            'name' => 'DIRETAS', 'brand' => 'X', 'type' => 'MPV',
            'transmission' => 'Manual', 'fuel_type' => 'Bensin',
            'seats' => 4, 'price_per_day' => 1,
        ]);

        $this->assertSame(
            'Mobil Korban',
            Car::withoutGlobalScopes()->find($car->id)?->name,
            'Mobil tenant lain BERHASIL DIUBAH oleh owner tenant asing.'
        );
    }

    public function test_owner_cannot_view_another_tenants_booking(): void
    {
        $booking = $this->victimBooking($this->victimCar());

        $this->actingAs($this->attackerOwner)
            ->get("/admin/bookings/{$booking->id}")
            ->assertNotFound();
    }

    public function test_owner_cannot_delete_another_tenants_booking(): void
    {
        $booking = $this->victimBooking($this->victimCar());

        $this->actingAs($this->attackerOwner)->delete("/admin/bookings/{$booking->id}");

        $this->assertNotNull(
            Booking::withoutGlobalScopes()->find($booking->id),
            'Booking tenant lain TERHAPUS oleh owner tenant asing.'
        );
    }

    public function test_owner_cannot_view_or_delete_another_tenants_testimonial(): void
    {
        app(TenantManager::class)->set($this->victim);
        $t = Testimonial::create([
            'name' => 'Korban', 'quote' => 'Bagus sekali', 'rating' => 5, 'is_published' => true,
        ]);

        $this->actingAs($this->attackerOwner)
            ->get("/admin/testimonials/{$t->id}/edit")
            ->assertNotFound();

        $this->actingAs($this->attackerOwner)->delete("/admin/testimonials/{$t->id}");
        $this->assertNotNull(
            Testimonial::withoutGlobalScopes()->find($t->id),
            'Testimoni tenant lain TERHAPUS oleh owner tenant asing.'
        );
    }

    public function test_owner_cannot_read_another_tenants_contact_message(): void
    {
        app(TenantManager::class)->set($this->victim);
        $m = ContactMessage::create([
            'name' => 'Pengirim', 'email' => 'p@x.id', 'message' => 'RAHASIA PELANGGAN',
        ]);

        $this->actingAs($this->attackerOwner)
            ->get("/admin/messages/{$m->id}")
            ->assertNotFound();
    }
}
