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

    public function finish(TenantManager $manager, SubscriptionCheckout $checkout): View
    {
        $tenant = $manager->current();

        // Jaring pengaman yang sama dengan SignupController::finish: jangan
        // menunggu webhook. Di lokal webhook tak pernah sampai (Midtrans tak
        // bisa menghubungi localhost) dan di produksi bisa balapan dengan
        // kembalinya pengguna ke halaman ini.
        //
        // Dulu hanya alur PENDAFTARAN yang punya pengaman ini; alur
        // perpanjangan/upgrade dari dashboard terlewat — tenant yang sudah
        // membayar tetap terkunci sampai webhook masuk. Terbukti nyata:
        // pembayaran QRIS 19 Jul lunas 10:08, tenant baru aktif 13:56 setelah
        // diperbaiki manual.
        $queryOrder = (string) request()->query('order_id', '');
        $orderId = $queryOrder !== '' ? $queryOrder : (string) ($tenant?->payment_ref ?? '');

        if ($tenant && $orderId !== '' && $orderId === $tenant->payment_ref && $checkout->verifyPaid($orderId)) {
            $checkout->activate($tenant);

            // Kosongkan referensi agar me-refresh halaman ini tidak mengaktifkan
            // ulang (yang berarti menambah 30 hari gratis tiap refresh).
            $tenant->update(['payment_ref' => null]);
            $tenant->refresh();
        }

        return view('admin.subscription.finish', compact('tenant'));
    }
}
