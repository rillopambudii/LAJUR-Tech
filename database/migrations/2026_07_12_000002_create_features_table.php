<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // gps_tracking | fuel_tracking | export | ai_assistant
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('feature_plan', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->primary(['plan_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_plan');
        Schema::dropIfExists('features');
    }
};
