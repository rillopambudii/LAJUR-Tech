<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating_punctuality');
            $table->unsignedTinyInteger('rating_cleanliness');
            $table->unsignedTinyInteger('rating_friendliness');
            $table->unsignedTinyInteger('rating_safety');
            $table->decimal('rating_overall', 2, 1);
            $table->text('comment')->nullable();
            $table->string('status')->default('pending'); // pending | published | rejected
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_reviews');
    }
};
