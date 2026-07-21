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

class DriverDashboardRatingBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriver(): User
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        return User::create(['tenant_id' => $tenant->id, 'name' => 'Driver Joni', 'email' => 'joni-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
    }

    private function makeCompletedBooking(User $driver, string $carLabel): Booking
    {
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $carLabel,
            'customer_name' => 'Budi', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => now()->subDays(5)->toDateString(), 'end_date' => now()->subDays(3)->toDateString(),
            'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_published_rating_badge_shows_on_past_task_card(): void
    {
        $driver = $this->makeDriver();
        $booking = $this->makeCompletedBooking($driver, 'MobilDiulas');
        DriverReview::create(['booking_id' => $booking->id, 'driver_id' => $driver->id, 'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published']);

        $this->actingAs($driver)->get('/driver')
            ->assertOk()
            ->assertSee('MobilDiulas')
            ->assertSee('5.0');
    }

    public function test_pending_review_does_not_show_badge(): void
    {
        $driver = $this->makeDriver();
        $booking = $this->makeCompletedBooking($driver, 'MobilPending');
        DriverReview::create(['booking_id' => $booking->id, 'driver_id' => $driver->id, 'rating_punctuality' => 3, 'rating_cleanliness' => 3, 'rating_friendliness' => 3, 'rating_safety' => 3, 'rating_overall' => 3.0, 'status' => 'pending']);

        $response = $this->actingAs($driver)->get('/driver');
        $response->assertOk()->assertSee('MobilPending');
        $response->assertDontSee('3.0');
    }

    public function test_profile_page_shows_average_rating(): void
    {
        $driver = $this->makeDriver();
        $b1 = $this->makeCompletedBooking($driver, 'A');
        $b2 = $this->makeCompletedBooking($driver, 'B');
        DriverReview::create(['booking_id' => $b1->id, 'driver_id' => $driver->id, 'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published']);
        DriverReview::create(['booking_id' => $b2->id, 'driver_id' => $driver->id, 'rating_punctuality' => 3, 'rating_cleanliness' => 3, 'rating_friendliness' => 3, 'rating_safety' => 3, 'rating_overall' => 3.0, 'status' => 'published']);

        $this->actingAs($driver)->get('/driver/profil')
            ->assertOk()
            ->assertSee('4.0'); // rata-rata (5.0 + 3.0) / 2
    }
}
