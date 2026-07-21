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

class DriverReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $status = 'completed', bool $withDriver = true): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        $driverId = null;
        if ($withDriver) {
            $driver = User::create([
                'tenant_id' => $tenant->id, 'name' => 'Driver Uji', 'email' => 'drv-'.uniqid().'@lajur.id',
                'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false,
            ]);
            $driverId = $driver->id;
        }
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driverId, 'car_name' => $car->name,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => $status,
            'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_customer_can_submit_driver_review_for_completed_booking(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 5, 'rating_cleanliness' => 4, 'rating_friendliness' => 5,
            'rating_safety' => 4, 'comment' => 'Sopir ramah dan tepat waktu.',
        ])->assertRedirect(route('tracking.show', $booking->booking_code));

        $review = DriverReview::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertSame(4.5, $review->rating_overall);
    }

    public function test_cannot_submit_twice_for_the_same_booking(): void
    {
        $booking = $this->makeBooking();
        $payload = ['rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5];

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", $payload);
        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", $payload);

        $this->assertSame(1, DriverReview::where('booking_id', $booking->id)->count());
    }

    public function test_cannot_submit_for_booking_not_completed(): void
    {
        $booking = $this->makeBooking('confirmed');

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5,
        ]);

        $this->assertSame(0, DriverReview::where('booking_id', $booking->id)->count());
    }

    public function test_cannot_submit_when_booking_has_no_driver(): void
    {
        $booking = $this->makeBooking('completed', withDriver: false);

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5,
        ]);

        $this->assertSame(0, DriverReview::query()->count());
    }

    public function test_invalid_rating_value_is_rejected(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 6, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5,
        ])->assertSessionHasErrors('rating_punctuality');

        $this->assertSame(0, DriverReview::query()->count());
    }
}
