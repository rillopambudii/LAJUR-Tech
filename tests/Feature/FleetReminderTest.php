<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FleetReminderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function makeCar(array $overrides = []): Car
    {
        return Car::create(array_merge([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000,
        ], $overrides));
    }

    public function test_reminder_status_classification(): void
    {
        $overdue = $this->makeCar(['tax_due_date' => Carbon::today()->subDay()->toDateString()]);
        $soon = $this->makeCar(['tax_due_date' => Carbon::today()->addDays(10)->toDateString()]);
        $ok = $this->makeCar(['tax_due_date' => Carbon::today()->addDays(90)->toDateString()]);
        $none = $this->makeCar();

        $this->assertSame('overdue', $overdue->taxStatus());
        $this->assertSame('soon', $soon->taxStatus());
        $this->assertSame('ok', $ok->taxStatus());
        $this->assertNull($none->taxStatus());
    }

    public function test_scope_returns_only_due_or_overdue_cars(): void
    {
        $this->makeCar(['name' => 'Overdue', 'service_due_date' => Carbon::today()->subDays(3)->toDateString()]);
        $this->makeCar(['name' => 'Soon', 'tax_due_date' => Carbon::today()->addDays(5)->toDateString()]);
        $this->makeCar(['name' => 'Far', 'tax_due_date' => Carbon::today()->addDays(120)->toDateString()]);
        $this->makeCar(['name' => 'None']);

        $due = Car::query()->withDueReminders()->pluck('name')->all();

        sort($due);
        $this->assertSame(['Overdue', 'Soon'], $due);
    }

    public function test_dashboard_shows_reminders(): void
    {
        $this->makeCar(['name' => 'PajakLewat', 'tax_due_date' => Carbon::today()->subDay()->toDateString()]);
        $owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/admin')
            ->assertOk()
            ->assertSee('Pengingat Servis')
            ->assertSee('PajakLewat');
    }
}
