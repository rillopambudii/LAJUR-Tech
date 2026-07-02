<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // used for subdomain / path resolution
            $table->string('plan')->default('free'); // free / pro / enterprise
            $table->string('subscription_status')->default('trial'); // trial / active / suspended / cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->index('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
