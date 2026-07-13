# In-Dashboard Subscription Upgrade

**Date:** 2026-07-13
**Status:** Approved for planning

## Problem

Existing tenants have no way to pay for or change their plan from inside their
own dashboard. The paid-checkout machinery built for public signup
(`App\Payments\SubscriptionCheckout` + the `LAJUR-SUB-` webhook branch in
`PaymentController`) only fires during tenant *creation* (`/daftar/{plan}`).
An existing tenant — trial or already active — has no self-service path to
subscribe or upgrade; the only lever today is the platform owner manually
reassigning their plan from `/superadmin/tenants`.

Two owner-reported scenarios prompted this:
- A trial ends and the tenant (now downgraded to Basic by the existing
  `TrialGuard`) has no way to pay to regain the higher plan.
- A tenant wants to pay *before* their 14-day trial ends (e.g. day 3), because
  they're already convinced.

## Goals

- A "Langganan" page inside the tenant admin (`/admin/langganan`), reachable
  on every plan (no feature gate — this is the opposite of a premium feature),
  showing current plan/status and letting the owner pick Basic/Pro/Business
  and pay via the same Midtrans Snap flow already built.
- Paying while on ANY status (trial, active-paid, or downgraded-Basic)
  produces the same outcome: `subscription_status=active`,
  `subscription_ends_at = now()+30 days`, `plan = <chosen plan>`. An
  in-progress trial is not partially preserved or extended — the spec's
  earlier example (day-3 trial + pay) explicitly resolves to a clean 30-day
  paid period starting at payment, not 11 leftover trial days plus 30.

## Non-goals (explicitly out of scope, per this iteration)

- Self-service "Forgot Password" (owner explicitly deferred this; still a
  manual owner-side reset via tinker for now).
- Downgrading/cancelling a plan from the dashboard (only upgrade/(re)subscribe
  flows here; cancellation stays a `/superadmin` action).
- Prorated billing, partial refunds, or stacking remaining trial/subscription
  days onto a new purchase.
- Saved payment methods / one-click renewal (still redirect-to-Midtrans each
  time, same as signup).

## The security constraint this design exists to satisfy

`tenants.plan` must never be set to the target plan before Midtrans confirms
payment. This is the exact class of bug fixed in the public-signup flow (a
`pending_payment` tenant that could reach `/admin` with unpaid premium
access) — but an in-dashboard upgrade starts from an *already-logged-in,
already-active* tenant, so gating on `subscription_status` (the fix used for
new signups) doesn't apply here: we cannot flip an already-active tenant to
`pending_payment` mid-upgrade, that would lock them out of their own
dashboard while their payment is still processing.

**Resolution:** a new nullable `tenants.pending_plan` column. Initiating a
checkout sets `payment_ref` (as today) and `pending_plan = <target plan
key>`, but leaves `plan` and `subscription_status` untouched — the tenant
keeps whatever access they already had throughout. Only the signature-verified
webhook, on `status === 'paid'`, promotes `pending_plan` into `plan` and
clears it. The existing new-tenant-signup path is unaffected: it never sets
`pending_plan`, so the webhook's existing behavior (leave `plan` alone, it
was already set correctly at tenant creation) is preserved by construction.

## Data model

New nullable column: `tenants.pending_plan` (string, matches a `plans.key`).

## Checkout flow (existing tenant)

1. `GET admin/langganan` — status card (current plan, trial_ends_at or
   subscription_ends_at) + the 3 plan cards (reusing the `.plan-card` styling
   from `/daftar`), each a `POST admin/langganan/{plan}` form.
2. `POST admin/langganan/{plan}`: validates `{plan}` is a real plan key,
   inside a DB transaction (mirroring the already-shipped rollback pattern in
   `SignupController::storePaid()`): sets `payment_ref` + `pending_plan` on
   the *current* tenant, calls `SubscriptionCheckout::createCheckout($tenant,
   $plan, route('admin.subscription.finish'))` — `SubscriptionCheckout` gains
   an optional third `$finishUrl` parameter (default `route('signup.finish')`,
   preserving today's call site in `SignupController` unmodified) so this
   flow returns to the dashboard instead of the public `/daftar/selesai` page.
   On failure (`null` return), roll back (clear the just-set `payment_ref`/
   `pending_plan`) and redirect back with an error, exactly like the signup
   flow's accepted failure handling.
3. Redirect to Midtrans; owner pays.
4. `GET admin/langganan/selesai` (`admin.subscription.finish`): a small status
   page, same shape as `payment/finish` — "processing" if `pending_plan` is
   still set, "success" once it's cleared and `plan` matches.
5. Webhook (`PaymentController::activateSubscription`, extended): on `paid`,
   in addition to the existing `subscription_status=active` +
   `subscription_ends_at=+30 days`, ALSO: if `pending_plan` is set, copy it
   into `plan` and null `pending_plan`. If `pending_plan` is null (the
   existing new-tenant-signup case), behavior is byte-identical to today.

## Testing

- Existing trial tenant pays → `plan` becomes the chosen plan,
  `subscription_status=active`, `subscription_ends_at` ~30 days out,
  `pending_plan` null.
- Existing Basic (post-trial-downgrade) tenant pays for Pro → same outcome.
- Access is NOT granted while `payment_ref`/`pending_plan` are pending and
  the webhook hasn't fired yet — `plan` stays whatever it was before checkout
  was initiated (regression guard for the security constraint above).
- Failed checkout creation rolls back `payment_ref`/`pending_plan` (no orphan
  state), tenant can retry.
- The new-tenant public signup path (`SignupController::storePaid`,
  Tasks 4/5 of the prior feature) is unaffected — its existing tests must
  keep passing unmodified, proving `pending_plan` stays null on that path and
  webhook activation there is unchanged.
- `/admin/langganan` reachable regardless of plan (no `feature:` middleware);
  a driver (non-manager) cannot access it (existing `admin` middleware).

## Open follow-ups (not this iteration)

- Self-service password reset.
- Downgrade/cancel from the dashboard.
- Trial-day proration on early upgrade.
