<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $customerName = 'Budi Santoso'): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        $driver = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Driver Uji', 'email' => 'drv-'.uniqid().'@lajur.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false,
        ]);
        $car = Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => $customerName, 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
        ]);
    }

    public function test_relations_and_masked_name(): void
    {
        $booking = $this->makeBooking('Budi Santoso');

        $review = DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $booking->driver_id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 4, 'rating_friendliness' => 5,
            'rating_safety' => 4, 'rating_overall' => 4.5, 'status' => 'published',
        ]);

        $this->assertSame($booking->id, $review->booking->id);
        $this->assertSame($booking->driver_id, $review->driver->id);
        $this->assertTrue($booking->fresh()->driverReview->is($review));
        $this->assertTrue($review->driver->driverReviews->contains($review));
        $this->assertSame('Budi S.', $review->maskedCustomerName());
    }

    public function test_masked_name_with_single_word(): void
    {
        $booking = $this->makeBooking('Sari');
        $review = DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $booking->driver_id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5,
            'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published',
        ]);

        $this->assertSame('Sari', $review->maskedCustomerName());
    }

    public function test_published_scope_excludes_pending_and_rejected(): void
    {
        $b1 = $this->makeBooking('A');
        $b2 = $this->makeBooking('B');
        DriverReview::create(['booking_id' => $b1->id, 'driver_id' => $b1->driver_id, 'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published']);
        DriverReview::create(['booking_id' => $b2->id, 'driver_id' => $b2->driver_id, 'rating_punctuality' => 3, 'rating_cleanliness' => 3, 'rating_friendliness' => 3, 'rating_safety' => 3, 'rating_overall' => 3.0, 'status' => 'pending']);

        $this->assertSame(1, DriverReview::published()->count());
    }
}
