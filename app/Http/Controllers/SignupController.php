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
use Illuminate\Support\Facades\DB;
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
        // Trial berjalan di paket Business penuh: pemakai mencoba SEMUA fitur
        // premium (BBM, AI, dan pratinjau GPS) selama masa coba, lalu setelah
        // trial habis harus berlangganan untuk mempertahankannya. Panjang trial
        // diambil dari data paket Business.
        $trialPlan = Plan::where('key', 'business')->firstOrFail();

        $tenant = Tenant::create([
            'name' => $data['business_name'],
            'slug' => $data['slug'],
            'plan' => 'business',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays($trialPlan->trial_days),
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

        // Tenant baru: sapaan splash sekali di dashboard.
        return redirect()->route('admin.dashboard')->with('greet', 1);
    }

    public function paidForm(string $planKey): View
    {
        $plan = Plan::where('key', $planKey)->firstOrFail();

        return view('signup.form', ['mode' => 'paid', 'plan' => $plan]);
    }

    public function storePaid(SignupRequest $request, string $planKey, SubscriptionCheckout $checkout): RedirectResponse
    {
        $plan = Plan::where('key', $planKey)->firstOrFail();
        $data = $request->validated();

        // Slug and email are unique, so tenant/user rows must not survive a failed
        // checkout — otherwise the visitor can never retry with the same details.
        // Manual transaction control because createCheckout() signals failure by
        // returning null (never throws), which DB::transaction() would still commit.
        DB::beginTransaction();

        try {
            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => $data['slug'],
                'plan' => $plan->key,
                'subscription_status' => 'pending_payment',
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_OWNER,
            ]);

            $url = $checkout->createCheckout($tenant, $plan);
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if (! $url) {
            DB::rollBack();

            return redirect()->route('signup.paid.form', $planKey)
                ->withErrors(['email' => 'Pembayaran sedang tidak tersedia, silakan coba lagi nanti.']);
        }

        DB::commit();

        // Login pemilik sekarang: sesi bertahan lewat perjalanan ke Midtrans dan
        // kembali, jadi di halaman "selesai" mereka sudah masuk → langsung dashboard.
        Auth::login($user);

        return redirect($url);
    }

    public function finish(SubscriptionCheckout $checkout): RedirectResponse|View
    {
        $orderId = (string) request()->query('order_id', '');
        $tenant = $orderId !== ''
            ? Tenant::where('payment_ref', $orderId)->first()
            : null;

        if ($tenant) {
            // Jangan menunggu webhook (tak terjangkau di lokal, balapan di produksi):
            // verifikasi langsung ke Midtrans, lalu aktifkan bila sudah dibayar.
            if ($tenant->subscription_status !== 'active' && $checkout->verifyPaid($orderId)) {
                $checkout->activate($tenant);
            }

            if ($tenant->subscription_status === 'active') {
                $owner = $tenant->users()->where('role', User::ROLE_OWNER)->first();
                if ($owner && ! Auth::check()) {
                    Auth::login($owner);
                }

                // Langsung ke dashboard + sapaan splash.
                return redirect()->route('admin.dashboard')->with('greet', 1);
            }
        }

        return view('signup.finish', ['tenant' => $tenant]);
    }
}
