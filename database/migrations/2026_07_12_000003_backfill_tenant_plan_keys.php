<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Legacy tenants.plan values -> the new plans.key values. */
    private array $map = ['free' => 'basic', 'enterprise' => 'business'];

    public function up(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('tenants')->where('plan', $old)->update(['plan' => $new]);
        }

        // The flagship "lajur" tenant is the fully-featured showcase — put it on
        // Business so tracking/fuel/export/AI demos keep working after gating lands.
        DB::table('tenants')->where('slug', 'lajur')->update(['plan' => 'business']);
    }

    public function down(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('tenants')->where('plan', $new)->update(['plan' => $old]);
        }
        // lajur's pre-migration value ('pro') is not distinguishable from a
        // genuine 'pro' tenant at rollback time — left on 'business' intentionally.
    }
};
