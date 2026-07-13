# Tenant Storefront Themes

**Date:** 2026-07-14
**Status:** Approved for planning

## Problem

Tenants can already customize their storefront's identity (name, tagline,
contacts, logo, accent color) via `/admin/situs`, but every tenant's page
otherwise looks structurally identical — same corner radius, same fonts, same
section spacing. The owner wants a plan-gated "theme" choice: three distinct
visual personalities a tenant picks from, with higher plans unlocking more
choices, layered on top of (not replacing) the existing accent-color picker.

## Goals

- Three named themes — **Klasik**, **Modern**, **Elegan** — each a distinct
  combination of corner-radius scale, font pairing, and section spacing
  density, applied via one `<body>` class that overrides existing CSS custom
  properties. No component markup changes; no per-theme rewrite of `app.css`.
- Plan-gated, cumulative unlock: Basic → Klasik only. Pro → Klasik + Modern.
  Business → all three (Klasik + Modern + Elegan).
- A theme picker added to the existing `/admin/situs` page, showing only the
  themes the tenant's plan unlocks (locked ones shown but disabled with an
  "upgrade to unlock" hint, consistent with how gated nav items already work
  elsewhere in the app).
- Accent color stays fully independent of theme — whichever theme is active,
  the tenant's chosen accent color (or the Lajur default) still drives
  buttons/highlights exactly as it does today.
- Klasik is a genuine no-op: it IS today's exact visual output. A tenant who
  never touches the theme picker, or a Basic tenant with no other choice,
  sees byte-identical output to before this feature.

## Non-goals (explicitly out of scope)

- Changing which components exist or their markup/structure — only tokens
  (CSS custom properties) change per theme.
- Per-section spacing control beyond the single `.section` vertical rhythm
  token (`padding-block`) — no attempt to tokenize every padding/margin in
  `app.css`.
- Theme preview/live-editing UI beyond static swatches on the picker cards.
- Applying themes to the admin/driver/superadmin dashboards — themes are a
  storefront-only (public-layout) concept, same boundary as accent color.

## Data model

One new nullable column: `tenants.theme` (string, values `klasik|modern|elegan`,
null = `klasik`). Reuses the existing `Tenant`/`Branding` plumbing pattern
from the branding feature.

## Theme definitions

Each non-default theme is a `<body class="theme-modern">` /
`<body class="theme-elegan">` toggle in `layouts.public`, paired with a CSS
block that overrides the following existing custom properties (no new
component CSS, no new selectors beyond the theme-scoped `:root` override):

| Token | Klasik (today, no override) | Modern | Elegan |
|---|---|---|---|
| `--radius-sm` / `--radius` / `--radius-lg` / `--radius-pill` | 8 / 14 / 22 / 999px | 4 / 8 / 12 / 8px (buttons stay a shallow rounded rect, not sharp squares — see anti-pattern note below) | 10 / 18 / 28 / 999px |
| `--font-display` | Sora | existing `Plus Jakarta Sans` reused as display (avoids a new font-file network dependency) | a new Google Font pairing added to the existing `<link>` in `layouts.public` (see Implementation note) |
| `--font-body` | Plus Jakarta Sans | Inter (already a safe, near-universally-cached system-adjacent choice; added to the same `<link>`) | existing `Plus Jakarta Sans` reused as body (keeps Elegan to one net-new font file, on the display role only) |
| Section rhythm | `.section { padding-block: 92px }` | `72px` (denser) | `120px` (airier) |

**Anti-pattern note (from the design-taste-frontend skill already in use on
this project):** a literal sharp-corner (`border-radius: 0`) "Modern" theme
reads as an AI-default brutalist reach, not intentional. Modern here means
*tighter, more corporate rounding*, not zero-radius — kept deliberately
modest per that skill's Shape Consistency Lock guidance (one radius scale,
consistently applied, per theme).

## Enforcement

Reuses the exact pattern `Tenant::hasFeature()` already established, but
themes aren't a boolean feature — they're a ranked list. Add
`Tenant::unlockedThemes(): array` returning the cumulative list for the
tenant's plan (`basic → ['klasik']`, `pro → ['klasik','modern']`,
`business → ['klasik','modern','elegan']`). `SiteSettingRequest` validates
the submitted `theme` value is in `$tenant->unlockedThemes()` (not just any
of the three) — server-side enforcement, not just a disabled UI card, exactly
like every other plan gate in this app.

If a tenant's plan is later downgraded (e.g. a lapsed subscription drops
Business → Basic) while `theme=elegan` is stored, `Branding` resolves the
effective theme as `in_array($tenant->theme, $tenant->unlockedThemes()) ?
$tenant->theme : 'klasik'` — the stored value is left untouched (so upgrading
back restores their prior choice) but rendering silently falls back to what
their current plan actually allows.

## Settings page changes

`/admin/situs` gains a "Tema Situs" panel: three cards (same visual language
as the existing `/daftar` and `/admin/langganan` plan cards), each showing a
small static swatch (radius + font sample), a "Pilih" button for unlocked
themes, and a disabled "Upgrade untuk membuka" state for locked ones.

## Testing

- Klasik output is byte-identical to pre-feature output (no `theme-*` body
  class, no property overrides) — regression guard reusing the existing
  `StorefrontBrandingTest` default-tenant assertions.
- Basic tenant can select Klasik, cannot select Modern/Elegan (422 on direct
  POST).
- Pro tenant can select Klasik or Modern, not Elegan.
- Business tenant can select any of the three.
- Downgraded tenant (plan dropped below their stored theme) renders Klasik
  without losing the stored `theme` column value.
- Accent color renders identically regardless of active theme (cross-cutting
  regression check).

## Open follow-ups (not this iteration)

- Live preview of a theme against the tenant's actual storefront before
  saving.
- A fourth "custom" theme tier combining arbitrary token choices.
- Tokenizing per-section spacing beyond the single global rhythm value.
