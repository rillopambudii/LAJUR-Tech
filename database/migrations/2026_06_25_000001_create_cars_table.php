<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand');
            $table->string('type'); // SUV, MPV, Sedan, Hatchback, Luxury, Pickup
            $table->string('transmission'); // Automatic / Manual
            $table->string('fuel_type'); // Bensin / Diesel / Listrik / Hybrid
            $table->unsignedTinyInteger('seats')->default(4);
            $table->unsignedBigInteger('price_per_day');
            $table->string('image')->nullable(); // storage path or external URL
            $table->text('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_available']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
