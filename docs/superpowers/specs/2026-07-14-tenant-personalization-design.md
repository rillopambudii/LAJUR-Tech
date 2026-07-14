# Tenant Storefront Personalization (Font + UI Style)

**Date:** 2026-07-14
**Status:** Approved for planning
**Supersedes:** `2026-07-14-tenant-storefront-themes-design.md` (rejected —
bundled dark/neon themes looked poor once applied to a real tenant's own
branding/photos).

## Problem

The owner wants tenants to be able to personalize their storefront's look
from `/admin/situs` beyond the accent color that already exists. The earlier
"3-4 bundled named themes" design (Klasik/Dark/Elegant/Neon) was rejected
after seeing live previews: dark backgrounds don't reliably look good against
an arbitrary tenant's own accent color, logo, and car photos, and bundling
font+shape+spacing+color-mode into one named choice removed control the
owner actually wanted.

## Goals

- Two new **independent** personalization controls, in addition to the
  existing accent-color picker: **Gaya Font** (font pairing) and **Gaya UI**
  (corner-radius + section-spacing style). A tenant can mix any font choice
  with any UI style choice — they are not bundled.
- **Light backgrounds only.** No dark-mode option in this iteration — every
  choice keeps the existing `--ivory`/`--white`/`--ink` light-surface tokens
  untouched. This directly addresses the rejection reason.
- **Five choices each**, covering a real range without being overwhelming:
  - Gaya Font: Klasik, Netral, Ramah, Elegan, Korporat
  - Gaya UI: Klasik, Tegas, Lembut, Minimalis, Playful
- **No plan gating.** Every tenant, on every plan (Basic included), gets the
  full set of both pickers. This is a deliberate scope simplification versus
  the rejected design's per-plan unlock logic — confirmed with the owner.
- Reuses the exact mechanism the accent-color feature already proved out: a
  `Branding`-computed inline `<style>` override block in `layouts.public`,
  not new CSS classes scattered through `app.css`. Font/UI-style values are
  just more CSS custom properties resolved server-side per tenant, injected
  alongside the existing accent override.
- Klasik + Klasik (font and UI style both default) is a genuine no-op —
  identical output to today, matching the fallback discipline already
  established for every other branding field (`null` → today's hardcoded
  value).

## Non-goals (explicitly out of scope)

- Dark mode / any background color-mode change (rejected direction).
- Plan-based gating of font/UI-style choices (explicitly declined by owner).
- Free-text font entry or arbitrary Google Fonts URL input — only the five
  curated pairings per control, for the same "don't let a tenant accidentally
  ship an ugly combination" reason the original accent-color-only design
  already avoided for shape/typography.
- Applying personalization to admin/driver/superadmin dashboards — same
  boundary as accent color and the (superseded) theme feature: storefront
  (`layouts.public`) only.
- Live/instant preview while adjusting the picker (out of scope, same as the
  branding feature before it).

## Data model

Two new nullable columns on `tenants`:
- `font_style` (string, one of `klasik|netral|ramah|elegan|korporat`, null = `klasik`)
- `ui_style` (string, one of `klasik|tegas|lembut|minimalis|playful`, null = `klasik`)

## Style definitions

### Gaya Font (`--font-display` / `--font-body` overrides)

| Key | Display font | Body font | Notes |
|---|---|---|---|
| `klasik` (default) | Sora | Plus Jakarta Sans | today's exact fonts, no override needed |
| `netral` | Inter | Inter | one clean neutral face for both roles |
| `ramah` | Poppins | Plus Jakarta Sans | rounder, friendlier display face |
| `elegan` | Playfair Display | Plus Jakarta Sans | serif display — the one carried over from the rejected theme spec, still a deliberate non-default choice, not Fraunces/Instrument Serif |
| `korporat` | Space Grotesk | Inter | grotesk display, businesslike |

New font families (Poppins, Playfair Display, Space Grotesk, Inter) are all
added to the single existing Google Fonts `<link>` in `layouts.public` —
every family loads on every storefront request regardless of which tenant is
active, avoiding conditional per-tenant `<link>` generation. This is a
deliberate simplicity-over-byte-savings tradeoff consistent with this
project's existing "no new CSS/JS build step" constraint; acceptable because
these are the only 4 new families ever added (curated list, not open-ended).

### Gaya UI (`--radius-*` / section `padding-block` overrides)

| Key | `--radius-sm` / `--radius` / `--radius-lg` / `--radius-pill` | Section spacing | Notes |
|---|---|---|---|
| `klasik` (default) | 8 / 14 / 22 / 999px | 92px | today's exact values, no override needed |
| `tegas` | 4 / 8 / 12 / 8px | 72px | tighter corners, denser rhythm — "corporate" |
| `lembut` | 10 / 18 / 28 / 999px | 120px | softer corners, airier rhythm — "premium" |
| `minimalis` | 2 / 4 / 8 / 4px | 92px | near-sharp corners, standard rhythm |
| `playful` | 12 / 20 / 30 / 999px | 92px | the roundest, largest-radius option |

All values are colors-untouched — `--ivory`, `--white`, `--ink`,
`--ivory-200`, `--petrol` and every background/text token stay exactly as
they are today, in every combination. This is the structural guarantee that
prevents a repeat of the rejected design's contrast/legibility problems: the
prior bugs (invisible nav text, unreadable headings) were caused entirely by
overriding those shared color tokens, which this design never touches.

## Enforcement

None — no plan check. `SiteSettingRequest` validates `font_style` is one of
the five keys (`Rule::in(['klasik','netral','ramah','elegan','korporat'])`)
and `ui_style` similarly, purely for data integrity, not access control.

## Settings page changes

`/admin/situs` gains two new picker sections below the existing branding
fields: "Gaya Font" and "Gaya UI", each five radio-style cards. Font cards
render their own name in the actual paired font (a live self-demonstrating
sample). UI-style cards show a small static swatch illustrating the corner
radius (e.g. a rounded rectangle at that style's `--radius` value).

## Testing

- Klasik + Klasik (or nulls) renders byte-identical output to today — reuses
  the existing `StorefrontBrandingTest` default-tenant assertions as the
  regression guard.
- Setting `font_style=elegan` renders Playfair Display in the font-family
  CSS output; setting `ui_style=tegas` renders the tighter radius values.
- Both controls are independent: `font_style=elegan` + `ui_style=playful`
  together render both overrides simultaneously without interference.
- Any tenant, regardless of `plan`, can set any of the five values for
  either control (no 422 from a plan check — explicit regression test that
  a Basic-plan tenant can set `ui_style=lembut`, proving the no-gating
  decision holds).
- Accent color continues to render identically regardless of font/UI-style
  selection (cross-cutting regression, same as the branding feature's own
  test suite already checks for theme-independence).

## Open follow-ups (not this iteration)

- A from-scratch dark mode, revisited later with more care around
  per-tenant contrast (the rejection reason), possibly with a live preview
  so the owner/tenant can catch bad combinations before saving.
- Live preview of font/UI-style changes before saving.
- Tokenizing spacing beyond the single section-rhythm value.
