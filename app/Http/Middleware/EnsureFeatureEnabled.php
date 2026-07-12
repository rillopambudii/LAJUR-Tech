<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route unless the current tenant's plan includes the given feature.
 * Usage: ->middleware('feature:gps_tracking').
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $tenant = app(TenantManager::class)->current();

        if (! $tenant || ! $tenant->hasFeature($featureKey)) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Fitur ini tidak tersedia di plan Anda saat ini — upgrade untuk mengaktifkan.');
        }

        return $next($request);
    }
}
