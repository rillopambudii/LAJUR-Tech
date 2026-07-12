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

        // Block tenants whose subscription is not usable (e.g. paid signup abandoned
        // before completing Midtrans payment). Super admins have no tenant.
        if ($user->tenant && in_array($user->tenant->subscription_status, ['pending_payment', 'suspended', 'cancelled'], true)) {
            abort(403, 'Langganan belum aktif. Selesaikan pembayaran terlebih dahulu.');
        }

        return $next($request);
    }
}
