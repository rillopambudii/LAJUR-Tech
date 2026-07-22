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

    /**
     * Sapuan menyeluruh: SETIAP rute yang menerima id sumber daya milik tenant
     * harus menolak pemilik tenant lain. Ini yang membuktikan penutupannya utuh,
     * bukan cuma di jenis sumber daya yang kebetulan diuji satu per satu di atas.
     */
    public function test_every_tenant_scoped_binding_route_rejects_foreign_owner(): void
    {
        app(TenantManager::class)->set($this->victim);

        $car = $this->victimCar();
        $booking = $this->victimBooking($car);
        $testimonial = Testimonial::create([
            'name' => 'Korban', 'quote' => 'Bagus', 'rating' => 5, 'is_published' => true,
        ]);
        $message = ContactMessage::create([
            'name' => 'Pengirim', 'email' => 'p@x.id', 'message' => 'RAHASIA',
        ]);
        $fuelLog = \App\Models\FuelLog::create([
            'car_id' => $car->id, 'filled_at' => now(), 'liters' => 30,
            'price_per_liter' => 10000, 'total_cost' => 300000,
        ]);
        $victimDriver = User::create([
            'tenant_id' => $this->victim->id, 'name' => 'Sopir Korban',
            'email' => 'sopir-korban@x.id', 'password' => 'password',
            'role' => User::ROLE_DRIVER, 'is_admin' => false,
        ]);
        $victimStaff = User::create([
            'tenant_id' => $this->victim->id, 'name' => 'Staf Korban',
            'email' => 'staf-korban@x.id', 'password' => 'password',
            'role' => User::ROLE_ADMIN, 'is_admin' => true,
        ]);
        $booking->update(['driver_id' => $victimDriver->id, 'status' => 'completed']);
        $review = \App\Models\DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $victimDriver->id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5,
            'rating_safety' => 5, 'rating_overall' => 5, 'comment' => 'Mantap',
            'status' => 'pending',
        ]);

        $b = $booking->id;
        $routes = [
            ['GET', "/admin/bookings/{$b}"],
            ['DELETE', "/admin/bookings/{$b}"],
            ['PATCH', "/admin/bookings/{$b}/driver"],
            ['POST', "/admin/bookings/{$b}/email"],
            ['GET', "/admin/bookings/{$b}/invoice"],
            ['GET', "/admin/bookings/{$b}/replay"],
            ['PATCH', "/admin/bookings/{$b}/status"],
            ['PATCH', "/admin/bookings/{$b}/trip-status"],
            ['PUT', "/admin/cars/{$car->id}"],
            ['DELETE', "/admin/cars/{$car->id}"],
            ['GET', "/admin/cars/{$car->id}/edit"],
            ['PUT', "/admin/drivers/{$victimDriver->id}"],
            ['DELETE', "/admin/drivers/{$victimDriver->id}"],
            ['GET', "/admin/drivers/{$victimDriver->id}/edit"],
            ['DELETE', "/admin/fuel/{$fuelLog->id}"],
            ['GET', "/admin/messages/{$message->id}"],
            ['DELETE', "/admin/messages/{$message->id}"],
            ['PATCH', "/admin/messages/{$message->id}/toggle"],
            ['PUT', "/admin/staff/{$victimStaff->id}"],
            ['DELETE', "/admin/staff/{$victimStaff->id}"],
            ['GET', "/admin/staff/{$victimStaff->id}/edit"],
            ['PUT', "/admin/testimonials/{$testimonial->id}"],
            ['DELETE', "/admin/testimonials/{$testimonial->id}"],
            ['GET', "/admin/testimonials/{$testimonial->id}/edit"],
            ['PATCH', "/admin/ulasan-driver/{$review->id}/approve"],
            ['PATCH', "/admin/ulasan-driver/{$review->id}/reject"],
            ['PATCH', "/admin/ulasan-driver/{$review->id}/reply"],
        ];

        // Payload VALID untuk rute yang divalidasi FormRequest. Tanpa ini validasi
        // gagal duluan (302 balik dgn error) dan guard tenant di controller tak
        // pernah tereksekusi — ujiannya jadi semu.
        $payloads = [
            "/admin/drivers/{$victimDriver->id}" => [
                'name' => 'Dibajak', 'email' => 'bajak-driver@x.id', 'phone' => '08123456789',
            ],
            "/admin/staff/{$victimStaff->id}" => [
                'name' => 'Dibajak', 'email' => 'bajak-staf@x.id', 'phone' => '08123456789',
            ],
            "/admin/cars/{$car->id}" => [
                'name' => 'Dibajak', 'brand' => 'X', 'type' => 'MPV', 'transmission' => 'Manual',
                'fuel_type' => 'Bensin', 'seats' => 4, 'price_per_day' => 100000,
            ],
            "/admin/testimonials/{$testimonial->id}" => [
                'name' => 'Dibajak', 'quote' => 'Dibajak habis', 'rating' => 1,
            ],
        ];

        $leaked = [];
        foreach ($routes as [$method, $uri]) {
            $res = $this->actingAs($this->attackerOwner)->call($method, $uri, $payloads[$uri] ?? []);
            // 404 = tak ditemukan (benar). 403 = ditolak izin (juga benar).
            if (! in_array($res->getStatusCode(), [403, 404], true)) {
                $leaked[] = "{$method} {$uri} → {$res->getStatusCode()}";
            }
        }

        $this->assertSame([], $leaked, "Rute berikut TIDAK menolak owner tenant asing:\n".implode("\n", $leaked));

        // Pastikan tak ada yang benar-benar terhapus/berubah.
        $this->assertNotNull(Car::withoutGlobalScopes()->find($car->id), 'mobil korban terhapus');
        $this->assertNotNull(Booking::withoutGlobalScopes()->find($booking->id), 'booking korban terhapus');
        $this->assertNotNull(Testimonial::withoutGlobalScopes()->find($testimonial->id), 'testimoni korban terhapus');
        $this->assertNotNull(ContactMessage::withoutGlobalScopes()->find($message->id), 'pesan korban terhapus');
        $this->assertNotNull(\App\Models\FuelLog::withoutGlobalScopes()->find($fuelLog->id), 'catatan BBM korban terhapus');
        $this->assertNotNull(User::find($victimDriver->id), 'driver korban terhapus');
        $this->assertNotNull(User::find($victimStaff->id), 'staf korban terhapus');
        $this->assertSame('Sopir Korban', User::find($victimDriver->id)?->name, 'nama driver korban diubah');
        $this->assertSame('Staf Korban', User::find($victimStaff->id)?->name, 'nama staf korban diubah');
        $this->assertSame('Mobil Korban', Car::withoutGlobalScopes()->find($car->id)?->name, 'nama mobil korban diubah');
        $this->assertSame('pending', \App\Models\DriverReview::withoutGlobalScopes()->find($review->id)?->status, 'status ulasan korban berubah');
    }
}
