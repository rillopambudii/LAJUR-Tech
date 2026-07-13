<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Payments\SubscriptionCheckout;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function index(TenantManager $manager): View
    {
        $tenant = $manager->current();
        $plans = Plan::orderBy('sort_order')->get();

        return view('admin.subscription.index', compact('tenant', 'plans'));
    }

    public function store(string $planKey, TenantManager $manager, SubscriptionCheckout $checkout): RedirectResponse
    {
        $tenant = $manager->current();
        $plan = Plan::where('key', $planKey)->firstOrFail();

        // Explicit begin/commit/rollback (not DB::transaction(), which only
        // rolls back on a thrown exception) — same pattern already proven in
        // SignupController::storePaid() for this exact "checkout may return
        // null" failure mode.
        DB::beginTransaction();

        try {
            $tenant->update(['pending_plan' => $plan->key]);
            $url = $checkout->createCheckout($tenant, $plan, route('admin.subscription.finish'));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (! $url) {
            DB::rollBack();

            return redirect()->route('admin.subscription.index')
                ->with('error', 'Pembayaran sedang tidak tersedia, silakan coba lagi nanti.');
        }

        DB::commit();

        return redirect($url);
    }

    public function finish(TenantManager $manager): View
    {
        $tenant = $manager->current();

        return view('admin.subscription.finish', compact('tenant'));
    }
}
