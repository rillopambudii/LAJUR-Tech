<?php

namespace Tests\Feature;

use App\Mail\BookingInvoiceMail;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceNotificationTest extends TestCase
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

    private function makeBooking(string $phone = '081234567'): Booking
    {
        $car = Car::create([
            'name' => 'Innova', 'brand' => 'Toyota', 'type' => 'MPV', 'transmission' => 'Automatic',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 400000,
        ]);

        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Ani',
            'customer_email' => 'ani@example.com', 'customer_phone' => $phone,
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 400000, 'total_price' => 800000, 'status' => 'confirmed',
        ]);
    }

    public function test_invoice_number_format(): void
    {
        $booking = $this->makeBooking();

        $this->assertMatchesRegularExpression(
            '#^INV/LAJUR/\d{4}/\d{4}$#',
            $booking->invoiceNumber()
        );
    }

    public function test_whatsapp_number_normalisation(): void
    {
        $this->assertSame('6281234567', $this->makeBooking('0812-3456-7')->whatsappNumber());
        $this->assertStringStartsWith('https://wa.me/6281234567?text=', $this->makeBooking('081234567')->whatsappUrl('hi'));
    }

    public function test_invoice_page_renders(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->owner)
            ->get("/admin/bookings/{$booking->id}/invoice")
            ->assertOk()
            ->assertSee($booking->invoiceNumber())
            ->assertSee('Ani');
    }

    public function test_email_invoice_is_sent_to_customer(): void
    {
        Mail::fake();
        $booking = $this->makeBooking();

        $this->actingAs($this->owner)
            ->post("/admin/bookings/{$booking->id}/email")
            ->assertRedirect();

        Mail::assertSent(BookingInvoiceMail::class, fn ($mail) => $mail->hasTo('ani@example.com')
            && $mail->booking->is($booking));
    }
}
