<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kustomisasi etalase tingkat-tenant: owner/admin bisa atur hero, tentang,
 * keunggulan, kontak WhatsApp, media sosial, efek animasi, splash, visibilitas
 * bagian, dan SEO — dari menu Situs. Semua nullable + default aman: tenant lama
 * tetap tampil seperti sebelumnya (Branding memberi fallback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Hero (halaman depan)
            $table->string('hero_image_path')->nullable();
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle', 500)->nullable();

            // Tentang kami
            $table->string('about_title')->nullable();
            $table->text('about_text')->nullable();

            // Keunggulan ("kenapa kami") — daftar {title, text}
            $table->json('why_us')->nullable();

            // Kontak & sosial
            $table->string('whatsapp', 40)->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();

            // Tampilan & efek
            $table->string('section_effect', 20)->default('fade-up'); // fade-up|fade|zoom|slide|none
            $table->boolean('splash_enabled')->default(true);

            // Bagian yang ditampilkan
            $table->boolean('show_about')->default(true);
            $table->boolean('show_why')->default(true);
            $table->boolean('show_testimonials')->default(true);

            // SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'hero_image_path', 'hero_title', 'hero_subtitle',
                'about_title', 'about_text', 'why_us',
                'whatsapp', 'instagram', 'facebook', 'tiktok',
                'section_effect', 'splash_enabled',
                'show_about', 'show_why', 'show_testimonials',
                'meta_title', 'meta_description',
            ]);
        });
    }
};
