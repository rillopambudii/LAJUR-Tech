# Tenant Storefront Personalization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let every tenant, on every plan, pick a font pairing (5 choices) and a UI shape/spacing style (5 choices) independently for their storefront, from `/admin/situs` — no dark-mode, no plan gating, reusing the exact inline-`<style>`-override mechanism the accent-color feature already proved out.

**Architecture:** Two new nullable columns on `tenants` (`font_style`, `ui_style`). `App\Tenancy\Branding` gains accessors resolving each to CSS values via small lookup arrays (default `klasik` key = today's exact values = no-op). `layouts.public`'s existing accent-override `<style>` block is extended with the same variables, always rendered (not conditionally, since font/UI style always resolve to a value — Klasik included — unlike accent color which can be genuinely absent). `SiteSettingRequest` gains `Rule::in(...)` validation for both new fields, no plan check.

**Tech Stack:** Laravel 12, Blade, vanilla CSS custom properties, PHPUnit.

## Global Constraints

- **Never touch background/text/border color tokens** (`--ivory`, `--white`, `--ink`, `--ivory-200`, `--petrol`, `--graphite`) in any style/UI-style definition — this is the exact bug class that caused the rejected dark-theme design's illegible text. Every value table in this plan only touches `--font-display`, `--font-body`, `--radius-*`, and `.section { padding-block }`.
- `font_style=klasik` and `ui_style=klasik` (including `null`, which resolves to `klasik`) must produce byte-identical output to today — reuse `StorefrontBrandingTest`'s existing default-tenant assertions as the regression guard.
- No plan/feature gating anywhere in this feature — every tenant on every plan gets all 5+5 choices.
- Run `php artisan test` after every task; suite must stay green (baseline 169 tests as of this plan).

---

### Task 1: `font_style`/`ui_style` columns + `Branding` accessors + public layout wiring

**Files:**
- Create: `database/migrations/2026_07_14_100000_add_personalization_to_tenants.php`
- Modify: `app/Models/Tenant.php`
- Modify: `app/Tenancy/Branding.php`
- Modify: `resources/views/layouts/public.blade.php`
- Test: `tests/Feature/StorefrontPersonalizationTest.php`

**Interfaces:**
- Produces: `tenants.font_style` / `tenants.ui_style` (nullable strings), added to `Tenant::$fillable`.
- `Branding::fontDisplay(): string`, `fontBody(): string`, `radiusSm(): string`, `radius(): string`, `radiusLg(): string`, `radiusPill(): string`, `sectionSpacing(): string` — all resolve via lookup arrays keyed by the tenant's `font_style`/`ui_style` (or `'klasik'` when null/unrecognized), returning the exact CSS values from the spec's tables.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    private function ownerOf(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_default_tenant_home_uses_klasik_font_and_radius_with_no_override(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // Klasik is a no-op: the accent/personalization <style> block only
        // ever emits when there is something non-default to override.
        $response->assertDontSee('--font-display:', false);
        $response->assertDontSee('--radius-pill:', false);
    }

    public function test_elegan_font_style_renders_playfair_display(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental', 'slug' => 'kaltim-rental', 'plan' => 'basic',
            'subscription_status' => 'active', 'font_style' => 'elegan',
        ]);

        $response = $this->actingAs($this->ownerOf($tenant))->get('/');

        $response->assertOk();
        $response->assertSee("'Playfair Display'", false);
    }

    public function test_tegas_ui_style_renders_tighter_radius_and_denser_spacing(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental 2', 'slug' => 'kaltim-rental-2', 'plan' => 'basic',
            'subscription_status' => 'active', 'ui_style' => 'tegas',
        ]);

        $response = $this->actingAs($this->ownerOf($tenant))->get('/');

        $response->assertOk();
        $response->assertSee('--radius-pill: 8px', false);
        $response->assertSee('padding-block: 72px', false);
    }

    public function test_font_style_and_ui_style_apply_independently_together(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental 3', 'slug' => 'kaltim-rental-3', 'plan' => 'basic',
            'subscription_status' => 'active', 'font_style' => 'korporat', 'ui_style' => 'playful',
        ]);

        $response = $this->actingAs($this->ownerOf($tenant))->get('/');

        $response->assertOk();
        $response->assertSee("'Space Grotesk'", false);
        $response->assertSee('--radius-pill: 999px', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StorefrontPersonalizationTest`
Expected: FAIL — unknown columns / `Branding` has no `fontDisplay()` etc.

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
            $table->string('font_style')->nullable()->after('accent_color');
            $table->string('ui_style')->nullable()->after('font_style');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['font_style', 'ui_style']);
        });
    }
};
```

- [ ] **Step 4: Add both columns to `Tenant::$fillable`**

In `app/Models/Tenant.php`, add `'font_style', 'ui_style',` right after the existing `'accent_color',` entry in `$fillable`.

- [ ] **Step 5: Add lookup constants and accessors to `Branding`**

In `app/Tenancy/Branding.php`, add these `private const` arrays right after the class's opening brace (before `__construct`), and the accessor methods after `accentGlow()`:

```php
    /** key => [display font, body font], both as full CSS font-family values. */
    private const FONT_STYLES = [
        'klasik' => ["'Sora', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif"],
        'netral' => ["'Inter', system-ui, sans-serif", "'Inter', system-ui, sans-serif"],
        'ramah' => ["'Poppins', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif"],
        'elegan' => ["'Playfair Display', serif", "'Plus Jakarta Sans', system-ui, sans-serif"],
        'korporat' => ["'Space Grotesk', system-ui, sans-serif", "'Inter', system-ui, sans-serif"],
    ];

    /** key => [radius-sm, radius, radius-lg, radius-pill, section padding-block], all in px. */
    private const UI_STYLES = [
        'klasik' => [8, 14, 22, 999, 92],
        'tegas' => [4, 8, 12, 8, 72],
        'lembut' => [10, 18, 28, 999, 120],
        'minimalis' => [2, 4, 8, 4, 92],
        'playful' => [12, 20, 30, 999, 92],
    ];
```

```php
    private function fontStyleKey(): string
    {
        $key = $this->tenant?->font_style;

        return array_key_exists($key, self::FONT_STYLES) ? $key : 'klasik';
    }

    private function uiStyleKey(): string
    {
        $key = $this->tenant?->ui_style;

        return array_key_exists($key, self::UI_STYLES) ? $key : 'klasik';
    }

    public function fontDisplay(): string
    {
        return self::FONT_STYLES[$this->fontStyleKey()][0];
    }

    public function fontBody(): string
    {
        return self::FONT_STYLES[$this->fontStyleKey()][1];
    }

    public function radiusSm(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][0] . 'px';
    }

    public function radius(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][1] . 'px';
    }

    public function radiusLg(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][2] . 'px';
    }

    public function radiusPill(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][3] . 'px';
    }

    public function sectionSpacing(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][4] . 'px';
    }

    /** Whether font/UI-style personalization differs from Klasik (both default) — gates whether the override <style> block needs to emit anything for these properties. */
    public function hasPersonalization(): bool
    {
        return $this->fontStyleKey() !== 'klasik' || $this->uiStyleKey() !== 'klasik';
    }
```

Note: `array_key_exists($key, self::FONT_STYLES)` with `$key = null` returns `false` safely in PHP (no warning), so the `null`-column case correctly falls back to `'klasik'`.

- [ ] **Step 6: Wire `layouts/public.blade.php`**

Read the current file first — the accent-override block currently looks like:

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

Replace it with a version that ALSO emits font/UI-style overrides when personalized, keeping the accent-only case exactly as before (so `test_elegan_font_style_renders_playfair_display` and the like don't require an accent color to also be set):

```blade
    @if ($branding->accentColor() || $branding->hasPersonalization())
        {{-- --accent-override: penanda uji; nilai menimpa var aksen & gaya brand --}}
        <style id="accent-override">/* --accent-override */
            :root {
                @if ($branding->accentColor())
                    --amber: {{ $branding->accentColor() }};
                    --amber-600: {{ $branding->accentDark() }};
                    --amber-glow: {{ $branding->accentGlow() }};
                @endif
                @if ($branding->hasPersonalization())
                    --font-display: {{ $branding->fontDisplay() }};
                    --font-body: {{ $branding->fontBody() }};
                    --radius-sm: {{ $branding->radiusSm() }};
                    --radius: {{ $branding->radius() }};
                    --radius-lg: {{ $branding->radiusLg() }};
                    --radius-pill: {{ $branding->radiusPill() }};
                @endif
            }
            @if ($branding->hasPersonalization())
                .section { padding-block: {{ $branding->sectionSpacing() }}; }
            @endif
        </style>
    @endif
```

Also add the 4 new font families to the existing Google Fonts `<link>` (find the current `<link href="https://fonts.googleapis.com/css2?family=Sora:...">` line and extend its `family=` query string, do not add a second `<link>` tag):

```blade
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&family=Playfair+Display:wght@600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=StorefrontPersonalizationTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Run full suite (regression check)**

Run: `php artisan test`
Expected: 169 existing + 4 new = 173 passing. Every existing `StorefrontBrandingTest` assertion about the default site's exact output must still pass unmodified (Klasik+Klasik is a no-op).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_14_100000_add_personalization_to_tenants.php \
        app/Models/Tenant.php app/Tenancy/Branding.php \
        resources/views/layouts/public.blade.php \
        tests/Feature/StorefrontPersonalizationTest.php
git commit -m "feat: font-style and UI-style personalization columns + Branding accessors"
```

---

### Task 2: Settings page pickers + validation

**Files:**
- Modify: `app/Http/Requests/SiteSettingRequest.php`
- Modify: `resources/views/admin/site.blade.php`
- Test: `tests/Feature/SiteSettingPersonalizationTest.php`

**Interfaces:**
- Consumes: `Branding` constants from Task 1 (read the actual key lists from `App\Tenancy\Branding::FONT_STYLES`/`UI_STYLES` — do NOT hardcode a second copy of the key list in the request; if those constants are `private`, add a `public static function fontStyleKeys(): array` / `uiStyleKeys(): array` to `Branding` in this task returning `array_keys(self::FONT_STYLES)` / `array_keys(self::UI_STYLES)`, and use that from `SiteSettingRequest` — single source of truth for valid keys).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingPersonalizationTest extends TestCase
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

    public function test_settings_page_shows_font_and_ui_style_pickers(): void
    {
        $this->actingAs($this->owner())->get('/admin/situs')
            ->assertOk()
            ->assertSee('Gaya Font')
            ->assertSee('Gaya UI')
            ->assertSee('Elegan')
            ->assertSee('Playful');
    }

    public function test_owner_can_set_font_and_ui_style(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'font_style' => 'korporat',
            'ui_style' => 'minimalis',
        ])->assertRedirect(route('admin.site.edit'));

        $this->tenant->refresh();
        $this->assertSame('korporat', $this->tenant->font_style);
        $this->assertSame('minimalis', $this->tenant->ui_style);
    }

    public function test_invalid_font_style_is_rejected(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'font_style' => 'not-a-real-style',
        ])->assertSessionHasErrors('font_style');
    }

    public function test_invalid_ui_style_is_rejected(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'ui_style' => 'not-a-real-style',
        ])->assertSessionHasErrors('ui_style');
    }

    public function test_basic_plan_tenant_can_set_any_style_no_gating(): void
    {
        $basicTenant = Tenant::create(['name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active']);
        $basicOwner = User::create([
            'tenant_id' => $basicTenant->id, 'name' => 'Owner', 'email' => 'o@basic-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($basicOwner)->put('/admin/situs', [
            'ui_style' => 'lembut',
        ])->assertRedirect(route('admin.site.edit'));

        $this->assertSame('lembut', $basicTenant->fresh()->ui_style);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SiteSettingPersonalizationTest`
Expected: FAIL — pickers not in the view, fields not validated.

- [ ] **Step 3: Add `public static` key-list accessors to `Branding`**

In `app/Tenancy/Branding.php`, add after the `UI_STYLES` constant declaration:

```php
    public static function fontStyleKeys(): array
    {
        return array_keys(self::FONT_STYLES);
    }

    public static function uiStyleKeys(): array
    {
        return array_keys(self::UI_STYLES);
    }
```

- [ ] **Step 4: Update `SiteSettingRequest`**

In `app/Http/Requests/SiteSettingRequest.php`, add the import `use App\Tenancy\Branding;` and add these two rules inside `rules()`, alongside the existing ones:

```php
            'font_style' => ['nullable', Rule::in(Branding::fontStyleKeys())],
            'ui_style' => ['nullable', Rule::in(Branding::uiStyleKeys())],
```

(Add `use Illuminate\Validation\Rule;` import too if not already present.)

- [ ] **Step 5: Update `resources/views/admin/site.blade.php`**

Read the current file first (it has a "Branding Storefront" panel ending right before the closing `</div></div>@endsection`). Add two new panels after the existing one, before `@endsection`:

```blade
<div class="panel" style="max-width:760px;margin-top:24px">
    <div class="panel-head">
        <h2>Gaya Font</h2>
        <span class="tag">Berlaku di seluruh situs publik Anda</span>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.site.update') }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px">
                @foreach ([
                    'klasik' => ["'Sora', system-ui, sans-serif", 'Klasik'],
                    'netral' => ["'Inter', system-ui, sans-serif", 'Netral'],
                    'ramah' => ["'Poppins', system-ui, sans-serif", 'Ramah'],
                    'elegan' => ["'Playfair Display', serif", 'Elegan'],
                    'korporat' => ["'Space Grotesk', system-ui, sans-serif", 'Korporat'],
                ] as $key => [$fontFamily, $label])
                    <label style="display:block;padding:16px;border:1.5px solid var(--ivory-200);border-radius:var(--radius);cursor:pointer;{{ ($tenant->font_style ?? 'klasik') === $key ? 'border-color:var(--amber);background:var(--ivory)' : '' }}">
                        <input type="radio" name="font_style" value="{{ $key }}" {{ ($tenant->font_style ?? 'klasik') === $key ? 'checked' : '' }} style="margin-bottom:8px">
                        <div style="font-family:{{ $fontFamily }};font-size:1.15rem;font-weight:700">{{ $label }}</div>
                    </label>
                @endforeach
            </div>
            @error('font_style')<span class="field-error">{{ $message }}</span>@enderror
            <button type="submit" class="btn btn-primary" style="margin-top:16px">Simpan Gaya Font</button>
        </form>
    </div>
</div>

<div class="panel" style="max-width:760px;margin-top:24px">
    <div class="panel-head">
        <h2>Gaya UI</h2>
        <span class="tag">Bentuk sudut &amp; jarak antar bagian</span>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.site.update') }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px">
                @foreach ([
                    'klasik' => [14, 'Klasik'],
                    'tegas' => [8, 'Tegas'],
                    'lembut' => [18, 'Lembut'],
                    'minimalis' => [4, 'Minimalis'],
                    'playful' => [20, 'Playful'],
                ] as $key => [$radius, $label])
                    <label style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:16px;border:1.5px solid var(--ivory-200);border-radius:var(--radius);cursor:pointer;{{ ($tenant->ui_style ?? 'klasik') === $key ? 'border-color:var(--amber);background:var(--ivory)' : '' }}">
                        <input type="radio" name="ui_style" value="{{ $key }}" {{ ($tenant->ui_style ?? 'klasik') === $key ? 'checked' : '' }}>
                        <div style="width:56px;height:36px;background:var(--petrol);border-radius:{{ $radius }}px"></div>
                        <div style="font-weight:600;font-size:.92rem">{{ $label }}</div>
                    </label>
                @endforeach
            </div>
            @error('ui_style')<span class="field-error">{{ $message }}</span>@enderror
            <button type="submit" class="btn btn-primary" style="margin-top:16px">Simpan Gaya UI</button>
        </form>
    </div>
</div>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SiteSettingPersonalizationTest`
Expected: PASS (5 tests)

- [ ] **Step 7: Run full suite (final regression check)**

Run: `php artisan test`
Expected: 173 existing (after Task 1) + 5 new = 178 passing.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/SiteSettingRequest.php app/Tenancy/Branding.php \
        resources/views/admin/site.blade.php \
        tests/Feature/SiteSettingPersonalizationTest.php
git commit -m "feat: Gaya Font & Gaya UI pickers on /admin/situs, no plan gating"
```

---

## Post-plan manual check (not automated)

- [ ] `php artisan migrate` on the real DB.
- [ ] Log in as the Borneo Trans demo tenant (or any tenant), open `/admin/situs`, pick a font (e.g. Elegan) and a UI style (e.g. Lembut), save, then open the storefront and visually confirm: headline font changed, card corners are softer, section spacing is airier, AND the accent color / logo / contact info from the existing branding feature still render correctly alongside it (no regression on the already-shipped branding fields).
- [ ] Confirm the default `lajur` tenant's storefront (logged out) is visually unchanged from before this feature — the Klasik+Klasik no-op guarantee, eyeballed not just tested.
