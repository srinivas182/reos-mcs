# ADR 0016: Tenant branding, domains and single sign-on

- Status: Accepted (A4.2a branding, A4.2b custom domains, A4.2c single sign-on)
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
- **Brokerage brands:** brokerages, schools and provider businesses can save a brand at the same address as associations. Brokers see it as a Brand card on Brokerage settings (screen 41), which links to the brand editor (decision of 21 November 2026, A4.2b).
- The phone app shows the brand after sign-in, from the same data, when the phone's shell is themed (later sprint).

## Decision (A4.2b): custom domains

1. **One custom domain per tenant**, held in `tenant_domains` (row-level security, ADR 0015). Hostnames are unique across REOS, need three labels or more (`learn.lonestarrealty.com`) and can't be under the REOS base domain.
2. **Cloudflare for SaaS issues and renews SSL** (`cloudflare-saas` in the integrations register; API token, zone and CNAME target set by super admins). The tenant adds two records: a CNAME to `tenants.reos.com` and a TXT ownership record. Outside production a simulator stands in and goes live on the first check, so local work and tests never call Cloudflare.
3. **Verify DNS** checks at once; a scheduled job (`reos:domains:check`, every 10 minutes) checks domains still waiting, for up to 7 days. Adding, going live and removing are audited with the tenant.
4. **A domain counts only when both the hostname and SSL are active.** Only then does it brand sign-in (`Brands::tenantForHost` looks it up first, cached for 10 minutes and cleared on every change) and replace the slug address on the Tenants screens. The slug address always keeps working.
5. **Where it is managed:** the same `CustomDomainCard` on Branding and domain (screen 57, associations, schools, providers) and Brokerage settings (screen 41, brokers). Screen 41 also lists the people who run the brokerage and its teams with their scope and two-step status, and the plan. Inviting admins and changing permissions come with A4.3.
6. **Removing a domain asks first.** It can't be undone with a toast: putting it back needs DNS checked again and a new certificate.

## Decision (A4.2c): single sign-on

1. **Generic SAML 2.0 and OpenID Connect, one identity provider per tenant** (spec decision 4). No vendor-specific code: associations' member systems, Okta, Microsoft Entra ID, Google Workspace, ADFS and Keycloak all fit.
   - SAML: OneLogin `php-saml` 4 in strict mode (with `xmlseclibs`). REOS sends the AuthnRequest by redirect and takes the response by POST. Settings come from a metadata URL, an uploaded file or the test provider.
   - OIDC: discovery, the authorisation-code flow with state, nonce and PKCE (S256), and an RS256 ID token checked against the provider's published keys (cached for an hour). Built on `firebase/php-jwt`, the same library as Google and Apple sign-in. The client secret is stored encrypted and never sent to the browser or the audit log.
2. **Checks on every answer** (spec security test):
   - SAML: a signature with one of the tenant's certificates, on the response or the assertion; InResponseTo matches the request this browser started; Destination, Recipient, audience and time conditions; deprecated algorithms refused; each assertion ID used once (cache, until it expires).
   - OIDC: signature, issuer, audience, expiry (60 s leeway), nonce. An email the provider marks unverified isn't used.
   - Both: the one-time state in the session (no CSRF token on the callback). SAML's cross-site POST is handed to a same-site GET, as for Apple.
3. **Draft, Tested, On.** Saving settings returns to Draft. Only a passing test sign-in by an admin allows turning it on. So members only ever use settings someone tested. A failed test is recorded with its reason and doesn't switch off SSO members already use.
4. **Who an answer signs in** (stricter than the spec's "link by verified email"):
   - the REOS account already linked to that provider account (`sso_identities`, under row-level security); else
   - the account with that email, **only if it already belongs to the tenant or one of its brokerages, schools or teams, and never a super admin**. An identity provider is trusted for its own members only, so a tenant's IdP can't take over another tenant's member or REOS staff; else
   - a new account with the email verified and the tenant's member role: agent at the brokerage or team, candidate at a school, provider at a provider business. Association members become agents across REOS until member sync or an invite places them in their brokerage (A4.3); the SSO link records their association.
   - Two-step sign-in still applies after SSO, as after a password.
5. **One address per tenant for the provider.** Entity ID, reply URL (ACS) and redirect URI are on the tenant's own address (live custom domain, else `<slug>.reos.com`), so the IdP admin registers them once. Members can start on another address: a brokerage's sign-in page shows its association's button (journey screen 2). REOS hops to the association's address with a one-time key (5 minutes), and after the checks hands the person back with a one-time ticket (60 seconds, only valid on the address it was made for). A start address that isn't the tenant's or one of its children's is never a hand-back target.
6. **Addresses the admin types are fetched safely.** In production, metadata URLs, issuers and the provider's endpoints must be HTTPS on the public internet (no private, loopback or link-local addresses, no user info, no redirects), with 5 s and 10 s timeouts and a 1 MB limit on metadata.
7. **REOS test identity provider (outside production only).** It has its own key pair and certificate (made on first use, kept in `storage/app/private`) and speaks both protocols. Its OIDC discovery, keys and token exchange are answered in-process, so a single-process local server doesn't call itself. Its sign-in page lists the journey personas plus a new member. Its answers go through exactly the same checks as a real provider's. Production refuses it (404, and its issuer isn't recognised).

### Consequences

- An association can switch on member sign-in in an afternoon, with no REOS release, by giving its IdP's metadata or issuer and client.
- Changing settings switches SSO off until retested. That's deliberate; the screen says so before saving.
- Single logout, signed AuthnRequests, encrypted assertions and automatic certificate rollover from a metadata URL are not built. Members of an association with SSO on can still sign in other ways. The phone app's SSO sign-in comes with the phone shell's tenant work.
