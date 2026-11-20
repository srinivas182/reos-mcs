# ADR 0006: Design tokens pipeline

- Status: Accepted
- Date: 2026-11-20

## Context

Web (Tailwind 4 + shadcn/ui) and mobile (React Native with Unistyles 3) must look the same, follow the journey documents, and meet our accessibility standard: 16-point body text, nothing below 14, 48-point touch targets, WCAG 2.2 AA contrast, plus light, dark and calm high-contrast themes and a user-chosen text size.

## Decision

- `packages/tokens/src/index.ts` is the only source: a raw `palette`, semantic `themes` (light, dark, contrast), type scale, line heights, text scales, spacing, radius, elevation, motion and touch target. It has no imports, so Node runs it directly and Metro bundles it as-is.
- `scripts/build.ts` generates the web outputs into `src/generated/`:
  - `tokens.css` holds the CSS variables per theme (`:root`/`.dark`/`[data-theme]`), mapped onto shadcn/ui names so stock components follow our themes. It also holds the `html[data-text-size]` scaling and the reduced-motion overrides.
  - `tailwind.css` is a Tailwind 4 `@theme` with REOS utilities. It raises the smallest text size to 14 px.
  - `swatches.json` is the colour reference for designers.
- Components use semantic tokens only, never raw palette values.
- Tests (`pnpm --filter @reos/tokens test`) enforce:
  - AA contrast for 23 text and control pairs in every theme
  - identical keys across themes
  - the minimum font size, line heights, touch target and text-scale order
  - generated files that are current

  CI runs them whenever the package changes.
- Mobile reads `themes` and the scales directly (Unistyles themes from Sprint 2.2d).

## Consequences

- Changing a colour is a one-line change with automatic contrast checks; a failing pair blocks the merge.
- The text-size and theme settings work on every page by setting `data-text-size` and `data-theme` on `<html>`.
