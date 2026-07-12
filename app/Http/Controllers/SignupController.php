<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Payments\SubscriptionCheckout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SignupController extends Controller
{
    public function pricing(): View
    {
        $plans = Plan::with('features')->orderBy('sort_order')->get();

        return view('signup.pricing', compact('plans'));
    }

    public function trialForm(): View
    {
        return view('signup.form', ['mode' => 'trial', 'plan' => null]);
    }

    public function storeTrial(SignupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $businessPlan = Plan::where('key', 'business')->firstOrFail();

        $tenant = Tenant::create([
            'name' => $data['business_name'],
            'slug' => $data['slug'],
            'plan' => 'business',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays($businessPlan->trial_days),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['owner_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_OWNER,
        ]);

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
