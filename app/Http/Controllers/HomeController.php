<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // Featured & available cars for the highlight; fall back to the
        // latest available cars when no featured car exists (FR-06 / BR-06).
        $featured = Car::query()->available()->featured()->ordered()->take(6)->get();

        if ($featured->isEmpty()) {
            $featured = Car::query()->available()->ordered()->take(6)->get();
        }

        // All available cars for the catalogue + type filter chips (FR-08).
        $cars = Car::query()->available()->ordered()->get();
        $types = $cars->pluck('type')->unique()->values();

        $testimonials = Testimonial::query()->published()->ordered()->take(9)->get();

        $stats = [
            'cars' => Car::query()->available()->count(),
            'types' => $types->count(),
            'happy_customers' => max(Testimonial::query()->published()->count(), 0),
        ];

        return view('home', compact('featured', 'cars', 'types', 'testimonials', 'stats'));
    }
}
