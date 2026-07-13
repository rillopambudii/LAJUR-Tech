<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('tagline')->nullable()->after('display_name');
            $table->string('contact_phone', 40)->nullable()->after('tagline');
            $table->string('contact_address')->nullable()->after('contact_phone');
            $table->string('contact_email')->nullable()->after('contact_address');
            $table->string('logo_path')->nullable()->after('contact_email');
            $table->string('accent_color', 7)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'display_name', 'tagline', 'contact_phone',
                'contact_address', 'contact_email', 'logo_path', 'accent_color',
            ]);
        });
    }
};
