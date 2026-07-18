<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Etalase rental (storefront). Dipakai di dua konteks:
     *  - subdomain tenant "/" → etalase tenant itu,
     *  - "/demo" pada domain pusat → etalase contoh (tenant default), $demo=true.
     * $demo mengaktifkan banner "ini contoh" dan mengarahkan anchor nav ke /demo.
     */
    public function index(bool $demo = false): View
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

        // Basis anchor nav/footer etalase: di /demo harus tetap di /demo, bukan
        // meloncat ke page induk di "/". Dibaca oleh layouts.public + home.
        $storeBase = $demo ? url('/demo') : url('/');

        return view('home', compact('featured', 'cars', 'types', 'testimonials', 'stats', 'demo', 'storeBase'));
    }
}
