<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TrialGuard;
use Illuminate\Console\Command;

class CheckTrials extends Command
{
    protected $signature = 'tenants:check-trial';

    protected $description = 'Downgrade tenants whose 14-day trial has ended to the Basic plan.';

    public function handle(TrialGuard $guard): int
    {
        $expired = Tenant::where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expired as $tenant) {
            $guard->settleIfExpired($tenant);
            $this->info("Tenant {$tenant->slug}: trial berakhir, diturunkan ke plan Basic.");
        }

        if ($expired->isEmpty()) {
            $this->info('Tidak ada tenant trial yang kedaluwarsa.');
        }

        $lapsed = Tenant::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->where('plan', '!=', 'basic')
            ->get();

        foreach ($lapsed as $tenant) {
            $guard->settleIfLapsed($tenant);
            $this->info("Tenant {$tenant->slug}: langganan berakhir, diturunkan ke plan Basic.");
        }

        return self::SUCCESS;
    }
}
