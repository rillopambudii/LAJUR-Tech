<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci area yang tak punya alur bayar sendiri (mis. driver) saat langganan
 * tenant mati. Owner/admin dikunci di EnsureUserIsAdmin (dgn arahan ke halaman
 * bayar); driver tak bisa membayar, jadi cukup ditolak dengan pesan jelas.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()?->tenant;

        if ($tenant && $tenant->isLocked()) {
            abort(403, 'Langganan rental Anda sedang tidak aktif. Silakan hubungi pemilik rental.');
        }

        return $next($request);
    }
}
