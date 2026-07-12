# Tenant Branding & Per-Tenant Storefront

**Date:** 2026-07-13
**Status:** Approved for planning

## Problem

The public storefront (home page: hero, car catalog, booking, contact) is already
tenant-scoped at the data level — `IdentifyTenant` resolves a tenant from the
subdomain (or the logged-in user) and the page renders that tenant's cars and
testimonials. But every brand element on the page is hardcoded to Lajur: the
navbar name/logo, hero eyebrow, contact details (Samarinda address, phone,
email), footer, and the amber accent color. A new tenant's storefront therefore
looks like a Lajur branch, not their business. Tenants also have no way to edit
any of this themselves.

Owner decisions:
- Storefront is available to **all plans, including Basic** (no feature gating).
- Customizable branding v1: display name, tagline, phone/WhatsApp, address,
  email, **logo upload, and accent color**.

## Goals

- A "Pengaturan Situs" page in the tenant admin (`/admin/situs`) where the
  owner/admin edits their storefront branding.
- The public layout and home page read branding from the active tenant, with
  graceful fallbacks to the current Lajur values when a field is empty (so the
  default `lajur` tenant and brand-new tenants both render correctly with zero
  configuration).
- Logo upload follows the existing car-image pattern (`public` disk, 2 MB,
  jpeg/jpg/png/webp; old file deleted on replace).
- Accent color (hex) overrides the gold accent on the tenant's storefront only
  — admin/superadmin dashboards keep Lajur gold.

## Non-goals (explicitly out of scope)

- Custom domains, wildcard-DNS production setup (deployment concern, later).
- Theming beyond one accent color (no font/layout choices).
- Gating the storefront by plan (decided: all plans get it).
- Editing hero headline/body copy or section copy (v1 keeps the strong generic
  copy; only the eyebrow uses the tagline).

## Data model

New nullable columns on `tenants` (all optional; null = use Lajur default):

| column | type | storefront use |
|---|---|---|
| `display_name` | string | navbar brand, `<title>`, footer brand, copyright |
| `tagline` | string | hero eyebrow (fallback: "Rental Mobil Premium · Kalimantan Timur") |
| `contact_phone` | string | contact section, footer, displayed as Telepon/WhatsApp |
| `contact_address` | string | contact section + footer |
| `contact_email` | string | contact section + footer |
| `logo_path` | string | navbar/footer logo `<img>`; fallback: existing route-icon mark |
| `accent_color` | string(7) | hex like `#E7B24C`; overrides `--amber` (+ derived `--amber-600`, `--amber-glow`) via an inline `<style>` in `layouts.public` only |

No new tables. `Tenant::$fillable` extended.

## Branding resolution

`App\Tenancy\Branding` — a small value object built from the current tenant
(`TenantManager::current()`), exposing accessors that fall back to the Lajur
defaults currently hardcoded in the views:

- `name()` → display_name ?? tenant->name
- `tagline()`, `phone()`, `address()`, `email()` → column ?? current hardcoded value
- `logoUrl()` → Storage url ?? null (views render the icon mark when null)
- `accentColor()` → accent_color ?? null (views skip the style override when null)
- `accentDark()` → accent darkened ~15% (computed in PHP) for `--amber-600`
- `accentGlow()` → accent at 30% alpha for `--amber-glow`

Registered in `AppServiceProvider::boot()` via a view composer for
`layouts.public` (and the views that extend it), injected as `$branding`.
Admin/driver/superadmin layouts do not receive it and stay Lajur-branded.

## Settings page (tenant admin)

- Routes inside the existing `admin` group: `GET admin/situs` → `admin.site.edit`,
  `PUT admin/situs` → `admin.site.update`. No feature middleware (all plans).
- Controller `App\Http\Controllers\Admin\SiteSettingController` (edit/update),
  updating the **current** tenant only (`TenantManager::current()`).
- Form request `SiteSettingRequest`: all fields nullable; `accent_color`
  regex `/^#[0-9A-Fa-f]{6}$/`; `logo` follows CarRequest's image rules; separate
  `remove_logo` checkbox deletes the stored file and nulls the column.
- Logo file: `$request->file('logo')->store('logos', 'public')`; replacing or
  removing deletes the old file (same as CarController).
- View `admin/site.blade.php` using existing panel/field classes, with a color
  `<input type="color">` + text fallback, logo preview, and a link to view the
  live storefront (route('home'), which for a logged-in owner already resolves
  to their own tenant via IdentifyTenant's user-first resolution).
- Sidebar `layouts/admin.blade.php` gains a "Situs" nav item (settings icon),
  visible to every plan.

## Public views wiring

`layouts/public.blade.php`: title, navbar brand (logo img or icon + name),
footer brand/contact, copyright line, and — when `accentColor()` is set — an
inline `<style>` overriding `--amber`, `--amber-600`, `--amber-glow` on `:root`.
`home.blade.php`: hero eyebrow ← tagline; contact section ← phone/address/email.
Signup pages (`/daftar`) stay Lajur-branded (they are the platform's own pages)
— acceptable v1 simplification: they also extend `layouts.public`, so they will
show tenant branding only when reached under a tenant context; in practice they
are linked from the Lajur default site.

## Testing

- Branding fallback: home under the default `lajur` tenant renders the current
  Lajur strings with no columns set (regression: existing HomeTest-style checks
  keep passing unmodified).
- Settings update: owner saves all fields; values appear on `/` for that tenant.
- Logo upload (Storage::fake) + replace deletes old file + remove_logo works.
- accent_color validation rejects bad hex; inline style present when set,
  absent when null.
- Isolation: tenant B's branding never renders under tenant A's context.
- Non-manager (driver) cannot access `/admin/situs` (existing admin middleware).

## Open follow-ups (not this iteration)

- Custom domain mapping per tenant.
- Storefront copy editing (hero headline etc.).
- Per-tenant OG/social image.
