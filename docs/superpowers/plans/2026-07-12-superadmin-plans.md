# Super Admin — Plans & Feature Gating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a `/superadmin` control panel where the platform owner defines Basic/Pro/Business plans (price, 14-day trial length, feature toggles), assigns tenants to plans, and have those toggles actually gate access in the tenant dashboard.

**Architecture:** Two new tables (`plans`, `features`, pivot `feature_plan`) hold plan config, decoupled from the existing `tenants.plan` string column (no FK — `tenants.plan` just stores a `plans.key`). A `Tenant::hasFeature()` helper is the single choke point read by both a new route middleware (`feature:<key>`) and the admin sidebar. A new `super_admin` role (not tenant-scoped, same treatment as the existing `User` tenancy exemption) gates the `/superadmin/*` routes. Trial expiry is handled by one shared `TrialGuard` service, called from both a daily scheduled command and inline in `IdentifyTenant` as a safety net.

**Tech Stack:** Laravel 12, Blade, MySQL/SQLite, PHPUnit feature tests (existing patterns in `tests/Feature/`).

## Global Constraints

- Follow existing codebase conventions exactly: Blade views styled like `resources/views/admin/*`, controllers namespaced `App\Http\Controllers\SuperAdmin\*`, migrations timestamped `2026_07_12_*`, tests in `tests/Feature/*Test.php` using `RefreshDatabase`.
- Money is plain rupiah integers (matches `Booking.total_price`, `Car.price_per_day` — no separate currency/cents handling anywhere in this codebase).
- The default seeded tenant (slug `lajur`) must end up on the `business` plan after migrating, so every existing feature test (`AiAssistantTest`, `TrackingTest`, `FuelTrackingTest`, `ExportTest`) keeps passing unmodified.
- No public-facing signup, no billing/payment automation, no super-admin metrics dashboard — out of scope per the spec (`docs/superpowers/specs/2026-07-12-superadmin-plans-design.md`).
- Run `php artisan test` after every task; the full suite must stay green before moving to the next task.

---

### Task 1: Plans & Features database schema

**Files:**
- Create: `database/migrations/2026_07_12_000001_create_plans_table.php`
- Create: `database/migrations/2026_07_12_000002_create_features_table.php`
- Create: `database/migrations/2026_07_12_000003_backfill_tenant_plan_keys.php`
- Test: `tests/Feature/PlanFeatureSchemaTest.php`

**Interfaces:**
- Produces: tables `plans` (`id, key, name, price, trial_days, sort_order, timestamps`), `features` (`id, key, name, description, timestamps`), pivot `feature_plan` (`plan_id, feature_id`). Existing `tenants.plan` values normalized to `basic|pro|business`; tenant `lajur` forced to `business`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanFeatureSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_and_features_tables_support_many_to_many(): void
    {
        $planId = DB::table('plans')->insertGetId([
            'key' => 'test-plan', 'name' => 'Test', 'price' => 1000, 'trial_days' => 14,
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $featureId = DB::table('features')->insertGetId([
            'key' => 'test-feature', 'name' => 'Test Feature',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('feature_plan')->insert(['plan_id' => $planId, 'feature_id' => $featureId]);

        $this->assertDatabaseHas('feature_plan', ['plan_id' => $planId, 'feature_id' => $featureId]);
    }

    public function test_lajur_tenant_is_migrated_to_business_plan(): void
    {
        $plan = DB::table('tenants')->where('slug', 'lajur')->value('plan');

        $this->assertSame('business', $plan);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PlanFeatureSchemaTest`
Expected: FAIL — `no such table: plans` (tables don't exist yet).

- [ ] **Step 3: Create the `plans` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // basic | pro | business
            $table->string('name');
            $table->unsignedInteger('price')->default(0); // rupiah per month
            $table->unsignedInteger('trial_days')->default(14);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

- [ ] **Step 4: Create the `features` + `feature_plan` migration**

```php
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
```

- [ ] **Step 5: Create the tenant-plan backfill migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Legacy tenants.plan values -> the new plans.key values. */
    private array $map = ['free' => 'basic', 'enterprise' => 'business'];

    public function up(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('tenants')->where('plan', $old)->update(['plan' => $new]);
        }

        // The flagship "lajur" tenant is the fully-featured showcase — put it on
        // Business so tracking/fuel/export/AI demos keep working after gating lands.
        DB::table('tenants')->where('slug', 'lajur')->update(['plan' => 'business']);
    }

    public function down(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('tenants')->where('plan', $new)->update(['plan' => $old]);
        }
        // lajur's pre-migration value ('pro') is not distinguishable from a
        // genuine 'pro' tenant at rollback time — left on 'business' intentionally.
    }
};
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PlanFeatureSchemaTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_12_000001_create_plans_table.php \
        database/migrations/2026_07_12_000002_create_features_table.php \
        database/migrations/2026_07_12_000003_backfill_tenant_plan_keys.php \
        tests/Feature/PlanFeatureSchemaTest.php
git commit -m "feat: plans/features schema + tenant plan key backfill"
```

---

### Task 2: Plan/Feature models, PlanSeeder, Tenant::hasFeature(), super_admin role

**Files:**
- Create: `app/Models/Plan.php`
- Create: `app/Models/Feature.php`
- Create: `database/seeders/PlanSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `app/Models/Tenant.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/TenantFeatureGatingTest.php`

**Interfaces:**
- Consumes: `plans`/`features`/`feature_plan` tables (Task 1).
- Produces: `Feature::GPS_TRACKING|FUEL_TRACKING|EXPORT|AI_ASSISTANT` key constants, `Feature::NAMES` (key=>display name map), `Plan::features(): BelongsToMany`, `Feature::plans(): BelongsToMany`, `Tenant::currentPlan(): ?Plan`, `Tenant::hasFeature(string $key): bool`, `Tenant::PLANS = ['basic','pro','business']`, `User::ROLE_SUPER_ADMIN = 'super_admin'` (added to `User::ROLES`), `PlanSeeder` (seeds 3 plans + 4 features + default mapping — Basic: none, Pro: gps_tracking+fuel_tracking+export, Business: all four).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFeatureGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_basic_plan_has_no_premium_features(): void
    {
        $tenant = Tenant::create([
            'name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active',
        ]);

        $this->assertFalse($tenant->hasFeature('gps_tracking'));
        $this->assertFalse($tenant->hasFeature('ai_assistant'));
    }

    public function test_pro_plan_has_tracking_fuel_export_but_not_ai(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pro Co', 'slug' => 'pro-co', 'plan' => 'pro', 'subscription_status' => 'active',
        ]);

        $this->assertTrue($tenant->hasFeature('gps_tracking'));
        $this->assertTrue($tenant->hasFeature('fuel_tracking'));
        $this->assertTrue($tenant->hasFeature('export'));
        $this->assertFalse($tenant->hasFeature('ai_assistant'));
    }

    public function test_business_plan_has_all_features(): void
    {
        $tenant = Tenant::create([
            'name' => 'Biz Co', 'slug' => 'biz-co', 'plan' => 'business', 'subscription_status' => 'active',
        ]);

        $this->assertTrue($tenant->hasFeature('gps_tracking'));
        $this->assertTrue($tenant->hasFeature('fuel_tracking'));
        $this->assertTrue($tenant->hasFeature('export'));
        $this->assertTrue($tenant->hasFeature('ai_assistant'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TenantFeatureGatingTest`
Expected: FAIL — `Class "Database\Seeders\PlanSeeder" not found`.

- [ ] **Step 3: Create `Plan` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    protected $fillable = ['key', 'name', 'price', 'trial_days', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'feature_plan');
    }
}
```

- [ ] **Step 4: Create `Feature` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    public const GPS_TRACKING = 'gps_tracking';
    public const FUEL_TRACKING = 'fuel_tracking';
    public const EXPORT = 'export';
    public const AI_ASSISTANT = 'ai_assistant';

    /** key => display name, used by PlanSeeder to create rows. */
    public const NAMES = [
        self::GPS_TRACKING => 'Pelacakan GPS',
        self::FUEL_TRACKING => 'BBM & Solar (anti-kebocoran)',
        self::EXPORT => 'Export PDF/Excel',
        self::AI_ASSISTANT => 'Asisten AI',
    ];

    protected $fillable = ['key', 'name', 'description'];

    /**
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'feature_plan');
    }
}
```

- [ ] **Step 5: Create `PlanSeeder`**

```php
<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** Default plan config + which feature keys each plan includes. Editable later from /superadmin/plans. */
    private array $planDefaults = [
        'basic' => ['name' => 'Basic', 'price' => 150000, 'trial_days' => 14, 'sort_order' => 1, 'features' => []],
        'pro' => ['name' => 'Pro', 'price' => 350000, 'trial_days' => 14, 'sort_order' => 2, 'features' => [
            Feature::GPS_TRACKING, Feature::FUEL_TRACKING, Feature::EXPORT,
        ]],
        'business' => ['name' => 'Business', 'price' => 750000, 'trial_days' => 14, 'sort_order' => 3, 'features' => [
            Feature::GPS_TRACKING, Feature::FUEL_TRACKING, Feature::EXPORT, Feature::AI_ASSISTANT,
        ]],
    ];

    public function run(): void
    {
        foreach (Feature::NAMES as $key => $name) {
            Feature::updateOrCreate(['key' => $key], ['name' => $name]);
        }

        foreach ($this->planDefaults as $key => $data) {
            $plan = Plan::updateOrCreate(['key' => $key], [
                'name' => $data['name'],
                'price' => $data['price'],
                'trial_days' => $data['trial_days'],
                'sort_order' => $data['sort_order'],
            ]);

            $featureIds = Feature::whereIn('key', $data['features'])->pluck('id');
            $plan->features()->sync($featureIds);
        }
    }
}
```

- [ ] **Step 6: Wire `PlanSeeder` into `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, add `PlanSeeder::class` to the existing `$this->call([...])` list:

```php
        $this->call([
            PlanSeeder::class,
            CarSeeder::class,
            TestimonialSeeder::class,
        ]);
```

- [ ] **Step 7: Add `hasFeature()`/`currentPlan()` to `Tenant`**

In `app/Models/Tenant.php`, replace `public const PLANS = ['free', 'pro', 'enterprise'];` with:

```php
    public const PLANS = ['basic', 'pro', 'business'];
```

Add these methods (near `isActive()`):

```php
    public function currentPlan(): ?Plan
    {
        return Plan::where('key', $this->plan)->first();
    }

    public function hasFeature(string $featureKey): bool
    {
        return $this->currentPlan()?->features->contains('key', $featureKey) ?? false;
    }
```

- [ ] **Step 8: Add `super_admin` role to `User`**

In `app/Models/User.php`, add the constant and include it in `ROLES`:

```php
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLES = [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_DRIVER, self::ROLE_CUSTOMER, self::ROLE_SUPER_ADMIN];
```

Add a helper next to `isOwner()`:

```php
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --filter=TenantFeatureGatingTest`
Expected: PASS (3 tests)

- [ ] **Step 10: Run the full suite**

Run: `php artisan test`
Expected: PASS (all tests including pre-existing ones — `lajur` tenant is now `business`, which still has every feature the old tests exercise).

- [ ] **Step 11: Commit**

```bash
git add app/Models/Plan.php app/Models/Feature.php app/Models/Tenant.php app/Models/User.php \
        database/seeders/PlanSeeder.php database/seeders/DatabaseSeeder.php \
        tests/Feature/TenantFeatureGatingTest.php
git commit -m "feat: Plan/Feature models, PlanSeeder, Tenant::hasFeature, super_admin role"
```

---

### Task 3: `feature:<key>` route middleware

**Files:**
- Create: `app/Http/Middleware/EnsureFeatureEnabled.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/EnsureFeatureEnabledTest.php`

**Interfaces:**
- Consumes: `Tenant::hasFeature()` (Task 2), `TenantManager::current()` (existing).
- Produces: middleware alias `feature`, usable as `->middleware('feature:gps_tracking')`. On failure redirects to `route('admin.dashboard')` with `session('error')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureFeatureEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);

        Route::middleware(['web', 'auth', 'feature:ai_assistant'])
            ->get('/__test/gated', fn () => 'ok');
    }

    private function ownerFor(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_blocks_when_tenant_plan_lacks_the_feature(): void
    {
        $tenant = Tenant::create(['name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active']);

        $this->actingAs($this->ownerFor($tenant))
            ->get('/__test/gated')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_allows_when_tenant_plan_includes_the_feature(): void
    {
        $tenant = Tenant::create(['name' => 'Biz Co', 'slug' => 'biz-co', 'plan' => 'business', 'subscription_status' => 'active']);

        $this->actingAs($this->ownerFor($tenant))
            ->get('/__test/gated')
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EnsureFeatureEnabledTest`
Expected: FAIL — `Target class [feature] does not exist.`

- [ ] **Step 3: Create the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route unless the current tenant's plan includes the given feature.
 * Usage: ->middleware('feature:gps_tracking').
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $tenant = app(TenantManager::class)->current();

        if (! $tenant || ! $tenant->hasFeature($featureKey)) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Fitur ini tidak tersedia di plan Anda saat ini — upgrade untuk mengaktifkan.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the `feature` middleware alias**

In `bootstrap/app.php`, extend the existing `$middleware->alias([...])` call:

```php
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=EnsureFeatureEnabledTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnsureFeatureEnabled.php bootstrap/app.php tests/Feature/EnsureFeatureEnabledTest.php
git commit -m "feat: feature:<key> route middleware"
```

---

### Task 4: Trial expiry — `TrialGuard`, scheduled command, `IdentifyTenant` safety net

**Files:**
- Create: `app/Tenancy/TrialGuard.php`
- Create: `app/Console/Commands/CheckTrials.php`
- Modify: `routes/console.php`
- Modify: `app/Http/Middleware/IdentifyTenant.php`
- Test: `tests/Feature/TrialGuardTest.php`

**Interfaces:**
- Consumes: `Tenant` model (existing).
- Produces: `TrialGuard::settleIfExpired(Tenant $tenant): Tenant` (idempotent — downgrades an expired trial to `plan=basic, subscription_status=active`, no-op otherwise), artisan command `tenants:check-trial`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\TrialGuard;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_expired_trial_is_downgraded_to_basic(): void
    {
        $tenant = Tenant::create([
            'name' => 'Trial Co', 'slug' => 'trial-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->subDay(),
        ]);

        app(TrialGuard::class)->settleIfExpired($tenant);

        $this->assertSame('basic', $tenant->fresh()->plan);
        $this->assertSame('active', $tenant->fresh()->subscription_status);
    }

    public function test_active_trial_is_left_untouched(): void
    {
        $tenant = Tenant::create([
            'name' => 'Trial Co 2', 'slug' => 'trial-co-2', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->addDays(5),
        ]);

        app(TrialGuard::class)->settleIfExpired($tenant);

        $this->assertSame('business', $tenant->fresh()->plan);
        $this->assertSame('trial', $tenant->fresh()->subscription_status);
    }

    public function test_check_trial_command_downgrades_all_expired_tenants(): void
    {
        Tenant::create([
            'name' => 'Expired Co', 'slug' => 'expired-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('tenants:check-trial')->assertExitCode(0);

        $this->assertSame('basic', Tenant::where('slug', 'expired-co')->value('plan'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TrialGuardTest`
Expected: FAIL — `Class "App\Tenancy\TrialGuard" not found`.

- [ ] **Step 3: Create `TrialGuard`**

```php
<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Settles a tenant whose trial period has ended: drops it to the Basic plan
 * and marks the subscription active. No payment flow exists yet, so tenants
 * keep using the app on a reduced plan instead of being locked out.
 */
class TrialGuard
{
    public function settleIfExpired(Tenant $tenant): Tenant
    {
        if ($tenant->subscription_status !== 'trial') {
            return $tenant;
        }

        if (! $tenant->trial_ends_at || $tenant->trial_ends_at->isFuture()) {
            return $tenant;
        }

        $tenant->update(['plan' => 'basic', 'subscription_status' => 'active']);

        return $tenant;
    }
}
```

- [ ] **Step 4: Create `CheckTrials` command**

```php
<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TrialGuard;
use Illuminate\Console\Command;

class CheckTrials extends Command
{
    protected $signature = 'tenants:check-trial';

    protected $description = 'Downgrade tenants whose 14-day trial has ended to the Basic plan.';

    public function handle(TrialGuard $guard): int
    {
        $expired = Tenant::where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expired as $tenant) {
            $guard->settleIfExpired($tenant);
            $this->info("Tenant {$tenant->slug}: trial berakhir, diturunkan ke plan Basic.");
        }

        if ($expired->isEmpty()) {
            $this->info('Tidak ada tenant trial yang kedaluwarsa.');
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Schedule the command**

In `routes/console.php`, add below the existing `mileage:sync` schedule:

```php
// Daily: downgrade tenants whose 14-day trial has ended.
Schedule::command('tenants:check-trial')->dailyAt('02:00');
```

- [ ] **Step 6: Wire the safety net into `IdentifyTenant`**

In `app/Http/Middleware/IdentifyTenant.php`, add the import and settle the tenant before storing it:

```php
use App\Tenancy\TrialGuard;
```

```php
    public function handle(Request $request, Closure $next): Response
    {
        $manager = app(TenantManager::class);

        $tenant = $this->fromUser($request)
            ?? $this->fromSubdomain($request)
            ?? $this->default();

        if ($tenant) {
            $tenant = app(TrialGuard::class)->settleIfExpired($tenant);
        }

        $manager->set($tenant);

        return $next($request);
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=TrialGuardTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Tenancy/TrialGuard.php app/Console/Commands/CheckTrials.php \
        routes/console.php app/Http/Middleware/IdentifyTenant.php \
        tests/Feature/TrialGuardTest.php
git commit -m "feat: trial expiry auto-downgrade (TrialGuard + tenants:check-trial)"
```

---

### Task 5: Super admin — Plan management (controller + routes)

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/PlanController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SuperAdminPlanTest.php`

**Interfaces:**
- Consumes: `Plan`, `Feature` models (Task 2), `role:super_admin` (generic `EnsureUserHasRole`, no code change needed — it already accepts any role string).
- Produces: routes `superadmin.plans.index` (GET `/superadmin/plans`), `superadmin.plans.update` (PATCH `/superadmin/plans/{plan}`), `superadmin.plans.features` (PATCH `/superadmin/plans/{plan}/features`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_non_super_admin_cannot_access_plans_page(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/superadmin/plans')->assertForbidden();
    }

    public function test_super_admin_can_update_plan_price_and_trial_days(): void
    {
        $plan = Plan::where('key', 'pro')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/plans/{$plan->id}", ['price' => 399000, 'trial_days' => 14])
            ->assertRedirect();

        $this->assertSame(399000, $plan->fresh()->price);
    }

    public function test_super_admin_can_toggle_plan_features(): void
    {
        $plan = Plan::where('key', 'basic')->firstOrFail();
        $feature = Feature::where('key', 'gps_tracking')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/plans/{$plan->id}/features", ['features' => [$feature->id]])
            ->assertRedirect();

        $this->assertTrue($plan->fresh()->features->contains('key', 'gps_tracking'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SuperAdminPlanTest`
Expected: FAIL — `404 Not Found` (routes don't exist yet).

- [ ] **Step 3: Create `PlanController`**

```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::with('features')->orderBy('sort_order')->get();
        $features = Feature::orderBy('name')->get();

        return view('superadmin.plans.index', compact('plans', 'features'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0'],
        ]);

        $plan->update($data);

        return back()->with('success', "Plan {$plan->name} diperbarui.");
    }

    public function updateFeatures(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'features' => ['array'],
            'features.*' => ['integer', 'exists:features,id'],
        ]);

        $plan->features()->sync($data['features'] ?? []);

        return back()->with('success', "Fitur plan {$plan->name} diperbarui.");
    }
}
```

- [ ] **Step 4: Register super admin plan routes**

In `routes/web.php`, add the import near the other `Admin` controller imports:

```php
use App\Http\Controllers\SuperAdmin\PlanController as SuperAdminPlanController;
```

Add a new route group after the closing `});` of the existing `admin` prefix group (before the `Driver area` comment block):

```php
/*
|--------------------------------------------------------------------------
| Super admin (platform owner — auth + role:super_admin)
|--------------------------------------------------------------------------
*/
Route::prefix('superadmin')
    ->name('superadmin.')
    ->middleware(['auth', 'role:super_admin'])
    ->group(function () {
        Route::get('plans', [SuperAdminPlanController::class, 'index'])->name('plans.index');
        Route::patch('plans/{plan}', [SuperAdminPlanController::class, 'update'])->name('plans.update');
        Route::patch('plans/{plan}/features', [SuperAdminPlanController::class, 'updateFeatures'])->name('plans.features');
    });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SuperAdminPlanTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SuperAdmin/PlanController.php routes/web.php tests/Feature/SuperAdminPlanTest.php
git commit -m "feat: super admin plan management endpoints"
```

---

### Task 6: Super admin — Tenant management (controller + routes)

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/TenantController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SuperAdminTenantTest.php`

**Interfaces:**
- Consumes: `Plan`, `Tenant` models.
- Produces: routes `superadmin.tenants.index` (GET `/superadmin/tenants`), `superadmin.tenants.store` (POST `/superadmin/tenants`), `superadmin.tenants.plan` (PATCH `/superadmin/tenants/{tenant}/plan`). `store()` always creates the tenant on `plan=business, subscription_status=trial, trial_ends_at = now()+business.trial_days`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_creating_a_tenant_starts_a_14_day_business_trial(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/superadmin/tenants', ['name' => 'Rental Baru', 'slug' => 'rental-baru'])
            ->assertRedirect();

        $tenant = Tenant::where('slug', 'rental-baru')->firstOrFail();

        $this->assertSame('business', $tenant->plan);
        $this->assertSame('trial', $tenant->subscription_status);
        $this->assertTrue($tenant->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));
    }

    public function test_super_admin_can_change_a_tenants_plan(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/tenants/{$tenant->id}/plan", ['plan' => 'basic'])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertSame('basic', $tenant->plan);
        $this->assertSame('active', $tenant->subscription_status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SuperAdminTenantTest`
Expected: FAIL — `404 Not Found`.

- [ ] **Step 3: Create `TenantController`**

```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderByDesc('created_at')->get();
        $plans = Plan::orderBy('sort_order')->get();

        return view('superadmin.tenants.index', compact('tenants', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'alpha_dash'],
        ]);

        $businessPlan = Plan::where('key', 'business')->firstOrFail();

        Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'plan' => 'business',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays($businessPlan->trial_days),
        ]);

        return back()->with('success', 'Tenant baru dibuat dengan trial 14 hari (plan Business).');
    }

    public function updatePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(Plan::pluck('key'))],
        ]);

        $tenant->update([
            'plan' => $data['plan'],
            'subscription_status' => 'active',
        ]);

        return back()->with('success', "Plan tenant {$tenant->name} diubah ke {$data['plan']}.");
    }
}
```

- [ ] **Step 4: Register super admin tenant routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\SuperAdmin\TenantController as SuperAdminTenantController;
```

Add these routes inside the `superadmin` group created in Task 5, after the plan routes:

```php
        Route::get('tenants', [SuperAdminTenantController::class, 'index'])->name('tenants.index');
        Route::post('tenants', [SuperAdminTenantController::class, 'store'])->name('tenants.store');
        Route::patch('tenants/{tenant}/plan', [SuperAdminTenantController::class, 'updatePlan'])->name('tenants.plan');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SuperAdminTenantTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SuperAdmin/TenantController.php routes/web.php tests/Feature/SuperAdminTenantTest.php
git commit -m "feat: super admin tenant creation + manual plan assignment"
```

---

### Task 7: Super admin UI (layout + views)

**Files:**
- Create: `resources/views/layouts/superadmin.blade.php`
- Create: `resources/views/superadmin/plans/index.blade.php`
- Create: `resources/views/superadmin/tenants/index.blade.php`
- Test: `tests/Feature/SuperAdminUiTest.php`

**Interfaces:**
- Consumes: `PlanController::index()`'s `$plans`/`$features`, `TenantController::index()`'s `$tenants`/`$plans` (Tasks 5–6). Existing `<x-icon name="...">` component, `@csrf`/`@method('PATCH')` pattern used across `resources/views/admin/*`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_plans_page_lists_all_three_plans(): void
    {
        $response = $this->actingAs($this->superAdmin())->get('/superadmin/plans');

        $response->assertOk();
        $response->assertSee('Basic');
        $response->assertSee('Pro');
        $response->assertSee('Business');
    }

    public function test_tenants_page_lists_existing_tenants(): void
    {
        $response = $this->actingAs($this->superAdmin())->get('/superadmin/tenants');

        $response->assertOk();
        $response->assertSee('Lajur — Rental Mobil Premium');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SuperAdminUiTest`
Expected: FAIL — `View [superadmin.plans.index] not found.`

- [ ] **Step 3: Create `layouts/superadmin.blade.php`**

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin') — Lajur Platform</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="{{ route('superadmin.plans.index') }}" class="brand">
            <span class="mark"><x-icon name="route" /></span> Lajur Platform
        </a>
        <nav class="admin-nav" aria-label="Menu super admin">
            <a href="{{ route('superadmin.plans.index') }}" class="{{ request()->routeIs('superadmin.plans.*') ? 'active' : '' }}">
                <x-icon name="gauge" /> Plans &amp; Fitur
            </a>
            <a href="{{ route('superadmin.tenants.index') }}" class="{{ request()->routeIs('superadmin.tenants.*') ? 'active' : '' }}">
                <x-icon name="users" /> Tenant
            </a>
        </nav>
        <div class="sidebar-foot">
            <div class="sidebar-user">
                Masuk sebagai
                <strong>{{ auth()->user()->name }}</strong>
            </div>
            <div class="sidebar-actions">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="sidebar-btn danger">
                        <x-icon name="logout" /> <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <div>
                <span class="crumb">@yield('crumb', 'Lajur Platform')</span>
                <h1>@yield('heading', 'Super Admin')</h1>
            </div>
        </div>

        <div class="admin-content">
            @if (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
```

- [ ] **Step 4: Create `superadmin/plans/index.blade.php`**

```blade
@extends('layouts.superadmin')

@section('title', 'Plans & Fitur')
@section('crumb', 'Super Admin')
@section('heading', 'Plans & Fitur')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px">
    @foreach ($plans as $plan)
        <div class="card" style="padding:20px;border:1px solid #e2e2e2;border-radius:12px">
            <h2>{{ $plan->name }}</h2>

            <form method="POST" action="{{ route('superadmin.plans.update', $plan) }}" style="margin-bottom:16px">
                @csrf @method('PATCH')
                <label>Harga (Rp/bulan)
                    <input type="number" name="price" value="{{ $plan->price }}" min="0">
                </label>
                <label>Masa trial (hari)
                    <input type="number" name="trial_days" value="{{ $plan->trial_days }}" min="0">
                </label>
                <button type="submit">Simpan harga</button>
            </form>

            <form method="POST" action="{{ route('superadmin.plans.features', $plan) }}">
                @csrf @method('PATCH')
                @foreach ($features as $feature)
                    <label style="display:block">
                        <input type="checkbox" name="features[]" value="{{ $feature->id }}"
                            {{ $plan->features->contains('id', $feature->id) ? 'checked' : '' }}>
                        {{ $feature->name }}
                    </label>
                @endforeach
                <button type="submit">Simpan fitur</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
```

- [ ] **Step 5: Create `superadmin/tenants/index.blade.php`**

```blade
@extends('layouts.superadmin')

@section('title', 'Tenant')
@section('crumb', 'Super Admin')
@section('heading', 'Tenant')

@section('content')
<div style="margin-bottom:24px">
    <h2>Tambah tenant baru</h2>
    <form method="POST" action="{{ route('superadmin.tenants.store') }}">
        @csrf
        <label>Nama <input type="text" name="name" required></label>
        <label>Slug <input type="text" name="slug" required placeholder="mis. rental-baru"></label>
        <button type="submit">Buat tenant (trial 14 hari, Business)</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr><th>Nama</th><th>Plan</th><th>Status</th><th>Trial berakhir</th><th>Ubah plan</th></tr>
    </thead>
    <tbody>
        @foreach ($tenants as $tenant)
            <tr>
                <td>{{ $tenant->name }}</td>
                <td>{{ $tenant->plan }}</td>
                <td>{{ $tenant->subscription_status }}</td>
                <td>{{ $tenant->trial_ends_at?->format('d M Y') ?? '-' }}</td>
                <td>
                    <form method="POST" action="{{ route('superadmin.tenants.plan', $tenant) }}" style="display:flex;gap:8px">
                        @csrf @method('PATCH')
                        <select name="plan">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->key }}" {{ $tenant->plan === $plan->key ? 'selected' : '' }}>
                                    {{ $plan->name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit">Simpan</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SuperAdminUiTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/superadmin.blade.php \
        resources/views/superadmin/plans/index.blade.php \
        resources/views/superadmin/tenants/index.blade.php \
        tests/Feature/SuperAdminUiTest.php
git commit -m "feat: super admin plans & tenants UI"
```

---

### Task 8: Wire gating into existing tenant routes + sidebar, full regression

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/FeatureGatingSidebarTest.php`

**Interfaces:**
- Consumes: `feature:<key>` middleware (Task 3), `Tenant::hasFeature()` (Task 2).
- Produces: `admin.tracking*` routes require `gps_tracking`; `admin.fuel.*` require `fuel_tracking`; `admin.export.download` requires `export`; `admin.assistant*` require `ai_assistant`. `admin.reports*` stays ungated (basic reports are on every plan). Sidebar hides the corresponding nav links when the tenant lacks the feature.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGatingSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function ownerFor(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_basic_plan_tenant_does_not_see_gated_nav_items(): void
    {
        $tenant = Tenant::create(['name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active']);

        $response = $this->actingAs($this->ownerFor($tenant))->get('/admin');

        $response->assertOk();
        $response->assertDontSee('Pelacakan');
        $response->assertDontSee('Asisten AI');
    }

    public function test_basic_plan_tenant_is_redirected_away_from_tracking(): void
    {
        $tenant = Tenant::create(['name' => 'Basic Co 2', 'slug' => 'basic-co-2', 'plan' => 'basic', 'subscription_status' => 'active']);

        $this->actingAs($this->ownerFor($tenant))
            ->get('/admin/tracking')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_business_plan_tenant_sees_gated_nav_items(): void
    {
        $tenant = Tenant::create(['name' => 'Biz Co', 'slug' => 'biz-co', 'plan' => 'business', 'subscription_status' => 'active']);

        $response = $this->actingAs($this->ownerFor($tenant))->get('/admin');

        $response->assertOk();
        $response->assertSee('Pelacakan');
        $response->assertSee('Asisten AI');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FeatureGatingSidebarTest`
Expected: FAIL — nav items and `/admin/tracking` are currently visible/accessible to every plan.

- [ ] **Step 3: Wrap the gated route groups in `routes/web.php`**

Replace the tracking/fuel/export/assistant block inside the `admin` prefix group (currently lines ~90–113) with:

```php
        // Unit tracking (GPS + map) — Pro & Business plans only
        Route::middleware('feature:gps_tracking')->group(function () {
            Route::get('tracking', [TrackingController::class, 'index'])->name('tracking');
            Route::get('tracking/live', [TrackingController::class, 'live'])->middleware('throttle:60,1')->name('tracking.live');
            Route::get('tracking/history', [TrackingController::class, 'history'])->middleware('throttle:60,1')->name('tracking.history');
        });

        // Fuel (BBM/solar) logs & leak indicators — Pro & Business plans only
        Route::middleware('feature:fuel_tracking')->group(function () {
            Route::get('fuel', [FuelController::class, 'index'])->name('fuel.index');
            Route::get('fuel/create', [FuelController::class, 'create'])->name('fuel.create');
            Route::post('fuel', [FuelController::class, 'store'])->name('fuel.store');
            Route::delete('fuel/{fuelLog}', [FuelController::class, 'destroy'])->name('fuel.destroy');
        });

        // Operational data export (PDF/Excel) — Pro & Business plans only
        Route::get('export/{dataset}/{format}', [ExportController::class, 'download'])
            ->middleware('feature:export')
            ->where('format', 'xlsx|pdf')
            ->name('export.download');

        // Analytics & reports (every plan)
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

        // AI business assistant — Business plan only
        Route::middleware('feature:ai_assistant')->group(function () {
            Route::get('assistant', [AssistantController::class, 'index'])->name('assistant');
            Route::post('assistant', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('assistant.ask');
            Route::get('assistant/insight', [AssistantController::class, 'insight'])->middleware('throttle:30,1')->name('assistant.insight');
        });
```

- [ ] **Step 4: Gate the sidebar in `resources/views/layouts/admin.blade.php`**

Replace the block that renders the Pelacakan/BBM/Laporan/Asisten AI links (currently lines ~41–52) with:

```blade
            @php($currentTenant = app(\App\Tenancy\TenantManager::class)->current())
            @if ($currentTenant?->hasFeature('gps_tracking'))
            <a href="{{ route('admin.tracking') }}" class="{{ request()->routeIs('admin.tracking') ? 'active' : '' }}">
                <x-icon name="pin" /> Pelacakan
            </a>
            @endif
            @if ($currentTenant?->hasFeature('fuel_tracking'))
            <a href="{{ route('admin.fuel.index') }}" class="{{ request()->routeIs('admin.fuel.*') ? 'active' : '' }}">
                <x-icon name="fuel" /> BBM &amp; Solar
            </a>
            @endif
            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports') ? 'active' : '' }}">
                <x-icon name="gauge" /> Laporan
            </a>
            @if ($currentTenant?->hasFeature('ai_assistant'))
            <a href="{{ route('admin.assistant') }}" class="{{ request()->routeIs('admin.assistant') ? 'active' : '' }}">
                <x-icon name="sparkle" /> Asisten AI
            </a>
            @endif
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=FeatureGatingSidebarTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full suite (regression check)**

Run: `php artisan test`
Expected: PASS — every pre-existing test (`AiAssistantTest`, `TrackingTest`, `FuelTrackingTest`, `ExportTest`, etc.) still passes because the `lajur` tenant is on `business` (Task 1 backfill), which includes every gated feature.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/layouts/admin.blade.php tests/Feature/FeatureGatingSidebarTest.php
git commit -m "feat: enforce plan-based feature gating on tracking/fuel/export/assistant"
```

---

## Post-plan manual check (not automated)

- [ ] Run `php artisan migrate` and `php artisan db:seed --class=PlanSeeder` against local MySQL (Laragon), then log in as a super admin (create one via `php artisan tinker`: `User::create(['name'=>'Platform Owner','email'=>'platform@lajur.id','password'=>Hash::make('password'),'role'=>User::ROLE_SUPER_ADMIN])`) and click through `/superadmin/plans` and `/superadmin/tenants` in a browser to confirm the forms save correctly.
- [ ] Manually create a Basic-plan test tenant from `/superadmin/tenants`, log in as its owner, and confirm Pelacakan/BBM/Asisten AI are hidden from the sidebar and `/admin/tracking` redirects with the flash message.
