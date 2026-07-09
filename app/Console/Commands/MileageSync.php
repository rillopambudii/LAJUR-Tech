<?php

namespace App\Console\Commands;

use App\Mileage\MileageService;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

class MileageSync extends Command
{
    protected $signature = 'mileage:sync';

    protected $description = 'Recompute per-car daily mileage from GPS positions (all tenants).';

    public function handle(MileageService $service): int
    {
        $manager = app(TenantManager::class);

        foreach (Tenant::all() as $tenant) {
            $manager->set($tenant);
            $n = $service->syncAll();
            $this->info("Tenant {$tenant->slug}: {$n} mobil disinkron.");
        }

        return self::SUCCESS;
    }
}
