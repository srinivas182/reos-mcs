# Sprint 4: Tenancy, calendars and voice (18 to 29 January 2027)

**Goal:** by the end of the sprint, every association, brokerage and school is a tenant whose data no other tenant can read. Each tenant can carry its own brand, domain and single sign-on, and invites its people into seats. Agents' Google or Outlook calendars sync both ways. Anyone can say "Remind me to call Maria at 4" and get the reminder on the lock screen.

**Status:** decisions taken 21 November 2026 (section 6). Source: REOS Sprint Plan v1.0 (Sprint 4: A4.1–A4.3, B4.1–B4.3), the Web App Journey (screens 55, 57, 59, 60, 71), the broker, agent and Connections and Sync journeys.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 4 | Already in place | Sprint 4 now does |
| --- | --- | --- |
| Tenant types and hierarchy (A4.1) | `organisations` (type, name, parent), pulled forward in A3.2; roles are Spatie "teams" per organisation | Adds the tenant fields (slug, status, states, plan, limits, region cell), the current-tenant context and database-enforced isolation |
| Audit log (A4.3) | `audit_events` (2.4a); sign-ins, roles, connections and the catalogue are audited | Audits every remaining admin action and adds the audit screen with export (screen 71) |
| Google Calendar (B4.1) | B3.1 engine: OAuth, encrypted tokens, sync runs, webhooks, health; Google Calendar reads events on the Simulator | Writes back (two-way), push channels, conflicts, and the REOS calendar the events land in |

**Change from the journeys (decision 1):** REOS syncs **calendars only**. Gmail and Outlook mail are not read or sent. Screens in the agent journeys that sort the inbox into "needs reply" or log emails to contacts are dropped. "Email forwarding to REOS" stays as the documented fallback for anyone who wants an email on a contact.

**Architecture note:** the project brief's default is one deployment and database per customer. For REOS that was replaced in November 2026 by **one platform for every state and tenant**, so isolation is enforced inside one database (section 3, A4.1).

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Cloudflare account with Cloudflare for SaaS on the reos.com zone (100 custom domains free, then $0.10 each per month) | A4.2 | Client | Domains run on the Simulator: verification and SSL steps are simulated; branded sign-in works on `<slug>.reos.com` |
| Google Cloud project: OAuth consent screen with **calendar scopes only** (sensitive; standard Google verification, no security assessment) | B4.1 | Client with Mayura | Built and tested on the Simulator and recorded sandbox calls; real accounts are switched on in Settings › Integrations |
| Microsoft Entra app registration with publisher verification (Calendars.ReadWrite) | B4.2 | Client with Mayura | Same as Google |
| AWS account: Amazon Bedrock (Claude) and Amazon Transcribe in us-east-2 | B4.3 | Client | Parsing runs on the built-in date parser; Transcribe is only the fallback for phones without on-device speech |
| An association's identity provider details (SAML metadata or OIDC issuer) | A4.2 | Texas REALTORS | SSO is built and tested against a local test identity provider; switched on per tenant |

## 2. Market leaders: what they do, where they fall short, what we do

Benchmarks from the sprint plan: BoldTrail and Lone Wolf for tenant branding; Todoist and Google Assistant for voice capture. Calendar sync is compared with Follow Up Boss and Lofty.

| Leader | What they do well (we match it) | Where they fall short | REOS answer in this sprint |
| --- | --- | --- | --- |
| **BoldTrail (Inside Real Estate)** | Brokerage-branded agent sites and CRM, custom domains for sites | Branding stops at the website; the back office and app look like BoldTrail; brand changes need support tickets | **Self-serve brand** (logo, colours, app label, email sender) that carries into the app, emails and sign-in, previewed live before saving |
| **Lone Wolf** | Back office set up per brokerage; association products (zipForm) tied to membership | Separate products with separate logins; SSO per product | **One sign-in**: association SSO (SAML or OIDC) on the tenant's own domain, then every role in one app |
| **Follow Up Boss / Lofty** | Two-way Google and Outlook calendar sync | Sync settings are hidden; conflicts are silent (last write wins without saying) | **Two-way with a visible rule**: newest change wins, and the losing change is kept for 30 days and shown on the event, with an Undo option |
| **Todoist Ramble** | Speak naturally; dates, priorities and people extracted live | Audio goes to the cloud; about 62% of captures succeeded in December 2025; you still open the app | **On-device speech** (private, instant, free), Claude for meaning, and a **confirm card** before saving. Reminders fire **on the lock screen** with Done and Snooze |
| **Google Assistant** | Hands-free "remind me" from the lock screen | Reminders live in Google, not in the agent's CRM or deals | **Siri and Google shortcuts** open REOS voice. The to-do links to the contact and syncs to the agent's calendar |

**Our own additions:**

1. **Isolation tests in CI.** An automated suite tries to read every tenant table from another tenant and must fail at the database itself, not only in code.
2. **Support sign-in with consent.** REOS support can enter a tenant only after the owner approves. Access lasts 60 minutes, a banner shows on every page, and everything is audited.
3. **Brand preview.** The tenant sees the sign-in page, an email and the app header in its brand before saving. Contrast is checked against our high-contrast standard, and colours that fail are refused with a suggested fix.
4. **Invites that close the loop.** Invites by link, email or text, expiring in 14 days with one reminder, and a live roster of who joined.
5. **Calendar conflict trail.** No silent overwrites (see the Follow Up Boss / Lofty row).
6. **Voice that works offline.** A to-do spoken without signal is kept on the phone and synced when back online. The reminder is scheduled on the phone, so it still fires.

## 3. Sub-sprints

### A4.1 Tenants (Team A)

- **Tenant model:** organisations gain:
  - `slug` (for `<slug>.reos.com`) and `status` (active, suspended, archived).
  - `states` (where it operates), `plan`, `region` and `cell` (for later moves between cells).
  - `limits`: seats, AI credits, API rate limit, retention in days.
  - `features` (switches).
- **Hierarchy:** platform › association › brokerage › team; school and provider business sit beside them. A parent can see roll-ups but never a child's private data (for example, an agent's own CRM).
- **Current tenant:** resolved from the domain (custom domain or slug), otherwise from the active role. It's held in a `CurrentTenant` context and carried into queued jobs, cache keys, file paths and search filters.
- **Isolation at the database (PostgreSQL row-level security):**
  - Every tenant-owned table has `organisation_id` and a policy `organisation_id = current_setting('reos.tenant')`.
  - The app sets the tenant per request and per job with `SET LOCAL`.
  - Platform work (super admin, schedulers) runs under a separate database role that bypasses RLS, so nothing else can skip the policies.
  - Eloquent scopes add the same filter in code, as defence in depth.
- **Tenant-aware services:**
  - Cache keys are prefixed with the tenant.
  - Queue jobs carry the tenant and restore it.
  - Files live under `tenants/<id>/`.
  - Meilisearch indexes carry a tenant filter enforced by tenant tokens.
- **Done when:** the isolation test suite proves no cross-tenant reads, at the database and through the API, on PostgreSQL in CI.

### A4.1b Super admin: Tenants (Team A, screens 59 and 60)

- **Screen 59:** a table with type, state, plan, users, domain, cell and status; New tenant; filters; open a tenant.
- **Screen 60:** a tenant in tabs:
  - Overview; Features (switches); Limits and data (seats, credits, rate limit, region, retention); People; Domain and brand (read only); Audit.
- **Support sign-in with consent:**
  - The tenant owner approves in the app or by email.
  - Access lasts 60 minutes, shows a banner and is audited.
  - It never includes reading an agent's personal connections.

### A4.2 Branding and domains (Team A, screen 57, and brokerage settings)

- **Brand:**
  - Logo (SVG or PNG, scanned and resized) and app label.
  - Primary and accent colours, checked for contrast and stored as tokens.
  - Email sender name and reply-to. Sending stays on REOS's verified domain unless the tenant verifies its own.
- **Co-branding rule:** the brokerage brand wins inside brokerage areas. The association brand shows on association areas and on sign-in from the association's domain.
- **Custom domains (Cloudflare for SaaS):**
  - The tenant enters `learn.example.com`, REOS shows the CNAME and TXT records, and **Verify DNS** checks them.
  - Cloudflare issues SSL automatically, and the status shows on screen 57.
  - Runs on the Simulator until the account exists.
- **Branded sign-in:** on the tenant's domain or `<slug>.reos.com`, the sign-in page carries the tenant's logo, colours and name. On the phone, the brand applies after sign-in.
- **Single sign-on (generic SAML 2.0 and OIDC):**
  - Set up on screen 57 with metadata URL or upload, issuer, client and test sign-in.
  - First sign-in links to an existing account by verified email or creates one with the member role. Status shows on screen 57.
- **Done when:** `learn.<tenant>.com` (simulated in development) serves the branded sign-in, and an SSO test sign-in works against the test identity provider.

### A4.3 Membership and audit (Team A)

- **Invites:**
  - Single or bulk (paste emails or phone numbers, or upload a CSV), by email or text, with the role and team.
  - Links and codes expire in 14 days, with one reminder at day 7.
  - Accepting creates or links the account and fills a seat.
- **Seats:** a seat count per tenant and role, and who pays (brokerage pays or the agent pays). Invites stop at the limit, with a clear message and a request to the owner for more seats.
- **Several tenants:** one person can hold memberships in several tenants (for example an association and a brokerage). The role switcher (A3.2) already lists them, and the tenant follows the role.
- **Audit:**
  - Every admin action is audited (tenant settings, brand, domain, SSO, invites, seats, features, support sign-in), each with the actor, tenant, before and after values, and IP.
  - Screen 71 shows the audit log with filters and CSV export. Privacy requests are listed (TDPSA, 45-day clock).
- **Done when:** every admin action is in the audit log, proven by a test that walks each admin route and checks for an audit row.

### B4.1 Google Calendar, two-way (Team B)

- **Calendar only (decision 1):** scopes `calendar.events` and `calendar.readonly` for the calendar list. The register entry becomes **Google Calendar** (no Gmail).
- **REOS calendar:**
  - Events have a title, start and end, all-day, location, attendees, a link to a contact or deal, and a source.
  - Events made in REOS (appointments, to-do due dates, showings) are written to the agent's chosen Google calendar.
  - Google changes come back by push channels (`events.watch`, renewed before expiry) and incremental sync tokens.
- **Conflicts:** newest change wins, and the losing version is kept for 30 days with Undo (section 2).
- **Screens:**
  - Phone: the Today agenda and a "New appointment" sheet, per the agent app journey.
  - Web: the calendar card on the agent dashboard (screen 12) and Connections › Manage › "Calendar to write to".
- **Done when:** an event made in REOS appears in Google and an edit in Google comes back (contract tests on recorded calls, plus the Simulator end to end).
- **As built (ADR 0017):** in two parts. **B4.1a**: the engine, conflicts, calendar choices (web Manage, phone screen 16) and the API. **B4.1b**: the Today agenda, New appointment sheet and dashboard card. The calendar list uses `calendar.calendarlist.readonly`, narrower than `calendar.readonly`. The register id is now `google-calendar`.

### B4.2 Microsoft 365 calendar, two-way (Team B)

- Microsoft Graph with the `Calendars.ReadWrite` scope.
- Delta queries for changes; subscriptions with lifecycle notifications, renewed before they expire.
- The same REOS calendar, conflict rule and screens as Google. **Outlook mail is not read or sent (decision 1).**
- **Done when:** same as Google, for Outlook.

### B4.3 Voice to-dos and reminders (Team B)

- **To-dos:** title, notes, due date and time, reminder, a link to a contact or deal, done, and source (typed or voice).
- **Capture:**
  - Hold the mic on the Ask bar or the Today screen.
  - On-device speech-to-text (`expo-speech-recognition`, on-device when the phone supports it; Amazon Transcribe as fallback).
  - The text goes to `POST /api/v1/voice/parse`, where Claude on Amazon Bedrock returns to-dos, notes, dates and contact matches. A built-in date parser covers simple phrases and runs when AI is off.
  - A **confirm card** shows what will be saved, editable, in English or Spanish.
- **Reminders:**
  - Scheduled on the phone (`expo-notifications`, local), with Done and Snooze actions available on the lock screen.
  - Due dates are written to the connected calendar (B4.1 and B4.2).
  - Server push for other devices comes with B6.2.
- **Shortcuts:** "Hey Siri, add a to-do in REOS" (App Intents) and an Android app shortcut open voice capture directly.
- **Offline:** captures are queued on the phone and parsed when back online. A reminder with a clear time is scheduled immediately by the date parser.
- **Credits:** each parse uses AI credits from the seat's monthly pool (the credit ledger is a stub in this sprint; full credits arrive in Sprint 6).
- **Done when:** "Remind me to call Maria at 4" creates the to-do, links Maria, and the reminder fires on the lock screen at 4 PM with Done and Snooze.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the locked journey screens side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - Cross-tenant reads fail at the database.
  - Support sign-in needs consent and expires after 60 minutes.
  - Invites can't be reused or used after expiry.
  - SSO assertions are signature-checked and replay-protected.
  - Uploaded logos are validated and re-encoded.
- **ADRs:** tenancy and isolation; branding, domains and SSO; two-way calendar sync; voice capture.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Row-level security missed on a new table | A CI check lists every table with `organisation_id` and fails if it has no policy. The isolation suite runs on PostgreSQL |
| RLS cost on large tables | Indexes lead with `organisation_id`; policies are simple equality checks; tested with seeded volume |
| Cloudflare account late | Domains on the Simulator; `<slug>.reos.com` covers the pilot |
| Google or Microsoft verification slow | Calendar-only scopes keep Google to the standard review; the Simulator covers the build |
| Speech quality in noisy cars, or Spanish accents | On-device first with Transcribe fallback; the confirm card catches mistakes; Spanish phrases in the test set |
| Two-way sync loops (REOS writes, Google echoes back) | Each write records the remote version and etag; echoes are ignored |

## 6. Decisions (21 November 2026)

1. **Calendars only.** Google and Microsoft connections sync calendars two-way. REOS doesn't read or send Gmail or Outlook mail, so no Google security assessment is needed.
2. **Custom domains:** Cloudflare for SaaS (automatic SSL; 100 domains free, then $0.10 each per month).
3. **Voice:** on-device speech-to-text, then Claude on Amazon Bedrock to turn it into to-dos, with a built-in date parser as backup.
4. **Association sign-in:** generic SAML 2.0 and OIDC single sign-on, set up per tenant.
5. **Isolation:** PostgreSQL row-level security on one shared database, plus Eloquent scopes as defence in depth (per the sprint plan).

## Sources

- Gmail API scope classifications: https://developers.google.com/workspace/gmail/api/auth/scopes
- Restricted scope verification: https://developers.google.com/identity/protocols/oauth2/production-readiness/restricted-scope-verification
- Cloudflare for SaaS plans: https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/plans/
- Outlook change notifications: https://learn.microsoft.com/graph/outlook-change-notifications-overview
- Todoist Ramble (TechCrunch, January 2026): https://techcrunch.com/2026/01/21/todoists-app-now-lets-you-add-tasks-to-your-to-do-list-by-speaking-to-its-ai/
- expo-speech-recognition: https://github.com/jamsch/expo-speech-recognition
- BoldTrail IDX websites: https://boldtrail.com/idx-real-estate-websites
