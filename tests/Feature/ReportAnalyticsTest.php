<?php

namespace Tests\Feature;

use App\Analytics\ReportService;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportAnalyticsTest extends TestCase
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

    private function car(string $name = 'Innova'): Car
    {
        return Car::create([
            'name' => $name, 'brand' => 'Toyota', 'type' => 'MPV', 'transmission' => 'Automatic',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 100000,
        ]);
    }

    private function booking(Car $car, string $status, int $total, string $start, string $end): Booking
    {
        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;

        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'A',
            'customer_email' => 'a@x.id', 'customer_phone' => '0811', 'start_date' => $start,
            'end_date' => $end, 'days' => $days, 'price_per_day' => 100000, 'total_price' => $total,
            'status' => $status,
        ]);
    }

    public function test_revenue_only_counts_confirmed_and_completed(): void
    {
        $car = $this->car();
        $this->booking($car, 'confirmed', 500000, '2026-07-10', '2026-07-14');
        $this->booking($car, 'completed', 300000, '2026-07-15', '2026-07-17');
        $this->booking($car, 'pending', 999000, '2026-07-18', '2026-07-19');   // excluded
        $this->booking($car, 'cancelled', 999000, '2026-07-20', '2026-07-21');  // excluded

        $summary = app(ReportService::class)->summary(Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth());

        $this->assertSame(800000, $summary['revenue']);
        $this->assertSame(2, $summary['bookings_revenue']);
        $this->assertSame(4, $summary['bookings_total']);
        $this->assertSame(400000, $summary['avg_value']);
        $this->assertSame(1, $summary['status_breakdown']['pending']);
    }

    public function test_utilization_is_booked_car_days_over_capacity(): void
    {
        $car = $this->car();
        // 5 rental days within a 10-day window, fleet of 1 => 50%.
        $this->booking($car, 'confirmed', 500000, '2026-07-01', '2026-07-05');

        $util = app(ReportService::class)->utilization(
            Carbon::parse('2026-07-01'), Carbon::parse('2026-07-10')
        );

        $this->assertSame(50.0, $util);
    }

    public function test_reports_page_loads(): void
    {
        $car = $this->car();
        $this->booking($car, 'completed', 250000, Carbon::today()->toDateString(), Carbon::today()->addDay()->toDateString());

        $this->actingAs($this->owner)->get('/admin/reports')
            ->assertOk()
            ->assertSee('Okupansi Armada')
            ->assertSee('Mobil Terlaris');
    }

    public function test_csv_export_downloads_rows(): void
    {
        $car = $this->car();
        $this->booking($car, 'confirmed', 500000, Carbon::today()->toDateString(), Carbon::today()->addDays(2)->toDateString());

        $from = Carbon::today()->startOfMonth()->toDateString();
        $to = Carbon::today()->endOfMonth()->toDateString();

        $res = $this->actingAs($this->owner)->get("/admin/reports/export?from={$from}&to={$to}");
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $res->streamedContent();
        $this->assertStringContainsString('Invoice', $body);   // header row
        $this->assertStringContainsString('Innova', $body);    // data row
    }
}
