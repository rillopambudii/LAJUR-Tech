<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TrialGuard;
use Illuminate\Console\Command;

class CheckTrials extends Command
{
    protected $signature = 'tenants:check-trial';

    protected $description = 'Kunci tenant yang trial-nya habis (suspended) dan turunkan langganan berbayar yang lewat masa aktif.';

    public function handle(TrialGuard $guard): int
    {
        $expired = Tenant::where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expired as $tenant) {
            $guard->settleIfExpired($tenant);
            $this->info("Tenant {$tenant->slug}: trial berakhir, akun dikunci sampai pembayaran.");
        }

        if ($expired->isEmpty()) {
            $this->info('Tidak ada tenant trial yang kedaluwarsa.');
        }

        // Tanpa filter plan: pelanggan Basic yang lewat masa aktif juga harus
        // dikunci (Basic paket berbayar). Yang sudah suspended otomatis keluar
        // dari query karena status-nya bukan 'active' lagi.
        $lapsed = Tenant::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        foreach ($lapsed as $tenant) {
            $guard->settleIfLapsed($tenant);
            $this->info("Tenant {$tenant->slug}: langganan berakhir, akun dikunci sampai pembayaran.");
        }

        return self::SUCCESS;
    }
}
