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

        return $next($request);
    }
}
