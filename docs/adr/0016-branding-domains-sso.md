# ADR 0016: Tenant branding, domains and single sign-on

- Status: Accepted (A4.2a); A4.2b adds custom domains and single sign-on
- Date: 2026-11-21 (Sprint 4, A4.2; spec in docs/sprints/S04-spec.md, decisions 2 and 4)

## Context

REOS is one app for everyone, but members of Texas REALTORS® or Lone Star Realty should see their own organisation. Associations brand the app for their members (web journey screen 57). Each tenant has its own sign-in address with its brand panel (screen 2). The rule is "Brokerage brand wins inside brokerage areas". REOS's design standard requires high contrast, which a tenant's chosen colour must not break.

## Decision (A4.2a)

1. **A brand is a small JSON object on the organisation**: primary colour, app label, logo path, email sender name and reply-to. It is not secret (sign-in pages show it), so it doesn't need row-level security. Changes are audited with before and after values, tagged with the tenant.
2. **Contrast is enforced, not just advised.**
   - White text on the primary colour must reach 4.5:1 (WCAG AA). The server refuses colours below that and names the nearest darker shade that passes.
   - The screen shows the same check live (`lib/colour.ts` mirrors `BrandColour`).
3. **The brand changes the light theme only.**
   - A generated stylesheet sets `--reos-action`, its pressed and tint shades, focus and the active menu item.
   - The dark and high-contrast themes keep REOS's tested colours; the logo and name still carry the brand there.
4. **Which brand shows (`Brands::current`)**:
   - Signed in: the organisation of the role in use, falling back to its parent (team → brokerage → association). So a brokerage's own brand wins inside it, and a brokerage without one shows its association's.
   - Signed out: the tenant whose address it is, `<slug>.<base domain>`. A4.2b adds verified custom domains.
   - REOS super admins: REOS.
   - Only the address decides branding before sign-in. Data access still follows the role in use (ADR 0015).
5. **Logos are re-drawn, never stored as uploaded.**
   - PNG, JPEG or WebP, up to 2 MB, 64 to 4,000 pixels. The image is decoded and re-encoded as a PNG of at most 512 pixels, so scripts, metadata and oversized images don't survive. SVG is refused for that reason.
   - Logos are stored under the tenant's folder (`tenants/<id>/brand/`) and served publicly with a versioned address, a year's cache and `nosniff`.
6. **Email sender:** emails keep REOS's verified sending domain; the tenant chooses the sender name and reply-to. Sending from a tenant's own domain needs that domain verified (A4.2b).
7. **Base domain** comes from `REOS_TENANT_BASE_DOMAIN` (`reos.com` in production, `localhost` locally, because browsers send `*.localhost` to the computer).

## Consequences

- A tenant can't make REOS harder to read, and members see their organisation everywhere they work.
- **Brokerage brands:** brokerages, schools and provider businesses can save a brand at the same address as associations. The broker journey's Brokerage settings (screen 41) doesn't draw a brand card, so where brokers edit it needs a journey decision.
- The phone app shows the brand after sign-in, from the same data, when the phone's shell is themed (later sprint).
