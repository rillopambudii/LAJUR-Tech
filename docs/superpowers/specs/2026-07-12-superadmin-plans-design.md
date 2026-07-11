# Super Admin — Plans & Feature Gating

**Date:** 2026-07-12
**Status:** Approved for planning

## Problem

Lajur is becoming a multi-tenant SaaS. The `tenants` table already has `plan`,
`subscription_status`, and `trial_ends_at` columns (Phase 0), but nothing
reads or writes them meaningfully yet — every tenant effectively has every
feature, there's no way to define what Basic/Pro/Business include, no way to
see all tenants at a glance, and no trial expiry behavior. The owner (running
the platform, not a tenant) needs a single "page induk" to configure this
without touching code.

## Goals

- Define three plans (Basic, Pro, Business) with price and trial length,
  editable from a UI, not hardcoded.
- Define which existing dashboard features belong to which plan, toggleable
  from the same UI.
- Actually enforce the gating (hide + block), not just store metadata.
- Give the owner a way to see all tenants and manually set a tenant's plan
  (needed because public self-signup doesn't exist yet).
- New tenants start on a 14-day trial with full (Business-level) access, and
  automatically settle to Basic if they don't upgrade before the trial ends.

## Non-goals (explicitly out of scope)

- Public marketing/landing page or public tenant self-signup.
- Payment/billing automation (charging cards, SaaS invoicing).
- Business metrics dashboard (MRR, growth charts) for the super admin.

## Data model

### New table `plans`
| column | type | notes |
|---|---|---|
| id | bigint pk | |
| key | string, unique | `basic` \| `pro` \| `business` |
| name | string | display name |
| price | unsigned integer | rupiah, editable |
| trial_days | unsigned integer | default 14 |
| sort_order | unsigned integer | display order |
| timestamps | | |

### New table `features`
| column | type | notes |
|---|---|---|
| id | bigint pk | |
| key | string, unique | e.g. `gps_tracking`, `ai_assistant`, `fuel_tracking`, `export`, `mileage` |
| name | string | display name |
| description | string, nullable | |
| timestamps | | |

### New pivot `feature_plan`
`plan_id`, `feature_id` — many-to-many, defines which features a plan includes.

### `tenants.plan` (existing column, reused)
No schema change. A data migration backfills existing string values so they
line up with the new `plans.key` values: `free` → `basic`, `enterprise` →
`business`, `pro` stays `pro`. Going forward `tenants.plan` always holds a
valid `plans.key`. No FK is added — kept as a loose string reference,
consistent with how `role` already works on `users`, and it avoids a
migration/rename hazard if plan keys ever change.

### `users.role`
Add `super_admin` as a valid value alongside existing
owner/admin/driver/customer. Super admin users are **not** tenant-scoped
(same treatment as the existing exemption for `User` from the tenancy global
scope).

## Default feature → plan mapping (seeded, editable after)

| Feature | Basic | Pro | Business |
|---|---|---|---|
| Booking, Kalender, Fleet & Driver CRUD, Invoice, Reports dasar | ✓ | ✓ | ✓ |
| GPS Tracking | | ✓ | ✓ |
| Fuel/BBM anti-kebocoran | | ✓ | ✓ |
| Export PDF/Excel | | ✓ | ✓ |
| Mileage & prediksi servis | | ✓ | ✓ |
| AI Business Assistant | | | ✓ |

Core features (booking/calendar/fleet/driver/invoice/basic reports) are not
individually toggleable — they exist on every plan and are not represented as
gated `features` rows. Only the premium add-ons above are modeled as
`features` rows and go through the pivot.

## Enforcement

`Tenant` gets `hasFeature(string $featureKey): bool`, resolving the tenant's
plan (via `plan` string → `Plan::where('key', ...)`) and checking the
`feature_plan` pivot. Result cached per-request on `TenantManager` (already a
singleton) to avoid repeat queries.

- **Route guard:** new middleware `EnsureFeatureEnabled:{featureKey}` applied
  to the Tracking, Fuel, Export, Mileage, and Assistant route groups. On
  failure: redirect back (or to `/admin`) with a flash message ("Fitur ini
  tidak tersedia di plan Anda saat ini — upgrade untuk mengaktifkan.").
- **Sidebar:** admin layout nav items for gated features check
  `$tenant->hasFeature(...)` and are omitted entirely when false.

## Trial lifecycle

- Creating a new tenant (super admin action, since public signup doesn't
  exist yet) sets `plan=business`, `subscription_status=trial`,
  `trial_ends_at = now()->addDays($businessPlan->trial_days)`.
- New artisan command `tenants:check-trial`: for every tenant with
  `subscription_status=trial` and `trial_ends_at` in the past, set
  `plan=basic`, `subscription_status=active`. Scheduled daily, same pattern
  as the existing `mileage:sync` command.
- Safety-net check in `IdentifyTenant` middleware: if the resolved tenant is
  in an expired trial, apply the same downgrade inline before continuing the
  request (covers gaps if the scheduler didn't run recently — dev/local
  environments especially).

## Super admin panel

Route prefix `/superadmin`, guarded by `role:super_admin` (existing role
middleware extended to accept the new value). Separate `layouts.superadmin`
Blade layout — deliberately not sharing `layouts.admin`, since this is the
platform owner's view, not a tenant's.

### `/superadmin/plans`
Primary screen. Three cards (Basic/Pro/Business), each showing/editing:
price, trial_days, and a checkbox per gated feature (AJAX or simple form
POST, saves immediately). Matches the approved mockup layout.

### `/superadmin/tenants`
Lightweight list: tenant name, current plan, subscription_status,
trial_ends_at. Each row has a plan-change action (dropdown + save) so the
owner can manually move a tenant between plans or reactivate a
suspended/expired one. No suspend/delete actions in this iteration — out of
scope, can follow later if needed.

## Testing

Feature tests covering:
- Plan/feature CRUD from super admin routes (auth required, non-super-admin
  gets 403).
- `Tenant::hasFeature()` correctness across all three plans.
- `EnsureFeatureEnabled` middleware blocks/allows correctly per plan.
- `tenants:check-trial` command downgrades expired trials and leaves active
  trials untouched.
- Sidebar hides gated nav items for a Basic-plan tenant.

## Open follow-ups (not this iteration)

- Public landing page + self-signup flow that actually creates trial tenants
  (currently only the super admin can create tenants).
- Billing/payment integration for plan upgrades.
- Super admin business-metrics dashboard.
