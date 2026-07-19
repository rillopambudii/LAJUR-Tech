<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function owner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_owner_can_view_and_update_site_settings(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get('/admin/situs')->assertOk();

        $this->actingAs($owner)->put('/admin/situs', [
            'display_name' => 'Lajur Prima',
            'tagline' => 'Rental Andalan Kaltim',
            'contact_phone' => '+62 899-0000-1111',
            'contact_address' => 'Jl. Baru No. 2',
            'contact_email' => 'cs@lajurprima.id',
            'accent_color' => '#1F8A63',
        ])->assertRedirect(route('admin.site.edit'));

        $this->tenant->refresh();
        $this->assertSame('Lajur Prima', $this->tenant->display_name);
        $this->assertSame('#1F8A63', $this->tenant->accent_color);
    }

    public function test_invalid_accent_color_is_rejected(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'accent_color' => 'merah',
        ])->assertSessionHasErrors('accent_color');
    }

    public function test_logo_upload_and_replace_deletes_old_file(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)->put('/admin/situs', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);
        $first = $this->tenant->fresh()->logo_path;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($owner)->put('/admin/situs', [
            'logo' => UploadedFile::fake()->image('logo2.png', 200, 200),
        ]);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($this->tenant->fresh()->logo_path);
    }

    public function test_remove_logo_clears_column_and_file(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)->put('/admin/situs', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);
        $path = $this->tenant->fresh()->logo_path;

        $this->actingAs($owner)->put('/admin/situs', ['remove_logo' => '1']);

        $this->assertNull($this->tenant->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_owner_can_save_storefront_customization(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put('/admin/situs', [
            'hero_title' => 'Sewa Mobil Anti Ribet',
            'about_title' => 'Kenalan dengan kami',
            'about_text' => 'Kami rental terpercaya.',
            'why_us' => [
                ['title' => 'Cepat', 'text' => 'Proses 10 menit'],
                ['title' => '', 'text' => 'baris kosong dibuang'],
            ],
            'whatsapp' => '0812-3456-7890',
            'instagram' => 'lajurprima',
            'section_effect' => 'zoom',
            'meta_title' => 'Lajur Prima - Rental',
            // checkbox tak dicentang → show_testimonials harus jadi false
            'splash_enabled' => '1',
            'show_about' => '1',
            'show_why' => '1',
        ])->assertRedirect(route('admin.site.edit'));

        $t = $this->tenant->fresh();
        $this->assertSame('Sewa Mobil Anti Ribet', $t->hero_title);
        $this->assertSame('zoom', $t->section_effect);
        $this->assertCount(1, $t->why_us); // baris judul kosong terbuang
        $this->assertSame('Cepat', $t->why_us[0]['title']);
        $this->assertFalse($t->show_testimonials); // checkbox tak dikirim → false
        $this->assertTrue($t->show_about);
    }

    public function test_invalid_section_effect_is_rejected(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'section_effect' => 'meledak',
        ])->assertSessionHasErrors('section_effect');
    }

    public function test_driver_cannot_access_site_settings(): void
    {
        $driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sopir', 'email' => 'd@lajur.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER,
        ]);

        $this->actingAs($driver)->get('/admin/situs')->assertForbidden();
    }
}
