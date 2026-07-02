<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            // URL gambar eksternal (mis. thumbnail Wikimedia) bisa lebih dari
            // 191 karakter. Kolom ini tidak di-index, jadi aman diperlebar.
            $table->string('image', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('image', 191)->nullable()->change();
        });
    }
};
