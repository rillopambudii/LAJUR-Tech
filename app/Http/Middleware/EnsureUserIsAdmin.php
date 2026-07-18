<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Ensure the authenticated user is an administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Back-office access = tenant owner or admin. `is_admin` is retained for
        // backward compatibility with accounts created before role management.
        if (! $user || ! ($user->isManager() || $user->is_admin)) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk administrator.');
        }

        // Tenant dengan langganan tak-aktif (trial habis → suspended, pendaftaran
        // berbayar ditinggal → pending_payment, dibatalkan) dikunci dari back-office
        // TAPI diarahkan ke halaman langganan agar bisa membayar — bukan jalan buntu.
        // Halaman langganan sendiri harus lolos supaya tidak terjadi loop.
        $locked = ['pending_payment', 'suspended', 'cancelled'];
        $payRoutes = ['admin.subscription.index', 'admin.subscription.store', 'admin.subscription.finish'];

        if ($user->tenant
            && in_array($user->tenant->subscription_status, $locked, true)
            && ! in_array($request->route()?->getName(), $payRoutes, true)) {
            return redirect()->route('admin.subscription.index')
                ->with('locked', 'Masa langganan Anda berakhir. Pilih paket dan selesaikan pembayaran untuk mengaktifkan kembali akun.');
        }

        return $next($request);
    }
}
