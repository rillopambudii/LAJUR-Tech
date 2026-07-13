# In-Dashboard Subscription Upgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an existing tenant (any status: trial, active, or downgraded-Basic) pay to (re)subscribe from inside their own dashboard, without ever granting plan access before Midtrans confirms payment.

**Architecture:** A new nullable `tenants.pending_plan` column carries the target plan through the payment round-trip without touching `tenants.plan` until the webhook confirms `paid`. `SubscriptionCheckout::createCheckout()` gains an optional `$finishUrl` parameter so the same Midtrans call site serves both the public signup flow (unmodified default) and this new in-dashboard flow. `PaymentController::activateSubscription()` gains one additive branch: promote `pending_plan` into `plan` on activation, a no-op for the existing new-tenant-signup path where `pending_plan` is never set.

**Tech Stack:** Laravel 12, Blade, PHPUnit, existing Midtrans Snap integration.

## Global Constraints

- `tenants.plan` and `tenants.subscription_status` MUST NOT change until the webhook confirms `status === 'paid'`. Initiating a checkout only ever sets `payment_ref` and `pending_plan`.
- The existing public-signup paid flow (`SignupController::storePaid`, `SubscriptionCheckout`, `PaymentController::webhook`) must keep passing ALL of its existing tests **unmodified** — every change here is additive (new optional parameter with the old default, new branch gated on `pending_plan` being non-null).
- `/admin/langganan` has NO `feature:` middleware — reachable on every plan.
- Trial-vs-paid distinction collapses on activation: paying always results in `subscription_status=active`, `subscription_ends_at=+30 days from now`, `plan=<chosen>` — no leftover-trial-day math.
- Run `php artisan test` after every task; suite must stay green (baseline 154 tests as of this plan).

---

### Task 1: `pending_plan` column + `SubscriptionCheckout` finishUrl parameter + webhook promotion

**Files:**
- Create: `database/migrations/2026_07_13_100000_add_pending_plan_to_tenants.php`
- Modify: `app/Models/Tenant.php`
- Modify: `app/Payments/SubscriptionCheckout.php`
- Modify: `app/Http/Controllers/PaymentController.php`
- Test: `tests/Feature/SubscriptionUpgradeWebhookTest.php`

**Interfaces:**
- Produces: `tenants.pending_plan` (nullable string), added to `Tenant::$fillable`.
- `SubscriptionCheckout::createCheckout(Tenant $tenant, Plan $plan, ?string $finishUrl = null): ?string` — when `$finishUrl` is null, behaves exactly as today (`route('signup.finish')`); when provided, used as `callbacks.finish` instead.
- `PaymentController::activateSubscription()`: after setting `subscription_status`/`subscription_ends_at`, if `$tenant->pending_plan` is set, also sets `plan = pending_plan` and `pending_plan = null` in the same `update()` call.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionUpgradeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payment.gateway', 'midtrans');
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function signedPayload(string $orderId, string $transactionStatus, string $grossAmount = '350000.00'): array
    {
        $statusCode = '200';
        $serverKey = 'SB-Mid-server-TEST';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return [
            'order_id' => $orderId, 'status_code' => $statusCode, 'gross_amount' => $grossAmount,
            'signature_key' => $signature, 'transaction_status' => $transactionStatus, 'fraud_status' => 'accept',
        ];
    }

    public function test_webhook_promotes_pending_plan_on_upgrade_payment(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Co', 'slug' => 'existing-co', 'plan' => 'basic',
            'subscription_status' => 'active', 'pending_plan' => 'pro',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-9990001']);

        $payload = $this->signedPayload($tenant->payment_ref, 'settlement');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('pro', $tenant->plan);
        $this->assertNull($tenant->pending_plan);
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertTrue($tenant->subscription_ends_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_webhook_does_not_touch_plan_when_pending_plan_absent(): void
    {
        // Mirrors the existing new-signup case: plan already correct at creation, no pending_plan set.
        $tenant = Tenant::create([
            'name' => 'New Co', 'slug' => 'new-co', 'plan' => 'business',
            'subscription_status' => 'pending_payment',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-9990002']);

        $payload = $this->signedPayload($tenant->payment_ref, 'settlement');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('business', $tenant->plan);
        $this->assertNull($tenant->pending_plan);
    }

    public function test_pending_plan_not_promoted_when_payment_status_is_not_paid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Waiting Co', 'slug' => 'waiting-co', 'plan' => 'basic',
            'subscription_status' => 'active', 'pending_plan' => 'business',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-9990003']);

        $payload = $this->signedPayload($tenant->payment_ref, 'pending');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('basic', $tenant->plan);
        $this->assertSame('business', $tenant->pending_plan);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SubscriptionUpgradeWebhookTest`
Expected: FAIL — unknown column `pending_plan`.

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
            $table->string('pending_plan')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('pending_plan');
        });
    }
};
```

- [ ] **Step 4: Add `pending_plan` to `Tenant::$fillable`**

In `app/Models/Tenant.php`, add `'pending_plan',` right after the existing `'plan',` entry in `$fillable`.

- [ ] **Step 5: Add the optional `$finishUrl` parameter to `SubscriptionCheckout::createCheckout()`**

In `app/Payments/SubscriptionCheckout.php`, change the method signature and the `callbacks.finish` line:

```php
    public function createCheckout(Tenant $tenant, Plan $plan, ?string $finishUrl = null): ?string
    {
```

```php
            'callbacks' => [
                'finish' => $finishUrl ?? route('signup.finish'),
            ],
```

No other change to this file. This keeps every existing call site (`SignupController::storePaid()`, which calls `createCheckout($tenant, $plan)` with two args) byte-identical in behavior.

- [ ] **Step 6: Extend `PaymentController::activateSubscription()`**

In `app/Http/Controllers/PaymentController.php`, replace the body of `activateSubscription()`:

```php
    /** Activates a tenant's paid subscription. No-op for any status other than 'paid' or if the order_id doesn't match a pending tenant. */
    private function activateSubscription(string $orderId, string $status): void
    {
        if ($status !== 'paid') {
            return;
        }

        $tenant = Tenant::where('payment_ref', $orderId)->first();

        if (! $tenant) {
            return;
        }

        $data = [
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addDays(30),
        ];

        // Set only by the in-dashboard upgrade flow (Task 2). Absent for the
        // new-tenant signup flow, where `plan` is already correct from
        // creation — this branch is a no-op there.
        if ($tenant->pending_plan) {
            $data['plan'] = $tenant->pending_plan;
            $data['pending_plan'] = null;
        }

        $tenant->update($data);
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=SubscriptionUpgradeWebhookTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Run the full suite (regression check on existing signup/webhook tests)**

Run: `php artisan test --filter=SubscriptionCheckoutTest && php artisan test --filter=SubscriptionWebhookTest && php artisan test --filter=SignupPaidTest && php artisan test`
Expected: all four filtered runs green, full suite 154 existing + 3 new = 157 passing.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_13_100000_add_pending_plan_to_tenants.php \
        app/Models/Tenant.php app/Payments/SubscriptionCheckout.php \
        app/Http/Controllers/PaymentController.php \
        tests/Feature/SubscriptionUpgradeWebhookTest.php
git commit -m "feat: pending_plan column - webhook promotes it on payment, existing signup flow untouched"
```

---

### Task 2: `/admin/langganan` page (status + plan picker + checkout initiation + finish page)

**Files:**
- Create: `app/Http/Controllers/Admin/SubscriptionController.php`
- Create: `resources/views/admin/subscription/index.blade.php`
- Create: `resources/views/admin/subscription/finish.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/InAppSubscriptionUpgradeTest.php`

**Interfaces:**
- Consumes: `SubscriptionCheckout::createCheckout()` (Task 1's 3-arg form), `Plan` model, `TenantManager::current()`.
- Produces: `GET admin/langganan` (`admin.subscription.index`), `POST admin/langganan/{plan}` (`admin.subscription.store`), `GET admin/langganan/selesai` (`admin.subscription.finish`). Sidebar "Langganan" link, ungated.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InAppSubscriptionUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->tenant = Tenant::create([
            'name' => 'Existing Co', 'slug' => 'existing-co', 'plan' => 'basic',
            'subscription_status' => 'active',
        ]);
        app(TenantManager::class)->set($this->tenant);
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function owner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@existing-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_subscription_page_reachable_on_basic_plan(): void
    {
        $this->actingAs($this->owner())->get('/admin/langganan')
            ->assertOk()
            ->assertSee('Pro')
            ->assertSee('Business');
    }

    public function test_choosing_a_plan_sets_pending_plan_without_changing_active_plan(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/upgrade123',
            ]),
        ]);

        $response = $this->actingAs($this->owner())->post('/admin/langganan/pro');

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/upgrade123');

        $this->tenant->refresh();
        $this->assertSame('basic', $this->tenant->plan); // untouched until webhook confirms
        $this->assertSame('pro', $this->tenant->pending_plan);
        $this->assertNotNull($this->tenant->payment_ref);
    }

    public function test_failed_checkout_rolls_back_pending_plan(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([], 500),
        ]);

        $this->actingAs($this->owner())->post('/admin/langganan/pro');

        $this->tenant->refresh();
        $this->assertNull($this->tenant->pending_plan);
        $this->assertNull($this->tenant->payment_ref);
    }

    public function test_driver_cannot_access_subscription_page(): void
    {
        $driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sopir', 'email' => 'd@existing-co.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER,
        ]);

        $this->actingAs($driver)->get('/admin/langganan')->assertForbidden();
    }

    public function test_finish_page_shows_processing_while_pending(): void
    {
        $this->tenant->update(['pending_plan' => 'pro', 'payment_ref' => 'LAJUR-SUB-x-1']);

        $this->actingAs($this->owner())->get('/admin/langganan/selesai')
            ->assertOk()
            ->assertSee('Menunggu');
    }

    public function test_finish_page_shows_success_when_activated(): void
    {
        $this->tenant->update(['plan' => 'pro', 'pending_plan' => null]);

        $this->actingAs($this->owner())->get('/admin/langganan/selesai')
            ->assertOk()
            ->assertSee('aktif');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InAppSubscriptionUpgradeTest`
Expected: FAIL — 404 on `/admin/langganan`.

- [ ] **Step 3: Create `SubscriptionController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Payments\SubscriptionCheckout;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function index(TenantManager $manager): View
    {
        $tenant = $manager->current();
        $plans = Plan::orderBy('sort_order')->get();

        return view('admin.subscription.index', compact('tenant', 'plans'));
    }

    public function store(string $planKey, TenantManager $manager, SubscriptionCheckout $checkout): RedirectResponse
    {
        $tenant = $manager->current();
        $plan = Plan::where('key', $planKey)->firstOrFail();

        // Explicit begin/commit/rollback (not DB::transaction(), which only
        // rolls back on a thrown exception) — same pattern already proven in
        // SignupController::storePaid() for this exact "checkout may return
        // null" failure mode.
        DB::beginTransaction();

        try {
            $tenant->update(['pending_plan' => $plan->key]);
            $url = $checkout->createCheckout($tenant, $plan, route('admin.subscription.finish'));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (! $url) {
            DB::rollBack();

            return redirect()->route('admin.subscription.index')
                ->with('error', 'Pembayaran sedang tidak tersedia, silakan coba lagi nanti.');
        }

        DB::commit();

        return redirect($url);
    }

    public function finish(TenantManager $manager): View
    {
        $tenant = $manager->current();

        return view('admin.subscription.finish', compact('tenant'));
    }
}
```

Use this corrected version of `store()` in the actual file — the first
version above is illustrative only and must not be transcribed literally.

- [ ] **Step 4: Create `resources/views/admin/subscription/index.blade.php`**

```blade
@extends('layouts.admin')

@section('title', 'Langganan')
@section('crumb', 'Akun')
@section('heading', 'Langganan')

@section('content')
<div class="panel">
    <div class="panel-head">
        <h2>Plan Saat Ini</h2>
        <span class="tag">{{ ucfirst($tenant->plan) }} · {{ $tenant->subscription_status }}</span>
    </div>
    <div class="panel-body">
        @if ($tenant->subscription_status === 'trial')
            <p>Masa trial Anda berakhir pada <strong>{{ $tenant->trial_ends_at?->format('d M Y') }}</strong>.</p>
        @elseif ($tenant->subscription_ends_at)
            <p>Langganan Anda aktif hingga <strong>{{ $tenant->subscription_ends_at->format('d M Y') }}</strong>.</p>
        @else
            <p>Anda saat ini menggunakan plan Basic (gratis).</p>
        @endif
    </div>
</div>

<div class="pricing-grid" style="margin-top:24px">
    @foreach ($plans as $plan)
        <div class="plan-card @if ($plan->key === 'pro') is-featured @endif">
            @if ($plan->key === 'pro')
                <span class="plan-badge">Paling Populer</span>
            @endif
            <h2 class="plan-name">{{ $plan->name }}</h2>
            <div class="plan-price">
                <span class="amount">Rp {{ number_format($plan->price, 0, ',', '.') }}</span>
                <span class="per">/ bulan</span>
            </div>
            <div class="plan-foot">
                @if ($tenant->plan === $plan->key && $tenant->subscription_status === 'active')
                    <button type="button" class="btn btn-ghost btn-block" disabled>Plan Aktif Anda</button>
                @else
                    <form method="POST" action="{{ route('admin.subscription.store', $plan->key) }}">
                        @csrf
                        <button type="submit" class="btn @if ($plan->key === 'pro') btn-primary @else btn-ghost @endif btn-block">
                            Pilih {{ $plan->name }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
```

- [ ] **Step 5: Create `resources/views/admin/subscription/finish.blade.php`**

```blade
@extends('layouts.admin')

@section('title', 'Status Langganan')
@section('crumb', 'Akun')
@section('heading', 'Status Langganan')

@section('content')
<div class="panel" style="max-width:560px">
    <div class="panel-body">
        @if ($tenant->pending_plan)
            <h2>Menunggu Konfirmasi Pembayaran</h2>
            <p>Pembayaran Anda sedang diproses. Halaman ini akan menampilkan status terbaru, silakan refresh dalam beberapa saat.</p>
            <a href="{{ route('admin.subscription.finish') }}" class="btn btn-ghost">Refresh Status</a>
        @else
            <h2>Langganan Aktif</h2>
            <p>Plan {{ ucfirst($tenant->plan) }} Anda sudah aktif.</p>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Ke Dashboard</a>
        @endif
    </div>
</div>
@endsection
```

- [ ] **Step 6: Register routes**

In `routes/web.php`, add the import `use App\Http\Controllers\Admin\SubscriptionController;` and inside the `admin` group (after the `situs` routes added by the tenant-branding feature, or after Messages if that feature isn't present yet — place alongside the other ungated admin routes):

```php
        // Lanjut berlangganan / upgrade plan — semua plan, tanpa feature gate
        Route::get('langganan', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('langganan/{plan}', [SubscriptionController::class, 'store'])->name('subscription.store');
        Route::get('langganan/selesai', [SubscriptionController::class, 'finish'])->name('subscription.finish');
```

Route ordering note: `langganan/selesai` (literal) must be registered before
any wildcard route sharing the `langganan/` prefix — it already is here since
`{plan}` is a POST-only route and `selesai` is GET-only, so there's no
literal/wildcard collision to worry about, but keep this order regardless for
clarity.

- [ ] **Step 7: Add sidebar nav item**

In `resources/views/layouts/admin.blade.php`, add near the other ungated links (e.g. after "Laporan" or alongside "Situs" if present):

```blade
            <a href="{{ route('admin.subscription.index') }}" class="{{ request()->routeIs('admin.subscription.*') ? 'active' : '' }}">
                <x-icon name="wallet" /> Langganan
            </a>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=InAppSubscriptionUpgradeTest`
Expected: PASS (6 tests)

- [ ] **Step 9: Run full suite**

Run: `php artisan test`
Expected: 157 existing (after Task 1) + 6 new = 163 passing.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/SubscriptionController.php \
        resources/views/admin/subscription/ routes/web.php \
        resources/views/layouts/admin.blade.php \
        tests/Feature/InAppSubscriptionUpgradeTest.php
git commit -m "feat: halaman Langganan di dashboard tenant untuk upgrade/resubscribe"
```

---

## Post-plan manual check (not automated)

- [ ] `php artisan migrate` on the real DB.
- [ ] Log in as an existing tenant owner (any plan), open `/admin/langganan`, click a plan, confirm redirect to a real Midtrans sandbox checkout, and confirm the tenant's `plan` column does NOT change until a completed sandbox payment's webhook notification lands.
- [ ] Manually simulate the "3 days into trial, pay early" scenario: create a trial tenant, complete a sandbox payment, confirm `subscription_status` flips to `active` with a fresh 30-day `subscription_ends_at` (not extended by remaining trial days).
