# Public Signup & Plan Payment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a public `/daftar` page where a prospective customer picks Basic/Pro/Business (pays via Midtrans Snap) or a free 14-day trial, and gets a working tenant + login as a result.

**Architecture:** Two new nullable columns on `tenants` (`payment_ref`, `subscription_ends_at`) plus a new `pending_payment` status value. A shared `SignupController` handles both the trial and paid paths with near-identical form/validation logic, diverging only at the end: trial creates-and-logs-in immediately (reusing the exact mechanics `SuperAdmin\TenantController::store()` already uses), paid creates-in-pending-state and hands off to a new, small `SubscriptionCheckout` class that calls Midtrans Snap directly — deliberately NOT reusing the `Booking`-shaped `PaymentGateway` interface, to keep the existing booking payment code completely untouched. `PaymentController::webhook()` gains one `order_id`-prefix branch to activate a tenant instead of a booking. `TrialGuard` gains a sibling check for lapsed paid subscriptions, funneled through the same `IdentifyTenant` safety net.

**Tech Stack:** Laravel 12, Blade, MySQL/SQLite, PHPUnit feature tests, Midtrans Snap API (existing sandbox/production config).

## Global Constraints

- Money is plain rupiah integers, matching every other amount in this codebase.
- `App\Payments\MidtransGateway`, `App\Payments\PaymentGateway`, `App\Http\Controllers\PaymentController`'s existing booking-handling code, and all existing booking payment tests must be unmodified in behavior — only additive changes (one new `if` branch in `webhook()`).
- Follow existing codebase conventions: controllers/requests/views styled like `SuperAdmin\TenantController`, `DriverRequest` (password validation: `required|string|min:8|max:255` on create), `resources/views/layouts/public.blade.php` (public pages), tests in `tests/Feature/*Test.php` using `RefreshDatabase`.
- No recurring/auto-billing, no billing history page, no expiry reminder emails — out of scope.
- A lapsed paid subscription (30 days, unrenewed) downgrades to `plan=basic` (not suspended) — same non-punitive behavior as expired trials.
- Run `php artisan test` after every task; the full suite (119 tests as of this plan's start) must stay green before moving to the next task.

---

### Task 1: `tenants` schema — payment_ref, subscription_ends_at, pending_payment status

**Files:**
- Create: `database/migrations/2026_07_12_100000_add_subscription_payment_fields_to_tenants.php`
- Modify: `app/Models/Tenant.php`
- Test: `tests/Feature/TenantSubscriptionSchemaTest.php`

**Interfaces:**
- Produces: `tenants.payment_ref` (string, nullable, unique), `tenants.subscription_ends_at` (timestamp, nullable, cast to `datetime`). `Tenant::STATUSES` gains `'pending_payment'`. `Tenant::$fillable` gains `payment_ref`, `subscription_ends_at`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSubscriptionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_store_payment_ref_and_subscription_ends_at(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment',
            'payment_ref' => 'LAJUR-SUB-999-1234567890',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $this->assertSame('LAJUR-SUB-999-1234567890', $tenant->fresh()->payment_ref);
        $this->assertTrue($tenant->fresh()->subscription_ends_at->isFuture());
        $this->assertContains('pending_payment', Tenant::STATUSES);
    }

    public function test_payment_ref_is_unique(): void
    {
        Tenant::create([
            'name' => 'A', 'slug' => 'a-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-DUP',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Tenant::create([
            'name' => 'B', 'slug' => 'b-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-DUP',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TenantSubscriptionSchemaTest`
Expected: FAIL — `Unknown column 'payment_ref'`.

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
            $table->string('payment_ref')->nullable()->unique()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['payment_ref', 'subscription_ends_at']);
        });
    }
};
```

- [ ] **Step 4: Update `Tenant` model**

In `app/Models/Tenant.php`:

```php
    protected $fillable = [
        'name',
        'slug',
        'plan',
        'subscription_status',
        'payment_ref',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public const PLANS = ['basic', 'pro', 'business'];

    public const STATUSES = ['trial', 'active', 'suspended', 'cancelled', 'pending_payment'];
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TenantSubscriptionSchemaTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS (119 existing + 2 new = 121)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_12_100000_add_subscription_payment_fields_to_tenants.php \
        app/Models/Tenant.php tests/Feature/TenantSubscriptionSchemaTest.php
git commit -m "feat: add payment_ref/subscription_ends_at to tenants, pending_payment status"
```

---

### Task 2: `SubscriptionCheckout` — Midtrans Snap checkout for a Plan+Tenant

**Files:**
- Create: `app/Payments/SubscriptionCheckout.php`
- Test: `tests/Feature/SubscriptionCheckoutTest.php`

**Interfaces:**
- Consumes: `Tenant` (Task 1), `Plan` (existing), `config('services.midtrans.*')` (existing config, unmodified).
- Produces: `SubscriptionCheckout::createCheckout(Tenant $tenant, Plan $plan): ?string` — returns the Snap `redirect_url` on success, `null` if the server key is unset or the request fails (mirrors `MidtransGateway::createCheckout()`'s degrade-gracefully behavior). Sets `$tenant->payment_ref` to an order ID formatted `LAJUR-SUB-{tenantId}-{time}` and saves it.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Payments\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function pendingTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment',
        ]);
    }

    public function test_creates_checkout_and_sets_payment_ref(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/sub123',
            ]),
        ]);

        $tenant = $this->pendingTenant();
        $plan = Plan::where('key', 'pro')->firstOrFail();

        $url = app(SubscriptionCheckout::class)->createCheckout($tenant, $plan);

        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/sub123', $url);
        $tenant->refresh();
        $this->assertStringStartsWith('LAJUR-SUB-'.$tenant->id.'-', $tenant->payment_ref);
    }

    public function test_returns_null_when_server_key_unset(): void
    {
        config()->set('services.midtrans.server_key', '');

        $tenant = $this->pendingTenant();
        $plan = Plan::where('key', 'pro')->firstOrFail();

        $this->assertNull(app(SubscriptionCheckout::class)->createCheckout($tenant, $plan));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SubscriptionCheckoutTest`
Expected: FAIL — `Class "App\Payments\SubscriptionCheckout" not found`.

- [ ] **Step 3: Create `SubscriptionCheckout`**

```php
<?php

namespace App\Payments;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates a Midtrans Snap transaction for a tenant's plan subscription. Kept
 * separate from MidtransGateway (which is Booking-shaped) so the existing
 * booking payment flow is never touched by subscription billing changes.
 * Order IDs use the "LAJUR-SUB-" prefix so PaymentController::webhook() can
 * route notifications to a Tenant instead of a Booking.
 */
class SubscriptionCheckout
{
    public function createCheckout(Tenant $tenant, Plan $plan): ?string
    {
        $serverKey = (string) config('services.midtrans.server_key');
        if ($serverKey === '') {
            return null;
        }

        $orderId = 'LAJUR-SUB-'.$tenant->id.'-'.time();

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $plan->price,
            ],
            'item_details' => [[
                'id' => 'plan-'.$plan->key,
                'price' => (int) $plan->price,
                'quantity' => 1,
                'name' => mb_substr('Langganan Lajur - '.$plan->name.' (30 hari)', 0, 50),
            ]],
            'customer_details' => [
                'first_name' => $tenant->name,
            ],
            'callbacks' => [
                'finish' => route('signup.finish'),
            ],
        ];

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(30)
                ->post($this->snapUrl(), $payload);
        } catch (\Throwable $e) {
            Log::warning('Midtrans subscription checkout unreachable', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed() || ! $response->json('redirect_url')) {
            Log::warning('Midtrans subscription checkout failed', ['tenant' => $tenant->id, 'body' => $response->body()]);

            return null;
        }

        $tenant->forceFill(['payment_ref' => $orderId])->save();

        return $response->json('redirect_url');
    }

    private function snapUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }
}
```

Note: `route('signup.finish')` doesn't exist yet — it's created in Task 3. This is fine; Laravel resolves route names lazily at call time, not at class-definition time, and this class is only invoked from Task 3 onward.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SubscriptionCheckoutTest`

This will FAIL at this step with `Route [signup.finish] not defined` because Task 3 hasn't run yet. **This is expected** — add a minimal placeholder route right now so this task's tests are genuinely green in isolation:

In `routes/web.php`, add near the other public routes (after the `Order tracking` block, before `Payment` block):

```php
Route::get('/daftar/selesai', fn () => 'placeholder')->name('signup.finish');
```

(Task 3 replaces this placeholder closure with the real controller method — same route name, so nothing else needs to change.)

Run again: `php artisan test --filter=SubscriptionCheckoutTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS (121 existing + 2 new = 123)

- [ ] **Step 6: Commit**

```bash
git add app/Payments/SubscriptionCheckout.php routes/web.php tests/Feature/SubscriptionCheckoutTest.php
git commit -m "feat: SubscriptionCheckout - Midtrans Snap checkout for tenant plan payment"
```

---

### Task 3: `SignupController` — trial path (form + create + login)

**Files:**
- Create: `app/Http/Controllers/SignupController.php`
- Create: `app/Http/Requests/SignupRequest.php`
- Create: `resources/views/signup/pricing.blade.php`
- Create: `resources/views/signup/form.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SignupTrialTest.php`

**Interfaces:**
- Consumes: `Plan` (existing), `Tenant`/`User` (existing).
- Produces: `GET /daftar` (`signup.pricing`), `GET /daftar/trial` (`signup.trial.form`), `POST /daftar/trial` (`signup.trial.store`). Trial submission creates `Tenant`(`plan=business`, `subscription_status=trial`, `trial_ends_at`) + `User`(`role=owner`), logs in, redirects to `route('admin.dashboard')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_pricing_page_lists_plans_from_database(): void
    {
        $response = $this->get('/daftar');

        $response->assertOk();
        $response->assertSee('Basic');
        $response->assertSee('Pro');
        $response->assertSee('Business');
        $response->assertSee('Coba Gratis');
    }

    public function test_trial_signup_creates_tenant_and_logs_in(): void
    {
        $this->post('/daftar/trial', [
            'business_name' => 'Rental Baru', 'slug' => 'rental-baru',
            'owner_name' => 'Budi', 'email' => 'budi@rental-baru.id',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $tenant = Tenant::where('slug', 'rental-baru')->firstOrFail();
        $this->assertSame('business', $tenant->plan);
        $this->assertSame('trial', $tenant->subscription_status);
        $this->assertTrue($tenant->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));

        $user = User::where('email', 'budi@rental-baru.id')->firstOrFail();
        $this->assertSame(User::ROLE_OWNER, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_trial_signup_rejects_duplicate_slug(): void
    {
        Tenant::where('slug', 'lajur')->firstOrFail();

        $this->post('/daftar/trial', [
            'business_name' => 'Dupe', 'slug' => 'lajur',
            'owner_name' => 'Budi', 'email' => 'budi2@x.id', 'password' => 'password123',
        ])->assertSessionHasErrors('slug');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SignupTrialTest`
Expected: FAIL — `404 Not Found` (routes don't exist).

- [ ] **Step 3: Create `SignupRequest`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'Nama bisnis wajib diisi.',
            'slug.required' => 'Slug wajib diisi.',
            'slug.alpha_dash' => 'Slug hanya boleh huruf, angka, strip, dan underscore.',
            'slug.unique' => 'Slug ini sudah dipakai, coba yang lain.',
            'owner_name.required' => 'Nama pemilik wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ];
    }
}
```

- [ ] **Step 4: Create `SignupController`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Payments\SubscriptionCheckout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SignupController extends Controller
{
    public function pricing(): View
    {
        $plans = Plan::with('features')->orderBy('sort_order')->get();

        return view('signup.pricing', compact('plans'));
    }

    public function trialForm(): View
    {
        return view('signup.form', ['mode' => 'trial', 'plan' => null]);
    }

    public function storeTrial(SignupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $businessPlan = Plan::where('key', 'business')->firstOrFail();

        $tenant = Tenant::create([
            'name' => $data['business_name'],
            'slug' => $data['slug'],
            'plan' => 'business',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays($businessPlan->trial_days),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['owner_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_OWNER,
        ]);

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
```

- [ ] **Step 5: Create `resources/views/signup/pricing.blade.php`**

```blade
@extends('layouts.public')

@section('title', 'Harga & Paket — Lajur')

@section('content')
<main id="main" class="container" style="padding:48px 0">
    <h1>Pilih Paket Lajur</h1>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-top:24px">
        <div class="card" style="padding:20px;border:1px solid #e2e2e2;border-radius:12px">
            <h2>Coba Gratis</h2>
            <p>14 hari, akses penuh (setara Business)</p>
            <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">Coba Gratis 14 Hari</a>
        </div>

        @foreach ($plans as $plan)
            <div class="card" style="padding:20px;border:1px solid #e2e2e2;border-radius:12px">
                <h2>{{ $plan->name }}</h2>
                <p>Rp {{ number_format($plan->price, 0, ',', '.') }} / bulan</p>
                <ul>
                    @foreach ($plan->features as $feature)
                        <li>{{ $feature->name }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('signup.paid.form', $plan->key) }}" class="btn btn-primary">Pilih {{ $plan->name }}</a>
            </div>
        @endforeach
    </div>
</main>
@endsection
```

- [ ] **Step 6: Create `resources/views/signup/form.blade.php`**

```blade
@extends('layouts.public')

@section('title', 'Daftar — Lajur')

@section('content')
<main id="main" class="container" style="padding:48px 0;max-width:480px">
    <h1>
        @if ($mode === 'trial')
            Coba Gratis 14 Hari
        @else
            Daftar Paket {{ $plan->name }} (Rp {{ number_format($plan->price, 0, ',', '.') }}/bulan)
        @endif
    </h1>

    <form method="POST" action="{{ $mode === 'trial' ? route('signup.trial.store') : route('signup.paid.store', $plan->key) }}">
        @csrf
        <label>Nama Bisnis <input type="text" name="business_name" value="{{ old('business_name') }}" required></label>
        <label>Slug <input type="text" name="slug" value="{{ old('slug') }}" required placeholder="mis. rental-saya"></label>
        <label>Nama Pemilik <input type="text" name="owner_name" value="{{ old('owner_name') }}" required></label>
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Kata Sandi <input type="password" name="password" required minlength="8"></label>

        <button type="submit" class="btn btn-primary">
            @if ($mode === 'trial')
                Mulai Trial
            @else
                Lanjut ke Pembayaran
            @endif
        </button>
    </form>
</main>
@endsection
```

- [ ] **Step 7: Register routes**

In `routes/web.php`, add the import near other controller imports:

```php
use App\Http\Controllers\SignupController;
```

Replace the placeholder route added in Task 2 (`Route::get('/daftar/selesai', fn () => 'placeholder')->name('signup.finish');`) and add the pricing/trial routes as one block, placed after the `Order tracking` section and before `Payment`:

```php
/*
|--------------------------------------------------------------------------
| Public signup (pricing + trial + paid plan checkout)
|--------------------------------------------------------------------------
*/
Route::get('/daftar', [SignupController::class, 'pricing'])->name('signup.pricing');
Route::get('/daftar/trial', [SignupController::class, 'trialForm'])->name('signup.trial.form');
Route::post('/daftar/trial', [SignupController::class, 'storeTrial'])
    ->middleware('throttle:10,1')
    ->name('signup.trial.store');
Route::get('/daftar/selesai', fn () => 'placeholder')->name('signup.finish');
```

(The `signup.paid.form`/`signup.paid.store` routes and the real `signup.finish` handler are added in Task 4 — this task only needs the trial routes plus the still-placeholder `signup.finish` so route resolution keeps working.)

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=SignupTrialTest`
Expected: PASS (3 tests)

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: PASS (123 existing + 3 new = 126)

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/SignupController.php app/Http/Requests/SignupRequest.php \
        resources/views/signup/pricing.blade.php resources/views/signup/form.blade.php \
        routes/web.php tests/Feature/SignupTrialTest.php
git commit -m "feat: public /daftar pricing page + free trial signup"
```

---

### Task 4: `SignupController` — paid path (pending tenant + Midtrans redirect + finish page)

**Files:**
- Modify: `app/Http/Controllers/SignupController.php`
- Create: `resources/views/signup/finish.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SignupPaidTest.php`

**Interfaces:**
- Consumes: `SubscriptionCheckout::createCheckout()` (Task 2).
- Produces: `GET /daftar/{plan}` (`signup.paid.form`), `POST /daftar/{plan}` (`signup.paid.store`), `GET /daftar/selesai` (`signup.finish`, real implementation replacing the Task 2/3 placeholder). Paid submission creates `Tenant`(`subscription_status=pending_payment`) + `User`(`role=owner`, **not** logged in), redirects to the Midtrans URL.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignupPaidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    public function test_paid_form_shows_plan_name_and_price(): void
    {
        $this->get('/daftar/pro')->assertOk()->assertSee('Pro');
    }

    public function test_paid_signup_creates_pending_tenant_and_redirects_to_midtrans(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/subxyz',
            ]),
        ]);

        $response = $this->post('/daftar/pro', [
            'business_name' => 'Bayar Co', 'slug' => 'bayar-co',
            'owner_name' => 'Sari', 'email' => 'sari@bayar-co.id', 'password' => 'password123',
        ]);

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/subxyz');

        $tenant = Tenant::where('slug', 'bayar-co')->firstOrFail();
        $this->assertSame('pro', $tenant->plan);
        $this->assertSame('pending_payment', $tenant->subscription_status);
        $this->assertNotNull($tenant->payment_ref);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['email' => 'sari@bayar-co.id']);
    }

    public function test_finish_page_shows_pending_when_not_yet_paid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-1-999',
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-1-999')
            ->assertOk()
            ->assertSee('Menunggu');
    }

    public function test_finish_page_shows_success_when_active(): void
    {
        $tenant = Tenant::create([
            'name' => 'Paid Co', 'slug' => 'paid-co', 'plan' => 'pro',
            'subscription_status' => 'active', 'payment_ref' => 'LAJUR-SUB-2-999',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-2-999')
            ->assertOk()
            ->assertSee('Login');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SignupPaidTest`
Expected: FAIL — `404 Not Found` for `/daftar/pro`.

- [ ] **Step 3: Extend `SignupController`**

Add these methods to `app/Http/Controllers/SignupController.php` (keep existing `pricing()`/`trialForm()`/`storeTrial()` as-is):

```php
    public function paidForm(string $planKey): View
    {
        $plan = Plan::where('key', $planKey)->firstOrFail();

        return view('signup.form', ['mode' => 'paid', 'plan' => $plan]);
    }

    public function storePaid(SignupRequest $request, string $planKey, SubscriptionCheckout $checkout): RedirectResponse
    {
        $plan = Plan::where('key', $planKey)->firstOrFail();
        $data = $request->validated();

        $tenant = Tenant::create([
            'name' => $data['business_name'],
            'slug' => $data['slug'],
            'plan' => $plan->key,
            'subscription_status' => 'pending_payment',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['owner_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_OWNER,
        ]);

        $url = $checkout->createCheckout($tenant, $plan);

        if (! $url) {
            return redirect()->route('signup.paid.form', $planKey)
                ->withErrors(['email' => 'Pembayaran sedang tidak tersedia, silakan coba lagi nanti.']);
        }

        return redirect($url);
    }

    public function finish(): View
    {
        $orderId = (string) request()->query('order_id', '');
        $tenant = $orderId !== ''
            ? Tenant::where('payment_ref', $orderId)->first()
            : null;

        return view('signup.finish', ['tenant' => $tenant]);
    }
```

Add the missing import at the top of the file:

```php
use App\Payments\SubscriptionCheckout;
```

(It's likely already imported from Task 2's wiring — verify, don't duplicate the `use` line if present.)

- [ ] **Step 4: Create `resources/views/signup/finish.blade.php`**

```blade
@extends('layouts.public')

@section('title', 'Status Pembayaran — Lajur')

@section('content')
<main id="main" class="container" style="padding:48px 0;max-width:480px">
    @if (! $tenant)
        <h1>Transaksi tidak ditemukan</h1>
        <p>Silakan <a href="{{ route('signup.pricing') }}">coba daftar lagi</a>.</p>
    @elseif ($tenant->subscription_status === 'active')
        <h1>Pembayaran Berhasil</h1>
        <p>Paket {{ $tenant->plan }} untuk {{ $tenant->name }} sudah aktif.</p>
        <a href="{{ route('login') }}" class="btn btn-primary">Login Sekarang</a>
    @else
        <h1>Menunggu Konfirmasi Pembayaran</h1>
        <p>Pembayaran Anda sedang diproses. Halaman ini akan menampilkan status terbaru — silakan refresh dalam beberapa saat.</p>
        <a href="{{ route('signup.finish') }}?order_id={{ $tenant->payment_ref }}" class="btn btn-secondary">Refresh Status</a>
    @endif
</main>
@endsection
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, replace the placeholder block from Task 3:

```php
Route::get('/daftar/selesai', fn () => 'placeholder')->name('signup.finish');
```

with:

```php
Route::get('/daftar/selesai', [SignupController::class, 'finish'])->name('signup.finish');
Route::get('/daftar/{plan}', [SignupController::class, 'paidForm'])->name('signup.paid.form');
Route::post('/daftar/{plan}', [SignupController::class, 'storePaid'])
    ->middleware('throttle:10,1')
    ->name('signup.paid.store');
```

(Order matters: `/daftar/selesai` must be registered before the `/daftar/{plan}` wildcard route, otherwise Laravel would try to match "selesai" as a plan key. Placing it first, as shown, is correct.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SignupPaidTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS (126 existing + 4 new = 130)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/SignupController.php resources/views/signup/finish.blade.php \
        routes/web.php tests/Feature/SignupPaidTest.php
git commit -m "feat: paid plan signup - pending tenant + Midtrans redirect + status page"
```

---

### Task 5: Webhook activation — route subscription payments to Tenant

**Files:**
- Modify: `app/Http/Controllers/PaymentController.php`
- Test: `tests/Feature/SubscriptionWebhookTest.php`

**Interfaces:**
- Consumes: `MidtransGateway::verifyCallback()` (existing, unmodified — already payload-shape-generic).
- Produces: `PaymentController::webhook()` activates a `Tenant` when `order_id` starts with `LAJUR-SUB-` and the verified status is `paid`: sets `subscription_status=active`, `subscription_ends_at=now()->addDays(30)`. Booking-prefixed order IDs keep their existing behavior unchanged.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payment.gateway', 'midtrans');
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function signedPayload(string $orderId, string $transactionStatus): array
    {
        $statusCode = '200';
        $grossAmount = '350000.00';
        $serverKey = 'SB-Mid-server-TEST';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return [
            'order_id' => $orderId, 'status_code' => $statusCode, 'gross_amount' => $grossAmount,
            'signature_key' => $signature, 'transaction_status' => $transactionStatus, 'fraud_status' => 'accept',
        ];
    }

    public function test_webhook_activates_pending_tenant_on_settlement(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-'.1,
        ]);
        // payment_ref must match exactly what's signed/looked-up below.
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-1234567890']);

        $payload = $this->signedPayload($tenant->payment_ref, 'settlement');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertNotNull($tenant->subscription_ends_at);
        $this->assertTrue($tenant->subscription_ends_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_webhook_does_not_activate_on_pending_status(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co 2', 'slug' => 'pending-co-2', 'plan' => 'pro',
            'subscription_status' => 'pending_payment',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-1234567891']);

        $payload = $this->signedPayload($tenant->payment_ref, 'pending');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $this->assertSame('pending_payment', $tenant->fresh()->subscription_status);
    }

    public function test_webhook_ignores_unknown_subscription_order_id(): void
    {
        $payload = $this->signedPayload('LAJUR-SUB-99999-000', 'settlement');

        // Must not throw even though no tenant matches this payment_ref.
        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SubscriptionWebhookTest`
Expected: FAIL — tenant stays `pending_payment` (webhook doesn't know about tenants yet).

- [ ] **Step 3: Modify `PaymentController::webhook()`**

In `app/Http/Controllers/PaymentController.php`, add the `Tenant` import:

```php
use App\Models\Tenant;
```

Replace the body of `webhook()`:

```php
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        $status = $this->gateway->verifyCallback($payload);
        $orderId = $payload['order_id'] ?? null;

        if ($status && $orderId && str_starts_with((string) $orderId, 'LAJUR-SUB-')) {
            $this->activateSubscription((string) $orderId, $status);
        } elseif ($status && $orderId) {
            $booking = Booking::withoutGlobalScopes()->where('payment_ref', $orderId)->first();

            if ($booking) {
                $booking->payment_status = $status;

                if ($status === 'paid') {
                    $booking->paid_at = now();
                    if ($booking->status === 'pending') {
                        $booking->status = 'confirmed';
                    }
                }

                $booking->save();
            }
        }

        // Always 200 so the gateway stops retrying; we only act on a valid,
        // signature-verified, mapped status.
        return response()->json(['ok' => true]);
    }

    /** Activates a tenant's paid subscription. No-op for any status other than 'paid' or if the order_id doesn't match a pending tenant. */
    private function activateSubscription(string $orderId, string $status): void
    {
        if ($status !== 'paid') {
            return;
        }

        $tenant = Tenant::where('payment_ref', $orderId)->first();

        if ($tenant) {
            $tenant->update([
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addDays(30),
            ]);
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SubscriptionWebhookTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the full suite (regression check on booking payments)**

Run: `php artisan test --filter=PaymentMidtransTest && php artisan test`
Expected: `PaymentMidtransTest` fully green (booking webhook behavior unchanged), full suite PASS (130 existing + 3 new = 133).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PaymentController.php tests/Feature/SubscriptionWebhookTest.php
git commit -m "feat: activate tenant subscription from Midtrans webhook (LAJUR-SUB- order IDs)"
```

---

### Task 6: Subscription expiry — lapsed paid plan downgrades to Basic

**Files:**
- Modify: `app/Tenancy/TrialGuard.php`
- Modify: `app/Console/Commands/CheckTrials.php`
- Test: `tests/Feature/SubscriptionExpiryTest.php`

**Interfaces:**
- Consumes: `Tenant` (Task 1's `subscription_ends_at`).
- Produces: `TrialGuard` gains `settleIfLapsed(Tenant $tenant): Tenant` — for a tenant with `subscription_status=active` and `subscription_ends_at` in the past, downgrades to `plan=basic` (status stays `active`, since it already reflects "no longer trial/pending" — only the plan changes). `IdentifyTenant` calls both `settleIfExpired()` (trial) and `settleIfLapsed()` (paid subscription) — order doesn't matter, a tenant is only ever in one of the two states at a time. The `tenants:check-trial` command is extended to also sweep lapsed paid subscriptions in the same run (command name unchanged — it already means "check tenants' time-based plan status").

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\TrialGuard;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_lapsed_paid_subscription_downgrades_to_basic(): void
    {
        $tenant = Tenant::create([
            'name' => 'Lapsed Co', 'slug' => 'lapsed-co', 'plan' => 'pro',
            'subscription_status' => 'active', 'subscription_ends_at' => now()->subDay(),
        ]);

        app(TrialGuard::class)->settleIfLapsed($tenant);

        $this->assertSame('basic', $tenant->fresh()->plan);
        $this->assertSame('active', $tenant->fresh()->subscription_status);
    }

    public function test_active_unexpired_subscription_is_untouched(): void
    {
        $tenant = Tenant::create([
            'name' => 'Fresh Co', 'slug' => 'fresh-co', 'plan' => 'pro',
            'subscription_status' => 'active', 'subscription_ends_at' => now()->addDays(10),
        ]);

        app(TrialGuard::class)->settleIfLapsed($tenant);

        $this->assertSame('pro', $tenant->fresh()->plan);
    }

    public function test_tenant_with_no_subscription_ends_at_is_untouched(): void
    {
        // e.g. the seeded 'lajur' tenant or any tenant that came from the trial path.
        $tenant = Tenant::create([
            'name' => 'Trial Co', 'slug' => 'trial-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->addDays(5),
        ]);

        app(TrialGuard::class)->settleIfLapsed($tenant);

        $this->assertSame('business', $tenant->fresh()->plan);
    }

    public function test_check_trial_command_also_downgrades_lapsed_subscriptions(): void
    {
        Tenant::create([
            'name' => 'Lapsed Co 2', 'slug' => 'lapsed-co-2', 'plan' => 'business',
            'subscription_status' => 'active', 'subscription_ends_at' => now()->subDay(),
        ]);

        $this->artisan('tenants:check-trial')->assertExitCode(0);

        $this->assertSame('basic', Tenant::where('slug', 'lapsed-co-2')->value('plan'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SubscriptionExpiryTest`
Expected: FAIL — `Call to undefined method App\Tenancy\TrialGuard::settleIfLapsed()`.

- [ ] **Step 3: Add `settleIfLapsed()` to `TrialGuard`**

In `app/Tenancy/TrialGuard.php`, add this method alongside `settleIfExpired()`:

```php
    /** Downgrades a tenant whose PAID subscription period has ended, to the Basic plan. */
    public function settleIfLapsed(Tenant $tenant): Tenant
    {
        if ($tenant->subscription_status !== 'active') {
            return $tenant;
        }

        if (! $tenant->subscription_ends_at || $tenant->subscription_ends_at->isFuture()) {
            return $tenant;
        }

        $tenant->update(['plan' => 'basic']);

        return $tenant;
    }
```

- [ ] **Step 4: Wire into `IdentifyTenant`**

In `app/Http/Middleware/IdentifyTenant.php`, extend the existing safety-net block:

```php
        if ($tenant) {
            $tenant = app(TrialGuard::class)->settleIfExpired($tenant);
            $tenant = app(TrialGuard::class)->settleIfLapsed($tenant);
        }
```

- [ ] **Step 5: Extend `CheckTrials` command**

In `app/Console/Commands/CheckTrials.php`, add a second sweep after the existing trial-expiry loop, inside `handle()`:

```php
        $lapsed = Tenant::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->where('plan', '!=', 'basic')
            ->get();

        foreach ($lapsed as $tenant) {
            $guard->settleIfLapsed($tenant);
            $this->info("Tenant {$tenant->slug}: langganan berakhir, diturunkan ke plan Basic.");
        }
```

(The `where('plan', '!=', 'basic')` guard avoids re-processing tenants already on Basic — harmless either way since `settleIfLapsed()` would just re-set the same value, but this keeps the log output meaningful.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SubscriptionExpiryTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS (133 existing + 4 new = 137)

- [ ] **Step 8: Commit**

```bash
git add app/Tenancy/TrialGuard.php app/Http/Middleware/IdentifyTenant.php \
        app/Console/Commands/CheckTrials.php tests/Feature/SubscriptionExpiryTest.php
git commit -m "feat: lapsed paid subscriptions auto-downgrade to Basic, same as expired trials"
```

---

### Task 7: Navbar link + end-to-end regression pass

**Files:**
- Modify: `resources/views/layouts/public.blade.php`
- Test: `tests/Feature/SignupNavLinkTest.php`

**Interfaces:**
- Consumes: `route('signup.pricing')` (Task 3).
- Produces: a "Daftar" / "Mulai Trial" link in the public nav, pointing to `/daftar`, visible on every public page.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupNavLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_links_to_signup_pricing(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('signup.pricing'), false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SignupNavLinkTest`
Expected: FAIL — link not present in `layouts.public`.

- [ ] **Step 3: Add the nav link**

In `resources/views/layouts/public.blade.php`, inside the `.nav-cta` div (right before the existing `<a href="{{ route('login') }}" class="btn btn-ghost btn-sm">` line), add:

```blade
                <a href="{{ route('signup.pricing') }}" class="btn btn-ghost btn-sm">Daftar</a>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SignupNavLinkTest`
Expected: PASS (1 test)

- [ ] **Step 5: Run the full suite (final regression check)**

Run: `php artisan test`
Expected: PASS (137 existing + 1 new = 138). Every pre-existing test (bookings, tracking, fuel, super admin plans, AI assistant, etc.) unaffected — this task only adds a Blade link.

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/public.blade.php tests/Feature/SignupNavLinkTest.php
git commit -m "feat: add /daftar link to public navbar"
```

---

## Post-plan manual check (not automated)

- [ ] Run `php artisan migrate` locally, then walk through `/daftar` in a browser: click "Coba Gratis" end-to-end (should log you straight into `/admin`), then click "Pro" and confirm it redirects to a real Midtrans sandbox checkout page (needs `MIDTRANS_SERVER_KEY`/`MIDTRANS_CLIENT_KEY` set in `.env`, sandbox mode).
- [ ] Complete a sandbox payment on Midtrans's test page, confirm the webhook (needs a public URL — e.g. via `php artisan serve` + an ngrok tunnel registered in the Midtrans dashboard notification URL) activates the tenant, and that `/daftar/selesai?order_id=...` then shows "Login Sekarang".
- [ ] Manually set a paid tenant's `subscription_ends_at` to a past date via tinker, confirm its next `/admin` request downgrades it to Basic (mirrors the existing trial-expiry manual check from the previous feature).
