<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The default tenant that owns all legacy Lajur data.
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'lajur'],
            [
                'name' => 'Lajur — Rental Mobil Premium',
                'plan' => 'pro',
                'subscription_status' => 'active',
            ]
        );

        // Set tenant context so every model created below is scoped to it.
        app(TenantManager::class)->set($tenant);

        // Initial owner account — credentials MUST be changed after first login.
        User::updateOrCreate(
            ['email' => 'admin@lajur.id'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Administrator Lajur',
                'password' => Hash::make('password'),
                'role' => User::ROLE_OWNER,
                'is_admin' => true,
            ]
        );

        $this->call([
            CarSeeder::class,
            TestimonialSeeder::class,
        ]);
    }
}
