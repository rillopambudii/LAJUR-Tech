<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\FuelLog;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $car = Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ]);
        Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'days' => 2, 'price_per_day' => 300000, 'total_price' => 600000, 'status' => 'confirmed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
        FuelLog::create([
            'car_id' => $car->id, 'filled_at' => now(), 'liters' => 40,
            'price_per_liter' => 6800, 'total_cost' => 272000, 'full_tank' => true,
        ]);
    }

    public function test_bookings_xlsx_is_valid_spreadsheet_with_data(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/export/bookings/xlsx');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertNotFalse($sheet);
        $this->assertStringContainsString('Budi Santoso', $sheet);
        $this->assertStringContainsString('Penyewa', $sheet); // heading
    }

    public function test_bookings_pdf_downloads(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/export/bookings/pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_every_dataset_exports_xlsx(): void
    {
        foreach (['bookings', 'cars', 'fuel', 'mileage', 'report'] as $dataset) {
            $this->actingAs($this->admin)
                ->get("/admin/export/{$dataset}/xlsx")
                ->assertOk();
        }
    }

    public function test_unknown_dataset_or_format_is_404(): void
    {
        $this->actingAs($this->admin)->get('/admin/export/users/xlsx')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/export/bookings/docx')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/export/bookings/xlsx')->assertRedirect('/login');
    }
}
