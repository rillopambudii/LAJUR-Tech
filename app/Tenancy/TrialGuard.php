<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Settles a tenant whose trial period has ended: drops it to the Basic plan
 * and marks the subscription active. No payment flow exists yet, so tenants
 * keep using the app on a reduced plan instead of being locked out.
 */
class TrialGuard
{
    public function settleIfExpired(Tenant $tenant): Tenant
    {
        if ($tenant->subscription_status !== 'trial') {
            return $tenant;
        }

        if (! $tenant->trial_ends_at || $tenant->trial_ends_at->isFuture()) {
            return $tenant;
        }

        $tenant->update(['plan' => 'basic', 'subscription_status' => 'active']);

        return $tenant;
    }

    /** Downgrades a tenant whose PAID subscription period has ended, to the Basic plan. */
    public function settleIfLapsed(Tenant $tenant): Tenant
    {
        if ($tenant->subscription_status !== 'active') {
            return $tenant;
        }

        if (! $tenant->subscription_ends_at || $tenant->subscription_ends_at->isFuture()) {
            return $tenant;
        }

        $tenant->update(['plan' => 'basic']);

        return $tenant;
    }
}
