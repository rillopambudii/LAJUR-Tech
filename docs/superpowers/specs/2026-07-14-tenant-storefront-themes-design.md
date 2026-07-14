# Tenant Storefront Themes

**Date:** 2026-07-14
**Status:** SUPERSEDED — owner rejected the dark/neon direction after seeing
screenshots ("terlalu gelap, kurang bagus dipakai tenant"). Replaced by
`2026-07-14-tenant-personalization-design.md`, which drops bundled
named themes entirely in favor of independent, light-only, ungated
font-pairing + UI-style controls. Kept here for history only — do not
implement this document.

## Problem

Tenants can already customize their storefront's identity (name, tagline,
contacts, logo, accent color) via `/admin/situs`, but every tenant's page
otherwise looks structurally identical — same light background, same corner
radius, same fonts, same section spacing. The owner wants a plan-gated
"theme" choice: distinct visual moods a tenant picks from, with higher plans
unlocking more choices, layered on top of (not replacing) the existing
accent-color picker.

## Goals

- Four named themes — **Basic**, **Dark**, **Elegant**, **Neon** — each a
  distinct combination of background/text color mode, corner-radius scale,
  font pairing, and section spacing, applied via one `<body>` class that
  overrides existing CSS custom properties. No component markup changes; no
  per-theme rewrite of `app.css`.
- Plan-gated, cumulative unlock: **Basic plan → Basic theme only. Pro plan →
  Basic + Dark (2 themes). Business plan → all four** (Basic + Dark + Elegant
  + Neon).
- A theme picker added to the existing `/admin/situs` page, showing only the
  themes the tenant's plan unlocks (locked ones shown but disabled with an
  "upgrade to unlock" hint, consistent with how gated nav items already work
  elsewhere in the app).
- Accent color stays fully independent of theme in every case, including
  Neon: Neon does not introduce a fixed signature color — it takes whatever
  accent color the tenant already picked (or the Lajur default) and renders
  it with a stronger glow/shadow treatment on a dark background. A tenant
  with a green accent gets a "neon green" look; a tenant with a red accent
  gets "neon red."
- Basic is a genuine no-op: it IS today's exact visual output. A tenant who
  never touches the theme picker, or a Basic-plan tenant with no other
  choice, sees byte-identical output to before this feature.

## Non-goals (explicitly out of scope)

- Changing which components exist or their markup/structure — only tokens
  (CSS custom properties) change per theme.
- Per-section spacing control beyond the single `.section` vertical rhythm
  token (`padding-block`) — no attempt to tokenize every padding/margin in
  `app.css`.
- Theme preview/live-editing UI beyond static swatches on the picker cards.
- Applying themes to the admin/driver/superadmin dashboards — themes are a
  storefront-only (public-layout) concept, same boundary as accent color.
- A separately configurable "Neon glow intensity" — the glow strength is
  fixed per-theme, not a further tenant-tunable dial.

## Data model

One new nullable column: `tenants.theme` (string, values
`basic|dark|elegant|neon`, null = `basic`). Reuses the existing
`Tenant`/`Branding` plumbing pattern from the branding feature.

## Theme definitions

Each non-default theme is a `<body class="theme-dark">` (or
`theme-elegant` / `theme-neon`) toggle in `layouts.public`, paired with a CSS
block that overrides existing custom properties (no new component CSS, no
new selectors beyond the theme-scoped `:root` override). Dark and Neon reuse
`--petrol`/`--ivory` as the dark-surface/light-text pairing already proven
elsewhere in this codebase (the existing footer and testimonial cards use
exactly this pairing today), rather than inventing new dark-mode colors from
scratch.

| Token | Basic (today, no override) | Dark | Elegant | Neon |
|---|---|---|---|---|
| Page background | `--ivory` (light) | `--petrol` (dark navy — same value already used for the footer) | `--ivory` (light) | `--petrol` (dark navy) |
| Card/surface background | `--white` | `--petrol-600` (a lighter dark, existing token) | `--white` | `--petrol-600` |
| Body text | `--ink` (near-black) | `--ivory` (off-white — existing footer text color) | `--ink` | `--ivory` |
| Borders | `--ivory-200` | `rgba(255,255,255,.12)` (existing pattern, used today in the footer/nav-dropdown dark contexts) | `--ivory-200` | `rgba(255,255,255,.12)` |
| Corner radius scale | 8 / 14 / 22 / 999px (today) | same as Basic (pure color-mode swap, no shape change) | 10 / 18 / 28 / 999px (softer) | same as Basic |
| `--font-display` | Sora | Sora (unchanged) | Playfair Display (new — see font note) | Sora (unchanged) |
| `--font-body` | Plus Jakarta Sans | Plus Jakarta Sans (unchanged) | Plus Jakarta Sans (unchanged) | Plus Jakarta Sans (unchanged) |
| Section rhythm | `padding-block: 92px` | same as Basic | `120px` (airier, premium feel) | same as Basic |
| Accent glow | `--amber-glow`-style shadow, as today | same as Basic | same as Basic | **stronger**: glow blur/spread roughly doubled, applied to buttons and card hover states, using the tenant's own accent color (see Enforcement) |

**Font note:** Elegant is the only theme that changes typography, using
Playfair Display (added to the existing Google Fonts `<link>` in
`layouts.public`) — a deliberate serif choice for the premium tier, NOT the
LLM-default Fraunces/Instrument Serif that design tooling tends to reach for
by default.

**Neon implementation detail:** the "glow" is a CSS-only effect — a larger
`box-shadow` blur radius and a second, more saturated shadow layer around
already-glowing elements (`.btn-primary`, `.plan-card:hover`, etc.), computed
from the SAME `Branding::accentGlow()` value already used today, just with a
higher opacity/blur multiplier in the Neon theme's override block. No new
color computation, no new PHP method — the existing `accentColor()` /
`accentGlow()` accessors from the branding feature are reused as-is; only the
CSS consuming them changes shape under `.theme-neon`.

## Enforcement

Reuses the exact pattern `Tenant::hasFeature()` already established, but
themes aren't a boolean feature — they're a ranked list. Add
`Tenant::unlockedThemes(): array` returning the cumulative list for the
tenant's plan:
- `basic` → `['basic']`
- `pro` → `['basic', 'dark']`
- `business` → `['basic', 'dark', 'elegant', 'neon']`

`SiteSettingRequest` validates the submitted `theme` value is in
`$tenant->unlockedThemes()` (not just any of the four) — server-side
enforcement, not just a disabled UI card, exactly like every other plan gate
in this app.

If a tenant's plan is later downgraded (e.g. a lapsed subscription drops
Business → Basic) while `theme=neon` is stored, `Branding` resolves the
effective theme as `in_array($tenant->theme, $tenant->unlockedThemes()) ?
$tenant->theme : 'basic'` — the stored value is left untouched (so upgrading
back restores their prior choice) but rendering silently falls back to what
their current plan actually allows.

## Settings page changes

`/admin/situs` gains a "Tema Situs" panel: four cards (same visual language
as the existing `/daftar` and `/admin/langganan` plan cards), each showing a
small static swatch (background + radius + font sample), a "Pilih" button
for unlocked themes, and a disabled "Upgrade untuk membuka" state for locked
ones.

## Testing

- Basic output is byte-identical to pre-feature output (no `theme-*` body
  class, no property overrides) — regression guard reusing the existing
  `StorefrontBrandingTest` default-tenant assertions.
- Basic-plan tenant can select Basic, cannot select Dark/Elegant/Neon (422 on
  direct POST for each).
- Pro-plan tenant can select Basic or Dark, not Elegant or Neon.
- Business-plan tenant can select any of the four.
- Downgraded tenant (plan dropped below their stored theme) renders Basic
  without losing the stored `theme` column value.
- Accent color renders identically (same hex driving buttons/highlights)
  regardless of active theme — only the glow intensity differs under Neon,
  the base color itself never changes.

## Open follow-ups (not this iteration)

- Live preview of a theme against the tenant's actual storefront before
  saving.
- A fifth "custom" theme tier combining arbitrary token choices.
- Tokenizing per-section spacing beyond the single global rhythm value.
