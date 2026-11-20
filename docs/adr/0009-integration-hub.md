# ADR 0009: Integration Hub

- Status: Accepted
- Date: 2026-11-20

## Context

REOS needs about 40 outside services (calendar and email, payments, CRMs, transaction tools, e-signature, MLS feeds, monitoring and more). Most need a vendor approval with a lead time of weeks or months. The build must not wait for those approvals, and keys must be added and changed without a code change or deploy (decision 20 November 2026).

## Decision

1. **The register drives the product.** Each connectable entry in `docs/integrations/register.yaml` has a `connection` block: how REOS signs in (`api_key`, `oauth2`, `aws` or `none`), whose account the data comes from (`platform`, `user` or `tenant`), and the fields the super admin enters. `pnpm integrations:render` writes `apps/platform/resources/integrations/catalogue.json`, which the platform reads. CI fails if the file is stale or a field has no English label.
2. **Settings › Integrations** (super admin only, password re-confirmed) lists every service and opens a page for each one, showing:
   - how it runs;
   - test and live keys;
   - the OAuth redirect address to register with the vendor;
   - the vendor's test-access notes;
   - recent changes.
3. **Four modes per service:**
   - **Off.**
   - **Demo data (Simulator):** the default.
   - **Test account (Sandbox):** needs complete test keys.
   - **Live:** needs complete live keys.

   Removing the keys a service runs on puts it back on demo data. Public-data services (`auth: none`) have no test system and can go live without keys.
4. **Keys are encrypted with their own key** (`REOS_CREDENTIALS_KEY`, AES-256-GCM), not `APP_KEY`.
   - Secrets are never sent back to the browser; only their last four characters are shown, and only when the value has at least 12 characters.
   - Rotation: `reos:credentials:key --rotate`, then `reos:credentials:rekey`.
5. **Storage sits behind `CredentialVault`.** Today it is `DatabaseCredentialVault`. When the AWS account exists, a Secrets Manager vault replaces it without changing any screen.
6. **Every change is audited** in `audit_events`: who, what, when, IP address and browser. Only field names are recorded, never values. This table is the start of the platform audit log.
7. **Two kinds of connection:**
   - **Platform keys:** REOS's partner-app keys, entered here.
   - **Personal or tenant connections:** where `accounts` is `user` or `tenant`, each person or brokerage then connects their own account (for example "Connect Google Calendar") in their own settings. That is built in each integration's own sprint, on top of these keys.

## Plan

- **2.4a (this change):** catalogue, keys, modes, audit log, super-admin access.
- **2.4b (done):** checks.
  - **Test connection:** each vendor's tester makes the cheapest harmless request it offers. That means reading the account (Stripe, Twilio), swapping a made-up OAuth code (Google, Microsoft: `invalid_grant` means the client keys are good), asking for flags (PostHog) or sending an empty batch (Grafana). Sentry gets a format check only, because a test event would create a real issue. Every other vendor gets "keys complete" until its own sprint adds a tester to `TesterRegistry`.
  - **Results** are stored in `integration_checks` for 30 days. They hold only a translation key and parameters, never response bodies.
  - **Live** needs a check of the current live keys that didn't fail. Services with no keys need no check.
  - **Health checks** (`reos:integrations:health`, every 15 minutes) check services running on vendor systems whose tester contacts the vendor.
  - **Pausing:** after 3 failures in a row the service is paused and the super admins get an email. A paused service acts as **Off**, never as demo data, so nothing pretends to work. A passing Test connection, or choosing a mode, switches it back on.
- **2.4c (done):** monitoring, switched on from here.
  - **When it sends:** a service sends data only when it runs on test or live keys and isn't paused. Demo data and Off send nothing.
  - **Where settings are held:** `MonitoringRuntime` works them out and caches them until an `IntegrationSettingsChanged` event.
  - **Picking up changes without a restart:** each request (the `TraceRequests` middleware) and each queued job re-applies the settings when they changed. It rebinds the Sentry client and rebuilds the tracer, so Octane and queue workers follow a change without a restart.
  - **Server:**
    - `sentry/sentry-laravel` reports unhandled exceptions with no personal data.
    - OpenTelemetry sends one span per request to Grafana over OTLP/HTTP (JSON): method, route pattern, status and duration. No query strings or user data.
    - If the settings can't be read (database down), monitoring stays off and the request carries on; `/api/v1/health` keeps working.
  - **Web:** gets public values only (DSNs, the PostHog project key) as the `monitoring` page prop. `@sentry/react` and `posthog-js` are loaded only when switched on.
  - **Phone:** reads `GET /api/v1/app-config` at launch and starts `@sentry/react-native` or `posthog-react-native`, so no app release is needed.
  - **Privacy defaults everywhere:**
    - Sentry collects no user info, cookies, headers, bodies or query strings.
    - PostHog masks text and attributes, has no session recording, respects Do Not Track, and identifies people by REOS id only.
  - **Phone source maps:** the Sentry Expo plugin uploads source maps once 2.3 (EAS builds) sets `SENTRY_ORG`, `SENTRY_PROJECT` and `SENTRY_AUTH_TOKEN`.

## Alternatives considered

- **An integration platform (Nango, Merge, Paragon) as the core.** Rejected as the foundation, because it would put a third party between each customer's isolated deployment and its data. A driver can still wrap one of these for particular CRMs later.
- **Keys in `.env` until AWS exists.** Rejected, because every key change would need a deploy.
- **A screen per vendor.** Rejected: about 40 screens to maintain. The fields come from the register instead.

## Consequences

- Super admin is a user flag (`users.is_super_admin`; the demo `ops` persona) until Sprint 3 brings roles and permissions.
- Field names in the register are a first pass from vendor documentation. Correct them in the register when partner access arrives; the screens follow automatically.
- The credentials policy is amended: until AWS Secrets Manager is available, staging and production keys live in this encrypted store, entered by the super admin.
