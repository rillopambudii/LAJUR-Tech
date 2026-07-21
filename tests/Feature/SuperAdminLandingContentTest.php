<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminLandingContentTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_super_admin_can_view_edit_form(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/superadmin/konten-landing')
            ->assertOk()
            ->assertSee('Kelola seluruh operasional armada'); // placeholder default hero
    }

    public function test_non_super_admin_cannot_view_edit_form(): void
    {
        $tenant = Tenant::create(['name' => 'Owner Co', 'slug' => 'owner-co', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/superadmin/konten-landing')->assertForbidden();
    }

    public function test_non_super_admin_cannot_update_content(): void
    {
        $tenant = Tenant::create(['name' => 'Owner Co', 'slug' => 'owner-co', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)
            ->patch('/superadmin/konten-landing', ['hero_title_lead' => 'Hack'])
            ->assertForbidden();
    }

    public function test_super_admin_can_update_hero_and_it_reflects_on_landing_page(): void
    {
        $this->actingAs($this->superAdmin())
            ->patch('/superadmin/konten-landing', [
                'hero_title_lead' => 'Judul Kustom Owner',
                'hero_title_reveal' => 'baris kedua kustom.',
            ])
            ->assertRedirect(route('superadmin.landing.edit'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Judul Kustom Owner')
            ->assertSee('baris kedua kustom.');
    }

    public function test_landing_shows_driver_reputation_section(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Reputasi sopir jadi alasan orang memilih Anda')
            ->assertSee('Ulasan masuk ke dashboard Anda dulu', false)
            ->assertSee('product-driver-profile.jpg', false);
    }

    public function test_super_admin_can_edit_driver_reputation_section(): void
    {
        $this->actingAs($this->superAdmin())
            ->patch('/superadmin/konten-landing', [
                'reviews_title' => 'Judul Reputasi Kustom',
                'reviews_items' => [1 => 'Poin kedua kustom.'],
            ])
            ->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('Judul Reputasi Kustom')
            ->assertSee('Poin kedua kustom.')
            // Poin lain tetap default (override per-item, bukan menimpa seluruh daftar).
            ->assertSee('Penyewa tahu siapa yang akan menjemput', false);
    }

    public function test_landing_shows_family_chat_and_tracking_mockups(): void
    {
        $this->get('/')
            ->assertOk()
            // Ilustrasi percakapan berbagi kode booking.
            ->assertSee('Ini kode buat pantau perjalanannya', false)
            ->assertSee('Ilustrasi: penyewa membagikan kode ke keluarga')
            // Ilustrasi halaman lacak: hanya fitur yg sudah berjalan (tahap + ETA manual).
            ->assertSee('Dalam Perjalanan')
            ->assertSee('Estimasi tiba: 45 menit lagi')
            ->assertSee('Ilustrasi: halaman lacak yang dibuka keluarga');
    }

    public function test_super_admin_can_edit_family_illustration_captions(): void
    {
        $this->actingAs($this->superAdmin())
            ->patch('/superadmin/konten-landing', [
                'family_chat_caption' => 'Caption chat kustom',
                'family_track_caption' => 'Caption lacak kustom',
            ])
            ->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('Caption chat kustom')
            ->assertSee('Caption lacak kustom');
    }

    /**
     * Regresi: itemIcons di landing.blade.php di-hardcode per grup. Menambah item
     * ke LandingCopy::DEFAULTS['feature_groups'] sempat men-500-kan SELURUH landing
     * page ("Undefined array key"). Sekarang lookup ikon punya fallback.
     */
    public function test_extra_feature_group_item_does_not_break_landing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Profil dan rating sopir yang bisa dilihat penyewa');
    }

    public function test_partial_pain_item_edit_keeps_other_items_default(): void
    {
        $this->actingAs($this->superAdmin())
            ->patch('/superadmin/konten-landing', [
                'pain_items' => [
                    2 => ['title' => 'Kartu Ketiga Kustom'],
                ],
            ])
            ->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('Kartu Ketiga Kustom') // item 2 diganti
            ->assertSee('Sulit tahu posisi kendaraan') // item 0 tetap default
            ->assertSee('Data operasional tersebar'); // item 4 tetap default
    }

    public function test_blank_field_falls_back_to_default_after_being_previously_set(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch('/superadmin/konten-landing', ['cta_title' => 'Judul Sementara']);
        $this->get('/')->assertSee('Judul Sementara');

        $this->actingAs($admin)->patch('/superadmin/konten-landing', ['cta_title' => '']);
        $this->get('/')->assertSee('Siap mengelola armada lebih efisien?'); // kembali ke default
    }
}
