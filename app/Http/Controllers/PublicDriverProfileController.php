<?php

namespace App\Http\Controllers;

use App\Models\DriverReview;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;

class PublicDriverProfileController extends Controller
{
    public function show(User $driver, TenantManager $manager): View
    {
        // User TIDAK punya global tenant scope (lihat App\Models\User) — tanpa guard ini,
        // profil driver dari tenant lain bisa terbuka lewat subdomain tenant manapun.
        abort_unless(
            $driver->role === User::ROLE_DRIVER && $driver->tenant_id === $manager->id(),
            404
        );

        $reviews = DriverReview::published()
            ->where('driver_id', $driver->id)
            ->with('booking')
            ->latest()
            ->paginate(10);

        $completedTrips = $driver->driverBookings()->where('status', 'completed')->count();

        $aggregate = DriverReview::published()->where('driver_id', $driver->id)->selectRaw('
            AVG(rating_overall) as overall,
            AVG(rating_punctuality) as punctuality,
            AVG(rating_cleanliness) as cleanliness,
            AVG(rating_friendliness) as friendliness,
            AVG(rating_safety) as safety
        ')->first();

        return view('driver.public-profile', [
            'driver' => $driver,
            'reviews' => $reviews,
            'completedTrips' => $completedTrips,
            'avgOverall' => $aggregate->overall !== null ? round((float) $aggregate->overall, 1) : null,
            'avgPunctuality' => $aggregate->punctuality !== null ? round((float) $aggregate->punctuality, 1) : null,
            'avgCleanliness' => $aggregate->cleanliness !== null ? round((float) $aggregate->cleanliness, 1) : null,
            'avgFriendliness' => $aggregate->friendliness !== null ? round((float) $aggregate->friendliness, 1) : null,
            'avgSafety' => $aggregate->safety !== null ? round((float) $aggregate->safety, 1) : null,
        ]);
    }
}
