<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Holds the tenant context for the current request/process.
 *
 * Registered as a singleton in AppServiceProvider. The global scope
 * (see BelongsToTenant) reads the current tenant from here. When no tenant is
 * set — console commands, seeding, super-admin — queries are NOT scoped, which
 * preserves the app's original single-tenant behaviour.
 */
class TenantManager
{
    private ?Tenant $tenant = null;

    /** Set to true to bypass tenant scoping (e.g. cross-tenant super-admin views). */
    private bool $bypass = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /** Whether the global scope should filter queries right now. */
    public function shouldScope(): bool
    {
        return ! $this->bypass && $this->tenant !== null;
    }

    /** Run a callback with tenant scoping disabled, then restore it. */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypass;
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $previous;
        }
    }
}
