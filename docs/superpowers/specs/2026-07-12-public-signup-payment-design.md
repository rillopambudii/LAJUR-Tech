# Public Signup & Plan Payment Page

**Date:** 2026-07-12
**Status:** Approved for planning

## Problem

Lajur's plan/feature-gating system (`docs/superpowers/specs/2026-07-12-superadmin-plans-design.md`) lets the platform owner define Basic/Pro/Business plans and manage tenants, but the only way a tenant account gets created today is the super admin manually creating one from `/superadmin/tenants`. There is no public page where a prospective customer can pick a plan and sign up themselves — the owner explicitly wants a self-service page where "they all just click the price and pay."

## Goals

- A public, unauthenticated page (`/daftar`) listing Basic/Pro/Business (price, features) plus a free 14-day trial option, all read from the live `plans`/`features` data the owner manages in `/superadmin/plans` — never hardcoded.
- Choosing the trial creates a tenant + owner account immediately (no payment) and logs the user straight into their dashboard — this reuses the existing trial mechanics from the plans/feature-gating feature exactly as they are.
- Choosing a paid plan collects the same signup details, then hands off to Midtrans Snap for payment; on confirmed payment the tenant activates on the chosen plan for 30 days.
- A subscription that isn't renewed within 30 days downgrades to Basic automatically — consistent with the existing trial-expiry behavior (tenant is not locked out, just loses premium features).

## Non-goals (explicitly out of scope)

- Recurring/auto-billing (saved card, automatic monthly charge). Renewal is a manual "pay again" action by the tenant owner.
- Billing history / invoice list visible to the tenant.
- Expiry reminder emails or notifications.
- Any change to the existing booking payment flow (`App\Payments\MidtransGateway`, `Booking` webhook handling) — that code is not touched.

## Data model

### `tenants` — two new nullable columns
| column | type | notes |
|---|---|---|
| `payment_ref` | string, nullable, unique | Midtrans order ID for the pending/last subscription payment. Mirrors `bookings.payment_ref`. |
| `subscription_ends_at` | timestamp, nullable | When the current paid subscription period ends. Set on successful payment; unrelated to `trial_ends_at`. |

### `tenants.subscription_status` — one new value
Existing values: `trial | active | suspended | cancelled`. Add **`pending_payment`**: set when a tenant record is created for a paid-plan signup, before Midtrans confirms payment. A tenant in this state has no dashboard access (same as any unauthenticated-feeling state — the owner account exists but the tenant isn't usable yet); this is enforced by the existing `EnsureUserIsAdmin`/tenant flow simply having no bookings/features until `subscription_status` flips to `active`, so no new middleware is needed — see Enforcement below.

## Signup flow

### Trial path (no payment)
1. `GET /daftar` — pricing page, "Coba Gratis 14 Hari" button links to `GET /daftar/trial`.
2. `GET /daftar/trial` — signup form (business name, slug, owner name, email, password).
3. `POST /daftar/trial` — validates, creates `Tenant` (`plan=business`, `subscription_status=trial`, `trial_ends_at=now()+business.trial_days`, exactly like `SuperAdmin\TenantController::store()`) and a `User` (`role=owner`, `tenant_id`), logs the user in (session regenerate, matching `LoginController`'s existing pattern), redirects to `/admin`.

### Paid path (Midtrans)
1. `GET /daftar` — clicking Basic/Pro/Business links to `GET /daftar/{plan}` where `{plan}` is the plan's `key`.
2. `GET /daftar/{plan}` — same signup form, plan name/price shown for confirmation.
3. `POST /daftar/{plan}` — validates, creates `Tenant` (`plan={plan}`, `subscription_status=pending_payment`, `trial_ends_at=null`) and a `User` (`role=owner`), then calls a new `SubscriptionCheckout` service to create a Midtrans Snap transaction for `Plan::price`, and redirects the browser to the returned Snap URL. The owner account is **not** logged in yet — payment isn't confirmed.
4. Customer pays on Midtrans's hosted page, then is bounced to `GET /daftar/selesai?order_id=...` (a status page: "Pembayaran berhasil, silakan login" if the tenant is now `active`, or "Menunggu konfirmasi pembayaran" with a manual refresh link if still `pending_payment` — mirrors `PaymentController::finish()`'s pattern for bookings).
5. Midtrans sends its notification to the **existing single webhook endpoint** `POST /payment/midtrans/webhook`. `PaymentController::webhook()` currently assumes every `order_id` belongs to a `Booking`; it gains one branch: if `order_id` starts with `LAJUR-SUB-`, resolve a `Tenant` by `payment_ref` instead of a `Booking`, and on `status === 'paid'` set `subscription_status=active`, `subscription_ends_at=now()->addDays(30)`. Booking order IDs keep their existing `LAJUR-{bookingId}-{time}` format (no change); subscription order IDs use `LAJUR-SUB-{tenantId}-{time}`.

## Payment integration

A new class `App\Payments\SubscriptionCheckout` (not implementing the existing `PaymentGateway` interface, which is `Booking`-shaped) creates a Midtrans Snap transaction for a `Tenant`+`Plan` pair, following the same HTTP call shape as `MidtransGateway::createCheckout()` (same config keys `services.midtrans.*`, same sandbox/production URL selection, same "return null and degrade gracefully if the server key is blank or the request fails" behavior). It is intentionally a small, separate class rather than a refactor of `MidtransGateway` — the existing booking payment code is not touched, keeping this feature isolated and the booking flow's risk at zero.

`PaymentController::webhook()`'s signature verification (`MidtransGateway::verifyCallback()`) is reused as-is — it's already payload-shape-generic (order_id/status_code/gross_amount/signature), not `Booking`-specific.

## Subscription expiry

A new check joins the existing `tenants:check-trial` scheduled command's pattern: tenants with `subscription_status=active` and `subscription_ends_at` in the past (i.e., a paid subscription that lapsed) downgrade to `plan=basic` — the same non-punitive behavior already shipped for expired trials. This extends `TrialGuard` (renamed in effect to also guard subscription expiry, or a sibling method on the same service — implementation plan decides) so both expiry paths funnel through one place, and the existing `IdentifyTenant` safety net covers both without a second inline check.

## Views

- `resources/views/signup/pricing.blade.php` — the `/daftar` page. Extends `layouts.public` (existing public layout), reuses `app.css` (no new stylesheet), pulls plans/features from the DB.
- `resources/views/signup/form.blade.php` — shared by both trial and paid paths (a hidden field or route param carries which plan was chosen).
- `resources/views/signup/finish.blade.php` — the post-payment status page.

## Testing

Feature tests covering:
- `/daftar` lists live plan data (price/features change when the owner edits them in `/superadmin/plans`).
- Trial signup creates tenant+user and logs the user in.
- Paid signup creates a `pending_payment` tenant and redirects to the (faked) Midtrans URL; user is not authenticated yet.
- Webhook activates a pending tenant on `paid` status, sets `subscription_ends_at` 30 days out, leaves booking webhook behavior unchanged (regression check against existing `PaymentMidtransTest`).
- Slug collision on signup shows a validation error (reuses the same `unique:tenants,slug` rule as `SuperAdmin\TenantController::store()`).
- Subscription expiry downgrade test, mirroring `TrialGuardTest`.

## Open follow-ups (not this iteration)

- Recurring/auto-billing.
- Expiry reminder emails.
- Tenant-facing billing history page.
