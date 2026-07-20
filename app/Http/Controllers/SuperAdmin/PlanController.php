<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::with('features')->orderBy('sort_order')->get();
        $features = Feature::orderBy('name')->get();

        return view('superadmin.plans.index', compact('plans', 'features'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
            'discount_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'discount_label' => ['nullable', 'string', 'max:40'],
            'trial_days' => ['required', 'integer', 'min:0'],
        ]);

        // Kosongkan diskon = hapus; label tanpa diskon tidak ada artinya.
        $data['discount_price'] = $data['discount_price'] ?? null;
        $data['discount_label'] = $data['discount_price'] !== null ? ($data['discount_label'] ?? null) : null;

        $plan->update($data);

        return back()->with('success', "Plan {$plan->name} diperbarui.");
    }

    public function updateFeatures(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'features' => ['array'],
            'features.*' => ['integer', 'exists:features,id'],
        ]);

        $plan->features()->sync($data['features'] ?? []);

        return back()->with('success', "Fitur plan {$plan->name} diperbarui.");
    }
}
