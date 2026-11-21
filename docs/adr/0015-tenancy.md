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
