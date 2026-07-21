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

class AdminDriverReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id', 'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true]);
    }

    private function makeReview(string $status = 'pending'): DriverReview
    {
        $driver = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Driver Uji', 'email' => 'd-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);

        return DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $driver->id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5,
            'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => $status,
        ]);
    }

    public function test_owner_can_view_review_list(): void
    {
        $this->makeReview();

        $this->actingAs($this->owner)->get('/admin/ulasan-driver')->assertOk();
    }

    public function test_owner_can_approve_a_pending_review(): void
    {
        $review = $this->makeReview('pending');

        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$review->id}/approve")
            ->assertRedirect();

        $this->assertSame('published', $review->fresh()->status);
    }

    public function test_owner_can_reject_a_review(): void
    {
        $review = $this->makeReview('pending');

        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$review->id}/reject")
            ->assertRedirect();

        $this->assertSame('rejected', $review->fresh()->status);
    }

    public function test_owner_can_reply_to_a_review(): void
    {
        $review = $this->makeReview('published');

        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$review->id}/reply", ['admin_reply' => 'Terima kasih atas ulasannya!'])
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('Terima kasih atas ulasannya!', $review->admin_reply);
        $this->assertNotNull($review->replied_at);
    }

    public function test_admin_cannot_moderate_review_from_another_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-tenant']);
        app(TenantManager::class)->set($other);
        $foreignReview = $this->makeReview('pending'); // dibuat di bawah konteks tenant 'other'

        app(TenantManager::class)->set($this->tenant);
        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$foreignReview->id}/approve")
            ->assertNotFound();
    }
}
