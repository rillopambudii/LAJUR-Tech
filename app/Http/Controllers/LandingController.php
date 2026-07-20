<?php

namespace App\Http\Controllers;

use App\Content\LandingCopy;
use App\Models\LandingContent;
use App\Models\Plan;
use App\Tenancy\Domain;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page induk: situs marketing platform Lajur (untuk pemilik rental).
 *
 * "/" pada DOMAIN PUSAT menampilkan page induk ini. Pada SUBDOMAIN tenant, "/"
 * malah menampilkan etalase tenant (dilempar ke HomeController) — keputusan
 * murni dari host, lihat App\Tenancy\Domain.
 */
class LandingController extends Controller
{
    public function index(Request $request, HomeController $home): View
    {
        if (! Domain::isCentral($request->getHost())) {
            return $home->index(); // subdomain tenant → etalase
        }

        // Ringkasan paket untuk teaser harga (decoy) — urut sesuai sort_order.
        $plans = Plan::with('features')->orderBy('sort_order')->get();

        $copy = new LandingCopy(LandingContent::current());

        return view('landing', compact('plans', 'copy'));
    }
}
