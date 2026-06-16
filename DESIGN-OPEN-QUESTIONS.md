# Design Open Questions

Cases not fully covered by ContentGuidelines, resolved with the conservative
variant per §12. Review with the product owner.

## 1. Token naming collision: `--color-accent`
- **Issue:** ContentGuidelines §2.2 names the green progress colour `--color-accent`
  (`#34D399`). The Flux UI kit reserves `--color-accent` for its *action* colour
  (used by buttons, links, focus rings).
- **Resolution:** Flux's `--color-accent` is mapped to our **primary violet**
  (`#8B5CF6`) so "violet = action" holds. The green is exposed as
  `--color-success` (identical hex) and used wherever the design says
  "accent/green" (progress, streak, "Fertig" badges, flip-card Merkhilfe label).
- **Impact:** Chart colours (§2.5) reference `--color-accent` for the primary
  series — to be wired in the statistics milestone (M4) using `--color-success`.

## 2. Inter font self-hosting — RESOLVED
- **Issue:** ContentGuidelines §3 mandates self-hosted Inter woff2 (no Google CDN).
- **Resolution:** `vite.config.js` self-hosts Inter (weights 400/500/600/700) via
  `laravel-vite-plugin`'s Bunny Fonts integration; the woff2 files are emitted to
  `public/build/assets/` at build time and injected through `@fonts`. No CDN.
