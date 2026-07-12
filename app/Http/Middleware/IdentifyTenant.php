<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use App\Tenancy\TrialGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the current request and stores it in the
 * TenantManager, so tenant-scoped models are automatically filtered.
 *
 * Resolution order:
 *   1. Authenticated user's tenant_id (back office).
 *   2. Subdomain slug, e.g. `rentalA.example.com` -> tenant with slug "rentalA".
 *   3. Fallback to the default tenant (slug "lajur") so the existing public
 *      storefront keeps working during the transition to full multi-tenancy.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $manager = app(TenantManager::class);

        $tenant = $this->fromUser($request)
            ?? $this->fromSubdomain($request)
            ?? $this->default();

        if ($tenant) {
            $tenant = app(TrialGuard::class)->settleIfExpired($tenant);
        }

        $manager->set($tenant);

        return $next($request);
    }

    private function fromUser(Request $request): ?Tenant
    {
        $user = $request->user();

        return $user?->tenant_id ? $user->tenant : null;
    }

    private function fromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Needs at least sub.domain.tld to treat the first label as a slug.
        if (count($parts) < 3) {
            return null;
        }

        $slug = $parts[0];

        if (in_array($slug, ['www', 'app', 'admin'], true)) {
            return null;
        }

        return Tenant::where('slug', $slug)->first();
    }

    private function default(): ?Tenant
    {
        return Tenant::where('slug', 'lajur')->first();
    }
}
