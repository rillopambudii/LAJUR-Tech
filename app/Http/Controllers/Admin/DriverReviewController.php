<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverReviewController extends Controller
{
    public function index(): View
    {
        $reviews = DriverReview::query()
            ->with('booking', 'driver')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'published' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(15);

        return view('admin.driver-reviews.index', compact('reviews'));
    }

    public function approve(DriverReview $driverReview): RedirectResponse
    {
        $driverReview->update(['status' => 'published']);

        return back()->with('success', 'Ulasan diterbitkan ke profil driver.');
    }

    public function reject(DriverReview $driverReview): RedirectResponse
    {
        $driverReview->update(['status' => 'rejected']);

        return back()->with('success', 'Ulasan ditolak dan tidak akan tampil publik.');
    }

    public function reply(Request $request, DriverReview $driverReview): RedirectResponse
    {
        $data = $request->validate([
            'admin_reply' => ['required', 'string', 'max:1000'],
        ], [], ['admin_reply' => 'balasan']);

        $driverReview->update(['admin_reply' => $data['admin_reply'], 'replied_at' => now()]);

        return back()->with('success', 'Balasan tersimpan.');
    }
}
