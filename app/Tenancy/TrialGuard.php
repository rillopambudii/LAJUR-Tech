<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Settles a tenant whose trial period has ended. Payment now runs through
 * Midtrans (SubscriptionCheckout), so an expired trial is SUSPENDED — access
 * is locked until the tenant pays. EnsureUserIsAdmin routes a suspended tenant
 * to the subscription page instead of blocking it outright.
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

        // Plan dibiarkan (moot selagi terkunci); saat tenant membayar, webhook
        // menerapkan pending_plan dan mengaktifkan kembali.
        $tenant->update(['subscription_status' => 'suspended']);

        return $tenant;
    }

    /**
     * Mengunci tenant yang langganan BERBAYAR-nya lewat masa aktif. Diperlakukan
     * sama dengan trial habis: suspended sampai membayar lagi — bukan turun ke
     * Basic gratis (Basic adalah paket berbayar, bukan tier gratis).
     */
    public function settleIfLapsed(Tenant $tenant): Tenant
    {
        if ($tenant->subscription_status !== 'active') {
            return $tenant;
        }

        if (! $tenant->subscription_ends_at || $tenant->subscription_ends_at->isFuture()) {
            return $tenant;
        }

        $tenant->update(['subscription_status' => 'suspended']);

        return $tenant;
    }
}
