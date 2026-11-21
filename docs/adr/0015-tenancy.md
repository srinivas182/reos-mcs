# ADR 0015: Tenancy and isolation

- Status: Accepted
- Date: 2026-11-21 (Sprint 4, A4.1; spec in docs/sprints/S04-spec.md)

## Context

REOS is one platform for every state and every organisation (decided November 2026, replacing one deployment per customer). Associations, brokerages, schools and provider businesses are tenants. A tenant must never read another tenant's data, and the sprint plan asks for that to be proven at the database, not only in code (web journey screens 59 and 60: "row-level security per tenant").

People are not tenants. One person can hold roles in several organisations (ADR 0011), and some data is their own, whatever organisation they work in: for example, an agent's own Google Calendar connection.

## Decision

1. **Organisations are the tenants.**
   - They gain a `slug` (for `<slug>.reos.com`), `status` (active, suspended, archived), `states`, `plan`, `region` and `cell`, `limits` (seats, AI credits, rate limit, retention) and `features`.
   - The hierarchy stays `parent_id`. A parent sees roll-ups through platform services, never a child's rows.
2. **One context per request or job (`App\Tenancy\Tenancy`), in one of three states:**
   - **A tenant:** the organisation of the role in use (`ResolveTenant` middleware, run before route model binding). A4.2 adds the tenant's own domain as the first source.
   - **Platform:** REOS super admins; Artisan commands (migrations, seeders, schedulers, workers); and system endpoints authenticated by a signature or a one-time `state` (webhooks, sign-in callbacks; `tenancy.platform` middleware).
   - **None:** visitors and people without an organisation. They see no tenant rows.
3. **Enforced twice.**
   - **In PostgreSQL:** row-level security on every tenant table, with `FORCE` so the owner is bound too.
     - The policy allows a row when `current_setting('reos.scope') = 'platform'`, or when its `organisation_id` equals `current_setting('reos.tenant')`.
     - Tables with personal rows also allow `organisation_id is null`; the app decides who sees those.
     - `connections` and `oauth_authorizations` derive `organisation_id` from their owner (a generated column).
     - Settings are written per database session at the start of every request and job, and again on every new connection. A session without them sees no tenant rows, so a lost context fails closed.
   - **In Eloquent:** the `BelongsToTenant` scope adds the same filter. It also protects SQLite (local tests) and gives clear empty results instead of database errors.
4. **The app's database user is a normal user** (`reos_app`: no superuser, no BYPASSRLS), because PostgreSQL never applies row-level security to those.
   - Local Docker and CI create it.
   - Production gives the app its own user; migrations run as the owner.
5. **A guard that can't be forgotten.**
   - `php artisan reos:tenancy:check` fails if the app's database user could bypass row-level security.
   - It also fails if any table with `organisation_id` lacks the forced policy, unless it's on the reviewed exempt list with a reason: Spatie's `roles`, `model_has_roles` and `model_has_permissions`, which the role switcher reads across organisations.
   - It runs in CI after the migrations, and belongs in the deploy health check.
6. **Everything else follows the tenant.**
   - Queued jobs carry it in their payload (`reos_tenancy`) and restore it. Jobs queued before tenancy run as platform.
   - Cache keys use `Tenancy::cacheKey()`, files `Tenancy::path()` (`tenants/<id>/…`), and search indexes a tenant filter with tenant tokens when search arrives.
7. **New tenant tables:** add `organisation_id`, call `RowLevelSecurity::protect()` in the migration, and use `BelongsToTenant` on the model.

## Consequences

- Cross-tenant reads are impossible even for a query that forgets its `where`. The isolation suite (`tests/Feature/Tenancy`) proves this on PostgreSQL in CI: raw SQL, inserts, updates and deletes across tenants fail, and so does the API.
- One extra statement per request and job sets the session.
- **Settings are per session.** A transaction-pooling proxy (PgBouncer in transaction mode) would mix sessions. If one is added, switch to `SET LOCAL` inside a per-request transaction, or use RDS Proxy session pinning.
- **Personal rows are protected by the app** (policies and `Connections::canManage`), not by row-level security. Adding a `reos.user` setting is the path if that's ever needed.
- **Platform scope is powerful.** It's limited to super admins, Artisan, and endpoints with their own proof (signatures, one-time `state`).
- An older migration that created the platform organisation through the model now writes the table directly, so later model changes can't break it.

## Addendum: tenant administration (A4.1b, screens 59 and 60)

- **Features** (`TenantFeature`) have a default per tenant type, and a tenant's own choice overrides it.
  - Beta features are switched on from Features and releases (screen 70), not per tenant.
  - Each feature checks `Organisation::hasFeature()` when it ships.
- **Limits** (`TenantLimits`): seats and who pays, AI credits per seat (pooled or not), candidate pool, API rate limit, fair-queue weight, and retention (logs at least 4 years).
  - Anything not set uses the defaults for its type.
  - **The API rate limit is enforced now.** The `api` limiter reads the tenant's own value, keyed per tenant; `ResolveTenant` runs before throttling. People without a tenant get 300 requests a minute each; platform scope has no limit.
- **Status:** active, trial (with `trial_ends_at`), suspended and archived. Suspending or archiving needs a reason. Roles in a suspended or archived tenant stop counting until it is restored, so its people lose access at once.
- **Audit:** `audit_events.organisation_id` tags each event with its tenant, protected by row-level security (platform-wide events have none). Tenant changes record the fields changed, with before and after values.
- **New organisation type:** `team`, inside a brokerage, inheriting its plan.

## Addendum: support sign-in with consent (A4.1c, screen 60)

- **Ask.** REOS support asks from Tenant detail with a reason. Every holder of the tenant's admin role (broker, association admin, school admin or provider) is emailed and can answer. There is one open request per tenant at a time.
- **Answer.**
  - The owner answers on `/support-access/{id}`. That route runs in platform scope, because the owner may be working in another of their roles, so the controller checks ownership itself.
  - **Approving needs the owner's two-step; declining or ending access never does.**
  - An unanswered request ends after 24 hours, and an approval must be used within 24 hours.
- **Session.**
  - Support starts it; only the person who asked can.
  - For 60 minutes, `ActiveRole` returns the tenant's admin role in that tenant, so `ResolveTenant` enters it and row-level security applies as for the owner.
  - Personal connections stay hidden, because they belong to their owners. The back office is closed during the session.
- **Visible and audited.**
  - A banner shows on every page (`supportSession` shared prop) with the time left and "End support session".
  - Every page and change is recorded as `support.viewed` in the tenant's audit log, along with `support.requested`, `approved`, `declined`, `started` and `ended` (how: ended, expired or revoked).
  - The owner can end access at any time.
- `support_access_grants` is protected by row-level security (8 tables in `reos:tenancy:check`).

## Addendum (A4.3a): invitations, seats and several tenants

- **Invitations** (`invitations`, row-level security) belong to the tenant whose seat they take; a team's invites belong to its brokerage and name the team.
  - One person or many (pasted lines or a CSV, up to 500), by email or text, with a role, an optional team and the message language.
  - Who invites whom: brokers invite agents, mentors and brokers; team leaders invite agents into their own team only; school admins invite instructors, candidates, mentors and school admins; association admins invite co-admins; provider businesses invite providers.
  - Each invite has a 48-character link token and an 8-character code (`XXXX-XXXX`, no look-alike characters). Both are looked up by SHA-256 hash and kept encrypted only so Resend and the reminder send the same ones.
  - Invites expire after 14 days with one reminder at day 7 (`reos:invites:remind`, hourly), work once, and can be resent or withdrawn. Links are on the tenant's own address, so the page carries its brand.
- **Accepting** links an account only when it is the one invited (same email, or same phone for text invites). A person with an account signs in first; a new person creates one there, with the email or phone verified by the link. Accepting gives the role and switches the person into it; other memberships stay, so one person can hold roles in several tenants (A3.2's switcher lists them).
- **Seats:** the seat role is the one people pay for: agents in a brokerage (its teams included), instructors in a school, providers in a provider business. Admins and mentors take no seat; associations have no limit. Used seats count holders of the role plus open invites, so a batch that doesn't fit is refused whole, before anything is sent. "Ask for more seats" emails the tenant's owners, or REOS when an owner asks (once a day).
- **Ask my broker:** agents in a brokerage see tools their brokerage connects for everyone (`accounts: tenant`) with "Ask my broker". The brokerage's owners get an email a minute later unless the agent undoes it; the ask is audited in the brokerage and counts as demand.
- **Rate limits:** every route's limit has its own counter (`throttle:N,M,<route>`). Without the third parameter Laravel shares one counter per person across all routes, so five invites could block a later, unrelated action.
- Audited: `invite.sent`, `invite.resent`, `invite.revoked`, `invite.accepted`, `seats.requested`, `connection.broker_asked`, all with the tenant. The audit log screen and the check that every admin action is audited come in A4.3b.

## Addendum (A4.3b): audit everything, compliance and privacy requests

- **Every admin action is audited** with the person, the tenant, the IP address and user agent, and, for changes, before and after values (`before`/`after`, or `from`/`to` on older events; the screen and export read both). `tests/Feature/Audit/AdminActionsAuditedTest.php` lists every admin route that changes something and fails when a new one isn't listed, then calls each one and checks the row. Gaps it found are fixed: "Test sign-in" (`sso.test_started`) and "Verify DNS" before the domain is live (`domain.checked`).
- **Labels:** every action REOS records has a label in English and Spanish; a test scans the code for `record('…')` and fails on a missing one.
- **Compliance and audit (screen 71)** is for REOS super admins with two-step and a recent password confirmation (`audit.view`). It shows:
  - privacy requests with the 45-day clock;
  - the platform rules;
  - the platform audit log: filters by area, tenant, person or IP, and dates; 50 rows a page (cursor); CSV export of the filtered rows (up to 50,000, formula-safe). Exporting is itself audited (`audit.exported`).
- **Privacy requests (TDPSA):** people ask from Settings › Profile ("Your data").
  - **See my data:** a JSON export built at once from `App\Services\Privacy\DataExport`, never with secrets, sent as a signed link that works for 7 days and only for its owner. The file is deleted after that (`reos:privacy:prune`).
  - **Correct my data:** a REOS admin starts, completes or declines it (a reason is required, since the law asks to say why and how to appeal). It can be extended once by 45 days with a reason; the person is told by email at every step.
  - **Delete my account:** the existing 30-day deletion appears as "In 30-day hold", is withdrawn when the person signs in again, and becomes Done when the account is deleted. The request keeps no name, email or details after that.
  - `privacy_requests` belongs to the person, not a tenant: REOS answers it, so it has no tenant column or row-level security; only super admins and the person see it.
- **Data map:** `docs/privacy/data-map.md` lists where personal data lives and how each right reaches it; screen 71 shows when it was last reviewed (`reos.privacy.data_map_reviewed`).
