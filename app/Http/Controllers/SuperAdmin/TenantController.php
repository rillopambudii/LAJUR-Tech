<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderByDesc('created_at')->get();
        $plans = Plan::orderBy('sort_order')->get();

        return view('superadmin.tenants.index', compact('tenants', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'alpha_dash'],
        ]);

        $businessPlan = Plan::where('key', 'business')->firstOrFail();

        Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'plan' => 'business',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays($businessPlan->trial_days),
        ]);

        return back()->with('success', 'Tenant baru dibuat dengan trial 14 hari (plan Business).');
    }

    public function updatePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(Plan::pluck('key'))],
        ]);

        $tenant->update([
            'plan' => $data['plan'],
            'subscription_status' => 'active',
        ]);

        return back()->with('success', "Plan tenant {$tenant->name} diubah ke {$data['plan']}.");
    }

    public function updateStatus(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'subscription_status' => ['required', Rule::in(Tenant::STATUSES)],
        ]);

        $tenant->update(['subscription_status' => $data['subscription_status']]);

        return back()->with('success', "Status tenant {$tenant->name} diubah ke {$data['subscription_status']}.");
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        if ($tenant->slug === 'lajur') {
            return back()->with('error', 'Tenant bawaan (lajur) tidak boleh dihapus — platform bergantung padanya.');
        }

        $name = $tenant->name;

        DB::transaction(function () use ($tenant) {
            // Children referencing cars/users first, then cars & users, then leaf tables.
            foreach (['bookings', 'fuel_logs', 'vehicle_positions', 'car_mileage_daily', 'cars', 'users', 'contact_messages', 'testimonials'] as $table) {
                DB::table($table)->where('tenant_id', $tenant->id)->delete();
            }
            $tenant->delete();
        });

        return back()->with('success', "Tenant {$name} beserta seluruh datanya telah dihapus permanen.");
    }
}
