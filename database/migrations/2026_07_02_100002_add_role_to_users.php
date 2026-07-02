<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // owner / admin / driver / customer
            $table->string('role')->default('customer')->after('password');
            $table->index('role');
        });

        // Migrate the legacy boolean flag: existing admins become tenant owners.
        DB::table('users')->where('is_admin', true)->update(['role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
