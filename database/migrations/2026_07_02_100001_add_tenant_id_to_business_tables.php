<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables that become tenant-scoped. */
    private array $tables = ['users', 'cars', 'bookings', 'contact_messages', 'testimonials'];

    public function up(): void
    {
        // 1. Add nullable tenant_id to every business table (nullable so existing
        //    rows survive; backfilled below, kept nullable to avoid breaking the
        //    global tenants table / super-admin rows that legitimately have none).
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('tenant_id')->nullable()->after('id')
                        ->constrained('tenants')->nullOnDelete();
                    $t->index('tenant_id');
                });
            }
        }

        // 2. Ensure a default tenant exists for the legacy single-tenant data.
        $tenantId = DB::table('tenants')->where('slug', 'lajur')->value('id');
        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Lajur — Rental Mobil Premium',
                'slug' => 'lajur',
                'plan' => 'pro',
                'subscription_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Backfill all existing rows to the default tenant.
        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('tenant_id');
                });
            }
        }
    }
};
