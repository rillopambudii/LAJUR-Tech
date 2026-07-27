<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Payments\SubscriptionCheckout;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regresi untuk temuan soak 2026-07-27: integritas booking, lockout langganan,
 * gerbang fitur export, siklus langganan. Satu berkas agar mudah dijalankan
 * ulang tiap soak berikutnya.
 */
class SoakHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        $this->tenant->update(['plan' => 'business', 'subscription_status' => 'active']);
        app(TenantManager::class)->set($this->tenant);
    }

    private function car(array $o = []): Car
    {
        return Car::create(array_merge([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ], $o));
    }

    private function book(Car $car, string $start, string $end, string $status = 'confirmed', array $o = []): Booking
    {
        return Booking::create(array_merge([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '0811',
            'start_date' => $start, 'end_date' => $end, 'days' => 1,
            'price_per_day' => 300000, 'total_price' => 300000, 'status' => $status,
        ], $o));
    }

    private function owner(?Tenant $t = null): User
    {
        $t ??= $this->tenant;

        return User::create([
            'tenant_id' => $t->id, 'name' => 'Owner', 'email' => 'owner'.$t->id.'@x.id',
            'password' => 'secret', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    // ---- Booking duration cap ------------------------------------------------

    public function test_public_booking_rejects_excessive_duration(): void
    {
        $car = $this->car();

        $res = $this->from('/')->post('/booking', [
            'car_id' => $car->id, 'customer_name' => 'Ani', 'customer_email' => 'ani@x.id',
            'customer_phone' => '081234567',
            'start_date' => Carbon::today()->addDay()->toDateString(),
            'end_date' => Carbon::today()->addDays(200)->toDateString(),
        ]);

        $res->assertSessionHasErrors(['end_date'], null, 'booking');
        $this->assertSame(0, Booking::count());
    }

    // ---- Demo storefront must not create real orders ------------------------

    public function test_demo_storefront_booking_creates_no_real_order(): void
    {
        $car = $this->car();

        $res = $this->from('/demo')->post('/booking', [
            'demo' => '1',
            'car_id' => $car->id, 'customer_name' => 'Pengunjung', 'customer_email' => 'v@x.id',
            'customer_phone' => '081234567',
            'start_date' => Carbon::today()->addDay()->toDateString(),
            'end_date' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $res->assertSessionHas('booking_success'); // alur ditampilkan…
        $this->assertSame(0, Booking::count());     // …tapi tak ada booking nyata
    }

    // ---- Suspended tenant storefront ----------------------------------------

    public function test_suspended_tenant_storefront_rejects_booking(): void
    {
        $this->tenant->update(['subscription_status' => 'suspended']);
        $car = $this->car();

        $res = $this->from('/')->post('/booking', [
            'car_id' => $car->id, 'customer_name' => 'Ani', 'customer_email' => 'ani@x.id',
            'customer_phone' => '081234567',
            'start_date' => Carbon::today()->addDay()->toDateString(),
            'end_date' => Carbon::today()->addDays(2)->toDateString(),
        ]);

        $res->assertSessionHasErrors(['car_id'], null, 'booking');
        $this->assertSame(0, Booking::count());
    }

    // ---- Admin status change re-checks availability -------------------------

    public function test_admin_cannot_uncancel_into_an_occupied_slot(): void
    {
        $car = $this->car();
        $a = $this->book($car, '2026-08-10', '2026-08-15', 'cancelled');
        $this->book($car, '2026-08-12', '2026-08-14', 'confirmed'); // now occupies the slot

        $this->actingAs($this->owner())
            ->patch("/admin/bookings/{$a->id}/status", ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $a->fresh()->status);
    }

    // ---- Driver double-assignment -------------------------------------------

    public function test_driver_cannot_be_assigned_to_overlapping_trips(): void
    {
        $owner = $this->owner();
        $driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sopir', 'email' => 'd@x.id',
            'password' => 'secret', 'role' => User::ROLE_DRIVER,
        ]);
        $b1 = $this->book($this->car(['name' => 'Car A']), '2026-08-10', '2026-08-15', 'confirmed');
        $b2 = $this->book($this->car(['name' => 'Car B']), '2026-08-12', '2026-08-14', 'confirmed');

        $this->actingAs($owner)->patch("/admin/bookings/{$b1->id}/driver", ['driver_id' => $driver->id]);
        $this->actingAs($owner)->patch("/admin/bookings/{$b2->id}/driver", ['driver_id' => $driver->id])
            ->assertSessionHasErrors('driver_id');

        $this->assertSame($driver->id, $b1->fresh()->driver_id);
        $this->assertNull($b2->fresh()->driver_id);
    }

    // ---- Trip status blocked on cancelled -----------------------------------

    public function test_trip_status_cannot_change_on_cancelled_booking(): void
    {
        $b = $this->book($this->car(), '2026-08-10', '2026-08-15', 'cancelled', ['trip_status' => Booking::TRIP_NOT_STARTED]);

        $this->actingAs($this->owner())
            ->patch("/admin/bookings/{$b->id}/trip-status", ['trip_status' => Booking::TRIP_COMPLETED])
            ->assertSessionHasErrors('trip_status');

        $this->assertSame(Booking::TRIP_NOT_STARTED, $b->fresh()->trip_status);
    }

    // ---- Driver lockout on suspended tenant ---------------------------------

    public function test_suspended_tenant_driver_is_locked_out(): void
    {
        $this->tenant->update(['subscription_status' => 'suspended']);
        $driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sopir', 'email' => 'd2@x.id',
            'password' => 'secret', 'role' => User::ROLE_DRIVER,
        ]);

        $this->actingAs($driver)->get('/driver')->assertForbidden();
    }

    public function test_active_tenant_driver_still_allowed(): void
    {
        $driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sopir', 'email' => 'd3@x.id',
            'password' => 'secret', 'role' => User::ROLE_DRIVER,
        ]);

        $this->actingAs($driver)->get('/driver')->assertOk();
    }

    // ---- Export feature gate ------------------------------------------------

    public function test_export_fuel_blocked_without_fuel_feature(): void
    {
        // Pro punya export tapi TIDAK punya fuel_tracking/gps_tracking.
        $this->tenant->update(['plan' => 'pro']);
        $owner = $this->owner();

        $this->actingAs($owner)->get('/admin/export/fuel/xlsx')->assertForbidden();
        $this->actingAs($owner)->get('/admin/export/mileage/xlsx')->assertForbidden();
        $this->actingAs($owner)->get('/admin/export/bookings/xlsx')->assertOk();
    }

    public function test_export_fuel_allowed_with_business_plan(): void
    {
        $this->tenant->update(['plan' => 'business']);

        $this->actingAs($this->owner())->get('/admin/export/fuel/xlsx')->assertOk();
    }

    // ---- Payment webhook releases car on expiry -----------------------------

    public function test_expired_payment_cancels_pending_booking(): void
    {
        config()->set('services.payment.gateway', 'midtrans');
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');

        $b = $this->book($this->car(), '2026-09-01', '2026-09-03', 'pending', [
            'payment_ref' => 'LAJUR-55-1700000000', 'payment_status' => 'pending',
        ]);

        $order = 'LAJUR-55-1700000000';
        $payload = [
            'order_id' => $order, 'status_code' => '407', 'gross_amount' => '300000.00',
            'transaction_status' => 'expire', 'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $order.'407'.'300000.00'.'SB-Mid-server-TEST'),
        ];

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $b->refresh();
        $this->assertSame('expired', $b->payment_status);
        $this->assertSame('cancelled', $b->status); // car released
    }

    // ---- Auto-cancel booking online terbengkalai -----------------------------

    /** Booking online berumur $hoursOld jam yang belum dibayar. */
    private function abandoned(string $ref = 'LAJUR-77-1700000000', int $hoursOld = 25): Booking
    {
        $b = $this->book($this->car(), '2026-09-01', '2026-09-03', 'pending', [
            'payment_ref' => $ref, 'payment_status' => 'pending',
            'booking_code' => Booking::generateBookingCode(),
        ]);

        $b->forceFill(['created_at' => now()->subHours($hoursOld)])->save();

        return $b;
    }

    private function fakeMidtransStatus(string $transactionStatus): void
    {
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        Http::fake(['*/v2/*/status' => Http::response([
            'transaction_status' => $transactionStatus, 'fraud_status' => 'accept',
        ])]);
    }

    public function test_abandoned_online_booking_is_cancelled(): void
    {
        $b = $this->abandoned();
        $this->fakeMidtransStatus('expire');

        $this->artisan('bookings:cancel-abandoned')->assertSuccessful();

        $b->refresh();
        $this->assertSame('cancelled', $b->status); // mobil kembali bisa dipesan
        $this->assertSame('expired', $b->payment_status);
    }

    public function test_manual_and_fresh_bookings_are_left_alone(): void
    {
        $this->fakeMidtransStatus('expire');

        // Offline/manual: menunggu konfirmasi admin, bukan menunggu pembayaran.
        $manual = $this->book($this->car(), '2026-09-05', '2026-09-06', 'pending');
        $manual->forceFill(['created_at' => now()->subDays(5)])->save();

        $fresh = $this->abandoned('LAJUR-78-1700000000', hoursOld: 2);

        $this->artisan('bookings:cancel-abandoned')->assertSuccessful();

        $this->assertSame('pending', $manual->refresh()->status);
        $this->assertSame('pending', $fresh->refresh()->status);
    }

    public function test_booking_paid_without_webhook_is_confirmed_not_cancelled(): void
    {
        $b = $this->abandoned();
        $this->fakeMidtransStatus('settlement');

        $this->artisan('bookings:cancel-abandoned')->assertSuccessful();

        $b->refresh();
        $this->assertSame('confirmed', $b->status);
        $this->assertSame('paid', $b->payment_status);
    }

    public function test_unreachable_gateway_never_cancels(): void
    {
        $b = $this->abandoned();
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        Http::fake(['*/v2/*/status' => Http::response('', 500)]);

        $this->artisan('bookings:cancel-abandoned')->assertSuccessful();

        // Tak bisa diverifikasi = jangan menebak; coba lagi jadwal berikutnya.
        $this->assertSame('pending', $b->refresh()->status);
    }

    // ---- Subscription lifecycle ---------------------------------------------

    public function test_early_renewal_extends_from_remaining_time(): void
    {
        $this->tenant->update([
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addDays(20),
        ]);

        app(SubscriptionCheckout::class)->activate($this->tenant->fresh());

        // 20 hari tersisa + 30 hari = ~50 hari, bukan reset ke 30.
        $this->assertEqualsWithDelta(50, now()->diffInDays($this->tenant->fresh()->subscription_ends_at), 1);
    }

    public function test_superadmin_plan_change_clears_pending_plan_and_sets_expiry(): void
    {
        $super = User::create([
            'tenant_id' => null, 'name' => 'Super', 'email' => 'super@x.id',
            'password' => 'secret', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $this->tenant->update(['plan' => 'business', 'pending_plan' => 'basic']);

        $this->actingAs($super)->patch("/superadmin/tenants/{$this->tenant->id}/plan", ['plan' => 'business'])
            ->assertRedirect();

        $fresh = $this->tenant->fresh();
        $this->assertNull($fresh->pending_plan);
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertSame('business', $fresh->plan);
    }
}
