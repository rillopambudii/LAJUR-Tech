# Tenant Branding & Storefront Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let each tenant brand their own public storefront (name, tagline, contacts, logo, accent color) from a "Pengaturan Situs" page in their admin, with graceful Lajur fallbacks.

**Architecture:** Seven nullable branding columns on `tenants`. A small `App\Tenancy\Branding` value object resolves the active tenant's branding with hardcoded Lajur defaults, injected into `layouts.public` + `home` via a view composer. A new `Admin\SiteSettingController` (GET/PUT `admin/situs`) lets owner/admin edit the current tenant's branding, reusing the car-image upload pattern for the logo. Accent color is applied as an inline CSS-variable override on the public layout only.

**Tech Stack:** Laravel 12, Blade, vanilla CSS variables, `public` storage disk, PHPUnit feature tests.

## Global Constraints

- All branding columns nullable; **null = current hardcoded Lajur value**. The seeded `lajur` tenant with no branding set must render the public site EXACTLY as today (existing tests pass unmodified).
- Logo upload copies the CarController pattern verbatim: `store('logos', 'public')`, validation `image|mimes:jpeg,jpg,png,webp|max:2048`, old file deleted on replace/remove.
- Accent override applies ONLY to `layouts.public` pages. Admin, driver, superadmin layouts stay Lajur gold.
- No feature gating: every plan gets the settings page and storefront.
- Run `php artisan test` after every task; suite must stay green (baseline 142 tests).

---

### Task 1: Branding columns on tenants

**Files:**
- Create: `database/migrations/2026_07_13_000001_add_branding_to_tenants.php`
- Modify: `app/Models/Tenant.php`
- Test: `tests/Feature/TenantBrandingSchemaTest.php`

**Interfaces:**
- Produces: nullable columns `display_name`, `tagline`, `contact_phone`, `contact_address`, `contact_email`, `logo_path`, `accent_color` on `tenants`; all added to `Tenant::$fillable`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBrandingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_stores_branding_fields(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental', 'slug' => 'kaltim-rental', 'plan' => 'basic',
            'subscription_status' => 'active',
            'display_name' => 'Kaltim Rental Mobil',
            'tagline' => 'Sewa Mobil Samarinda Termurah',
            'contact_phone' => '+62 811-1111-2222',
            'contact_address' => 'Jl. Juanda No. 1, Samarinda',
            'contact_email' => 'halo@kaltimrental.id',
            'logo_path' => 'logos/x.png',
            'accent_color' => '#2C6E8F',
        ]);

        $fresh = $tenant->fresh();
        $this->assertSame('Kaltim Rental Mobil', $fresh->display_name);
        $this->assertSame('#2C6E8F', $fresh->accent_color);
        $this->assertSame('logos/x.png', $fresh->logo_path);
    }

    public function test_branding_fields_default_to_null(): void
    {
        $tenant = Tenant::create([
            'name' => 'Polos Co', 'slug' => 'polos-co', 'plan' => 'basic',
            'subscription_status' => 'active',
        ]);

        $this->assertNull($tenant->fresh()->display_name);
        $this->assertNull($tenant->fresh()->accent_color);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TenantBrandingSchemaTest`
Expected: FAIL — unknown column `display_name`.

- [ ] **Step 3: Create the migration**

```php
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
```

- [ ] **Step 4: Extend `Tenant::$fillable`**

In `app/Models/Tenant.php` add to `$fillable` (after `'slug'`):

```php
        'display_name',
        'tagline',
        'contact_phone',
        'contact_address',
        'contact_email',
        'logo_path',
        'accent_color',
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TenantBrandingSchemaTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run full suite** — expected 144 passing.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_13_000001_add_branding_to_tenants.php \
        app/Models/Tenant.php tests/Feature/TenantBrandingSchemaTest.php
git commit -m "feat: branding columns on tenants"
```

---

### Task 2: Branding resolver + public views read tenant branding

**Files:**
- Create: `app/Tenancy/Branding.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/layouts/public.blade.php`
- Modify: `resources/views/home.blade.php`
- Test: `tests/Feature/StorefrontBrandingTest.php`

**Interfaces:**
- Consumes: Task 1 columns; `TenantManager::current()` (existing singleton).
- Produces: `Branding` value object with `name()`, `tagline()`, `phone()`, `address()`, `email()`, `logoUrl()`, `accentColor()`, `accentDark()`, `accentGlow()`; `$branding` available in `layouts.public` and `home` views via composer.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function brandedTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Kaltim Rental', 'slug' => 'kaltim-rental', 'plan' => 'basic',
            'subscription_status' => 'active',
            'display_name' => 'Kaltim Rental Mobil',
            'tagline' => 'Sewa Mobil Samarinda Terpercaya',
            'contact_phone' => '+62 811-1111-2222',
            'contact_address' => 'Jl. Juanda No. 1, Samarinda',
            'contact_email' => 'halo@kaltimrental.id',
            'accent_color' => '#2C6E8F',
        ]);
    }

    private function ownerOf(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER,
        ]);
    }

    public function test_default_tenant_home_still_shows_lajur_branding(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Lajur');
        $response->assertSee('Samarinda, Kalimantan Timur');
        $response->assertSee('halo@lajur.id');
    }

    public function test_home_shows_tenant_branding_when_logged_in_as_its_owner(): void
    {
        $tenant = $this->brandedTenant();

        $response = $this->actingAs($this->ownerOf($tenant))->get('/');

        $response->assertOk();
        $response->assertSee('Kaltim Rental Mobil');
        $response->assertSee('Sewa Mobil Samarinda Terpercaya');
        $response->assertSee('halo@kaltimrental.id');
        $response->assertSee('#2C6E8F', false); // accent style override present
    }

    public function test_accent_style_absent_when_not_set(): void
    {
        $this->get('/')->assertOk()->assertDontSee('--accent-override', false);
    }

    public function test_branding_does_not_leak_across_tenants(): void
    {
        $this->brandedTenant(); // exists, but request runs under default lajur tenant

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Kaltim Rental Mobil');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StorefrontBrandingTest`
Expected: `test_default_tenant_home_still_shows_lajur_branding` PASSES already; the branded-tenant tests FAIL (branding not rendered).

- [ ] **Step 3: Create `Branding`**

```php
<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the active tenant's storefront branding, falling back to the
 * Lajur defaults that were previously hardcoded in the public views. Only
 * layouts.public + home receive this (via the view composer in
 * AppServiceProvider); dashboards stay Lajur-branded.
 */
class Branding
{
    public function __construct(private ?Tenant $tenant)
    {
    }

    public function name(): string
    {
        return $this->tenant?->display_name ?? $this->tenant?->name ?? 'Lajur';
    }

    public function tagline(): string
    {
        return $this->tenant?->tagline ?? 'Rental Mobil Premium · Kalimantan Timur';
    }

    public function phone(): string
    {
        return $this->tenant?->contact_phone ?? '+62 812-0000-0000';
    }

    public function address(): string
    {
        return $this->tenant?->contact_address ?? 'Samarinda, Kalimantan Timur';
    }

    public function email(): string
    {
        return $this->tenant?->contact_email ?? 'halo@lajur.id';
    }

    public function logoUrl(): ?string
    {
        return $this->tenant?->logo_path
            ? Storage::disk('public')->url($this->tenant->logo_path)
            : null;
    }

    public function accentColor(): ?string
    {
        return $this->tenant?->accent_color;
    }

    /** Accent darkened ~15% for hover states (replaces --amber-600). */
    public function accentDark(): ?string
    {
        $hex = $this->accentColor();
        if (! $hex) {
            return null;
        }

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02X%02X%02X', (int) ($r * .85), (int) ($g * .85), (int) ($b * .85));
    }

    /** Accent at 30% alpha for glow shadows (replaces --amber-glow). */
    public function accentGlow(): ?string
    {
        $hex = $this->accentColor();
        if (! $hex) {
            return null;
        }

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('rgba(%d, %d, %d, 0.30)', $r, $g, $b);
    }
}
```

- [ ] **Step 4: Register the view composer**

In `app/Providers/AppServiceProvider.php` `boot()` (add imports `App\Tenancy\Branding`, `App\Tenancy\TenantManager`, `Illuminate\Support\Facades\View`):

```php
        // Storefront branding: only the public layout + home read tenant
        // branding; dashboards keep Lajur branding.
        View::composer(['layouts.public', 'home'], function ($view) {
            $view->with('branding', new Branding(app(TenantManager::class)->current()));
        });
```

- [ ] **Step 5: Wire `layouts/public.blade.php`**

Replace the hardcoded pieces (keep everything else intact):

1. Title default: `<title>@yield('title', $branding->name().' - Rental Mobil')</title>`
2. Navbar brand (the `<a ... class="brand">` containing `<span class="mark">`):

```blade
            <a href="{{ route('home') }}" class="brand" aria-label="{{ $branding->name() }} beranda">
                @if ($branding->logoUrl())
                    <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }}" style="width:38px;height:38px;border-radius:11px;object-fit:cover">
                @else
                    <span class="mark"><x-icon name="route" /></span>
                @endif
                {{ $branding->name() }}
            </a>
```

3. Footer brand block: same logo/name treatment; footer contact column uses `$branding->address()`, `$branding->phone()`, `$branding->email()`; copyright `&copy; {{ date('Y') }} {{ $branding->name() }}.`
4. Accent override, placed right after the `app.css` `<link>`:

```blade
    @if ($branding->accentColor())
        {{-- --accent-override: penanda uji; nilai menimpa var aksen brand --}}
        <style id="accent-override">/* --accent-override */
            :root {
                --amber: {{ $branding->accentColor() }};
                --amber-600: {{ $branding->accentDark() }};
                --amber-glow: {{ $branding->accentGlow() }};
            }
        </style>
    @endif
```

- [ ] **Step 6: Wire `home.blade.php`**

1. Hero eyebrow: `<span class="eyebrow hero-eyebrow">{{ $branding->tagline() }}</span>`
2. Contact section items: Lokasi → `{{ $branding->address() }}`, Telepon/WhatsApp → `{{ $branding->phone() }}`, Email → `{{ $branding->email() }}`.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=StorefrontBrandingTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Run full suite** — expected 148 passing (146 prior +... baseline 144 after Task 1, +4 new = 148). Every pre-existing public-page test must pass unmodified (fallbacks preserve today's output).

- [ ] **Step 9: Commit**

```bash
git add app/Tenancy/Branding.php app/Providers/AppServiceProvider.php \
        resources/views/layouts/public.blade.php resources/views/home.blade.php \
        tests/Feature/StorefrontBrandingTest.php
git commit -m "feat: storefront reads tenant branding with Lajur fallbacks"
```

---

### Task 3: Pengaturan Situs page in tenant admin

**Files:**
- Create: `app/Http/Controllers/Admin/SiteSettingController.php`
- Create: `app/Http/Requests/SiteSettingRequest.php`
- Create: `resources/views/admin/site.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/SiteSettingTest.php`

**Interfaces:**
- Consumes: Task 1 columns, `TenantManager::current()`.
- Produces: `GET admin/situs` (`admin.site.edit`), `PUT admin/situs` (`admin.site.update`); sidebar "Situs" nav item.

- [ ] **Step 1: Write the failing test**

```php
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
        $this->actingAs($this->owner())->get('/admin/situs')->assertOk();

        $this->actingAs($this->owner())->put('/admin/situs', [
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

    public function test_driver_cannot_access_site_settings(): void
    {
        $driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sopir', 'email' => 'd@lajur.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER,
        ]);

        $this->actingAs($driver)->get('/admin/situs')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SiteSettingTest`
Expected: FAIL — 404 on `/admin/situs`.

- [ ] **Step 3: Create `SiteSettingRequest`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route already behind auth+admin middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent_color.regex' => 'Warna harus format hex, mis. #E7B24C.',
            'logo.image' => 'Berkas harus berupa gambar.',
            'logo.mimes' => 'Logo harus berformat JPEG, JPG, PNG, atau WEBP.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
        ];
    }
}
```

- [ ] **Step 4: Create `SiteSettingController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettingRequest;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class SiteSettingController extends Controller
{
    public function edit(TenantManager $manager): View
    {
        return view('admin.site', ['tenant' => $manager->current()]);
    }

    public function update(SiteSettingRequest $request, TenantManager $manager): RedirectResponse
    {
        $tenant = $manager->current();
        $data = $request->validated();

        if ($request->boolean('remove_logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
            $data['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo'], $data['remove_logo']);

        $tenant->update($data);

        return redirect()->route('admin.site.edit')
            ->with('success', 'Pengaturan situs disimpan.');
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add the import `use App\Http\Controllers\Admin\SiteSettingController;` and inside the `admin` group (after the Messages routes):

```php
        // Pengaturan situs publik (branding storefront) — semua plan
        Route::get('situs', [SiteSettingController::class, 'edit'])->name('site.edit');
        Route::put('situs', [SiteSettingController::class, 'update'])->name('site.update');
```

- [ ] **Step 6: Create `resources/views/admin/site.blade.php`**

```blade
@extends('layouts.admin')

@section('title', 'Pengaturan Situs')
@section('crumb', 'Situs Publik')
@section('heading', 'Pengaturan Situs')

@section('topbar-action')
    <a href="{{ route('home') }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">
        <x-icon name="eye" /> Lihat Situs
    </a>
@endsection

@section('content')
<div class="panel" style="max-width:760px">
    <div class="panel-head">
        <h2>Branding Storefront</h2>
        <span class="tag">Tampil di situs publik Anda</span>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.site.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="form-row">
                <div class="field">
                    <label for="display_name">Nama Tampilan</label>
                    <input class="input @error('display_name') has-error @enderror" type="text" id="display_name"
                        name="display_name" value="{{ old('display_name', $tenant->display_name) }}" placeholder="{{ $tenant->name }}">
                    @error('display_name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="tagline">Tagline</label>
                    <input class="input @error('tagline') has-error @enderror" type="text" id="tagline"
                        name="tagline" value="{{ old('tagline', $tenant->tagline) }}" placeholder="Rental Mobil Premium · Kalimantan Timur">
                    @error('tagline')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="contact_phone">Telepon / WhatsApp</label>
                    <input class="input @error('contact_phone') has-error @enderror" type="text" id="contact_phone"
                        name="contact_phone" value="{{ old('contact_phone', $tenant->contact_phone) }}" placeholder="+62 812-0000-0000">
                    @error('contact_phone')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="contact_email">Email</label>
                    <input class="input @error('contact_email') has-error @enderror" type="email" id="contact_email"
                        name="contact_email" value="{{ old('contact_email', $tenant->contact_email) }}" placeholder="halo@bisnisanda.id">
                    @error('contact_email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label for="contact_address">Alamat</label>
                <input class="input @error('contact_address') has-error @enderror" type="text" id="contact_address"
                    name="contact_address" value="{{ old('contact_address', $tenant->contact_address) }}" placeholder="Samarinda, Kalimantan Timur">
                @error('contact_address')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="accent_color">Warna Aksen</label>
                    <div style="display:flex;gap:10px;align-items:center">
                        <input type="color" id="accent_color_picker"
                            value="{{ old('accent_color', $tenant->accent_color ?? '#E7B24C') }}"
                            style="width:46px;height:46px;border:1.5px solid var(--ivory-200);border-radius:10px;padding:2px;background:var(--white);cursor:pointer"
                            oninput="document.getElementById('accent_color').value = this.value">
                        <input class="input mono @error('accent_color') has-error @enderror" type="text" id="accent_color"
                            name="accent_color" value="{{ old('accent_color', $tenant->accent_color) }}" placeholder="#E7B24C"
                            oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('accent_color_picker').value = this.value">
                    </div>
                    @error('accent_color')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="logo">Logo (opsional)</label>
                    @if ($tenant->logo_path)
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($tenant->logo_path) }}"
                                 alt="Logo" style="width:52px;height:52px;border-radius:12px;object-fit:cover;border:1px solid var(--ivory-200)">
                            <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;color:var(--graphite);cursor:pointer">
                                <input type="checkbox" name="remove_logo" value="1"> Hapus logo
                            </label>
                        </div>
                    @endif
                    <input class="input" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
                    @error('logo')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:8px">Simpan Pengaturan</button>
            <p style="margin-top:12px;font-size:.86rem;color:var(--graphite)">Kosongkan kolom untuk memakai tampilan bawaan.</p>
        </form>
    </div>
</div>
@endsection
```

- [ ] **Step 7: Add sidebar nav item**

In `resources/views/layouts/admin.blade.php`, after the Pesan (messages) nav link, add:

```blade
            <a href="{{ route('admin.site.edit') }}" class="{{ request()->routeIs('admin.site.*') ? 'active' : '' }}">
                <x-icon name="settings" /> Situs
            </a>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=SiteSettingTest`
Expected: PASS (5 tests)

- [ ] **Step 9: Run full suite (final regression)** — expected 153 passing (148 + 5).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/SiteSettingController.php app/Http/Requests/SiteSettingRequest.php \
        resources/views/admin/site.blade.php routes/web.php resources/views/layouts/admin.blade.php \
        tests/Feature/SiteSettingTest.php
git commit -m "feat: halaman Pengaturan Situs untuk branding storefront tenant"
```

---

## Post-plan manual check (not automated)

- [ ] `php artisan migrate` on the real DB, then `php artisan storage:link` if `public/storage` doesn't exist yet (car uploads imply it already does).
- [ ] Log in as a tenant owner, open `/admin/situs`, set name/tagline/color/logo, save, click "Lihat Situs" and confirm the storefront reflects it while the default Lajur site (logged out) stays unchanged.
