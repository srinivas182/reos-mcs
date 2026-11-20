# Sprint 2: Cloud, design system, mobile pipeline (21 December 2026 to 1 January 2027)

**Goal:** by the end of the sprint, every merge to `main` deploys to a private staging site on AWS. The web and mobile apps share one design system that matches the journey documents, is easy for older and non-technical users, and works in English and Spanish. Mobile builds reach TestFlight and Play internal testing without anyone touching a laptop. Every request and screen view is traceable.

**Status:** approved 20 November 2026; plan updated the same evening:

| Part | Status |
| --- | --- |
| 2.2a–d Design tokens, web components, Storybook, English and Spanish, first mobile components | Merged (#6–#10) |
| **2.2e Mobile kit from the mobile journeys** (added) | Done (this PR). The mobile app follows the mobile journey screens, not the web set: per-role bottom tabs, the Ask bar with voice, chips, pills, progress rings and bars, dark hero cards, option rows, chat bubbles, 54-point buttons. The web and mobile apps share only the brand (colours, type, accessibility rules). |
| 2.4 Integration Hub, then monitoring and analytics | Next (widened 20 November 2026). Every integration in the register gets a place in super-admin **Settings › Integrations**: encrypted, masked, audited keys; Test connection; each tool runs as Simulator (demo data), Sandbox, Live or Off, so missing keys never block the build. Sentry, PostHog and Grafana are the first three switched on from there, with no deploy or app release needed. |
| 2.1 Staging on AWS, 2.3 TestFlight and Play | **Moved to just before the pilot** (decision 20 November 2026). Earlier needs: the reos.com domain and a public privacy page by Sprint 4 (integration reviews); AWS sandbox access by Sprint 6; a small preview environment when Nelle's first usability check is due (about Sprint 6). |

Original approval note: Decisions: Unistyles 3 for mobile styling, Cloudflare Access for staging, Grafana Cloud for observability. Build order: 2.2 and 2.4 now; 2.1 and 2.3 when the AWS, domain, Apple and Google accounts exist.

## 1. Dependencies and capacity

| Needs | For | Owner | Apply by (register) | If late |
| --- | --- | --- | --- | --- |
| AWS organisation (client's entity), Mayura admin through IAM Identity Center | 2.1 | Client | 14 Dec 2026 | 2.1 is built and tested against a Mayura sandbox account, then applied to the client's account in one step |
| Product domain decided and on Cloudflare | 2.1, 2.3 | Client | 14 Dec 2026 | Staging runs on a temporary sub-domain we own |
| Apple Developer organisation (needs D-U-N-S) | 2.3 iOS | Client | **23 Nov 2026** | Android ships to testers first; iOS follows the day Apple approves |
| Google Play Console organisation | 2.3 Android | Client | 7 Dec 2026 | Android preview builds go to testers as an installable file through EAS |
| Expo organisation, Sentry, PostHog, Grafana Cloud | 2.3, 2.4 | Mayura | 14 Dec 2026 | — (self-serve, minutes) |

**Holiday capacity:** the sprint covers 25 December and 1 January. Plan at about 70%. If capacity is short, the mobile component set in 2.2 moves to the first days of Sprint 3; everything else stays.

## 2. Market leaders: what they do, where they fall short, what we do

Benchmarks from the sprint plan: Follow Up Boss, Lofty and AceableAgent for the design system; Laravel Cloud and Vapor, and Expo's own guidance, for deployment and mobile release.

| Leader | What they do well (we match it) | Where they fall short (reviews) | REOS answer in this sprint |
| --- | --- | --- | --- |
| **Follow Up Boss** | Clean, fast lists; one-tap call, text, email; strong desktop | The mobile app is a lighter version ("not that useful when out and about"); many features but "I wish there was an onboarding process"; parts feel "dated" | The same component set on web and mobile, so mobile isn't a lesser app; **guided first-use tour** and **empty states that teach** are design-system components, not afterthoughts |
| **Lofty** | Everything in one platform; rich dashboards | "Very complicated", "more technology than I am used to"; steep learning curve; iPad layout not optimised | **One main action per screen** rule; progressive disclosure (advanced options folded away); tablet layouts in the component set; plain-language copy rules in the design system |
| **AceableAgent** | Mobile-first lessons; progress synced across devices; modern, friendly look | Text-heavy content; occasional navigation problems; no published accessibility features | **Accessibility built into every component** (WCAG 2.2 AA, screen readers, larger text, reduced motion), tested automatically; consistent navigation shell on every role |
| **Laravel Cloud / Vapor** | Push to deploy, zero-downtime releases | Less control of networking, data residency and cost at our scale; Vapor's Lambda model doesn't suit Octane's long-lived workers | Our own Terraform on ECS Fargate (per the PRD) with **push-to-deploy, automatic rollback and preview of every migration**, so we get the same convenience without the limits |
| **Typical Expo apps** | EAS Build and Submit | Teams rebuild native binaries for every change (slow, costly) and ship without crash tracking | **Fingerprint-based pipeline**: rebuild native only when native code changes; everything else ships as an over-the-air update in minutes, with staged rollout and one-click rollback |

**Our own additions** (beyond the leaders):

1. **Text size control in the app and on the web** (Standard, Large, Largest), remembered per user and also following the phone's own text setting. Older users never have to pinch-zoom.
2. **Calm, high-contrast theme** next to light and dark, for low vision and bright sunlight (agents at showings).
3. **Undo instead of "Are you sure?"** for everything that can be undone, through one toast component.
4. **Spanish everywhere from day one**, with the user's language remembered, and Spanish strings checked in CI so no screen ships half-translated.
5. **Staging that always looks like the journeys**: seeded with the journey personas, so Nelle and pilot users can try every role.

## 3. Sub-sprints

### 2.1 Staging cell on AWS (DevOps)

**Scope**

- Terraform in `infra/terraform`: reusable modules (`network`, `database`, `cache`, `storage`, `app`, `edge`) and an `envs/staging` root. State in S3 with native locking; no long-lived AWS keys anywhere.
- **Network:** VPC across 3 availability zones in us-east-2; private subnets for app, database and cache. One NAT gateway in staging (three in production).
- **App:** ECS Fargate services built from one image (PHP 8.5, FrankenPHP, Octane). Images live in ECR, with scanning on push.
  - `web`: Octane, 2 tasks, autoscaling on CPU and request count
  - `worker`: Horizon queue workers
  - `scheduler`: one task
  - `migrate`: a one-off task run before each deploy
- **Data:**
  - Aurora PostgreSQL 17 Serverless v2, with pgvector, citext and pg_trgm. It pauses when idle in staging.
  - ElastiCache for Redis 7.
  - S3 buckets for uploads (private) and assets, served through CloudFront with signed URLs.
  - Secrets Manager for every secret, following the credentials policy.
- **Edge:**
  - Cloudflare DNS and WAF in front of an Application Load Balancer that accepts traffic only from Cloudflare (authenticated origin pulls).
  - **Staging is private behind Cloudflare Access.** Mayura, the client and Nelle sign in with email one-time codes.
- **Deploy on merge** (`.github/workflows/deploy-staging.yml`):
  - Authentication uses GitHub OIDC with an assumed role.
  - Steps: build the image once and push it; run migrations as a one-off task (the deploy stops if they fail); roll out with the ECS deployment circuit breaker (automatic rollback); check `/up` and `/api/v1/health`; post the result to the PR.
  - Tests don't re-run, in line with the CI minutes rules.
- **Staging data:** migrations plus the journey personas. Nightly reset to known demo data is optional and off by default.
- **Cost guard:**
  - AWS Budgets alarm at a monthly limit agreed with the client.
  - Everything tagged by environment and module.
  - Staging scales to minimum outside India and Texas working hours.

**Done when:** a merge to `main` is live on `https://staging.<domain>` within about 10 minutes, behind Cloudflare Access. Sofia Reyes can sign in. A broken migration stops the deploy and leaves the previous version running.

**Tests and checks:** `terraform fmt`, `validate` and `tflint` in CI on infra changes; `checkov` security scan (no public buckets, encryption on, no open security groups); the deploy smoke test; a rollback drill (deploy a deliberately failing image and confirm the automatic rollback).

### 2.2 Design system (Design + Team A)

**Tokens (`packages/tokens`, v1).** Extracted from the journey documents, so the product looks like what Nelle approved.

| Group | Values |
| --- | --- |
| Brand and action | Navy `#0F172A`, primary blue `#2952CC`, pressed `#1E3FA3`, tint `#E8EEFC`, sky `#93C5FD` |
| Text | Ink `#0F172A`, body `#334155`, muted `#475569`, subtle `#64748B` (used only where contrast passes) |
| Surfaces and lines | Page `#F6F7F9`, surface `#FFFFFF`, soft `#EEF1F5`, border `#E3E7EE` / `#CBD5E1` |
| Status | Success `#15803D` on `#F0FDF4`, warning `#B45309` on `#FEF3C7`, danger `#B42318`, each with a dark-theme pair |
| Type | Inter Variable (bundled, no font CDN); Inter Display for headings. Scale: 14 (secondary, the minimum anywhere), **16 body**, 18, 20, 24, 32, 40 |
| Shape and space | 4-point spacing; radius 8 / 12 / 16 / pill; three elevation levels; focus ring 3 px blue with offset |
| Motion | 150 / 250 ms ease-out; switched off when the user asks for reduced motion |

- **Semantic tokens** (for example `surface`, `text`, `action`, `success`) for light, dark and calm high-contrast themes. Components use only these, never raw colours.
- **Generated outputs:** TypeScript (mobile), CSS variables plus Tailwind 4 `@theme` (web), and a JSON swatch page for designers. Built from one source in `packages/tokens`.
- **Contrast is a test:** every text and background pair a theme uses must pass WCAG AA (4.5:1 body, 3:1 large text and icons), or CI fails.
- **Note on the journey PDFs:** they use 11–13 px text so whole screens fit on a page. The product uses the 16-point base above, as required by our design standard.

**Web components** (`apps/platform`, shadcn/ui on Tailwind 4, restyled with our tokens):

- **Actions and forms:** button and icon button (sizes default 48 px, compact 40 px only in dense admin tables), input, textarea, select and combobox, checkbox, radio, switch, date and time picker, OTP input, file drop.
- **Display:** card, stat tile, badge and status pill, avatar, table (TanStack Table: sort, filter, density toggle, sticky header, mobile card view), tabs, accordion, progress, stepper, timeline, empty state, skeleton.
- **Feedback:** toast with **undo**, inline alert and banner, dialog, sheet, tooltip, guided-tour step.
- **Shell:** app shell (sidebar, top bar, role switcher, tenant brand slot, text-size control), page header, breadcrumbs, command palette (Ctrl/⌘ K).

**Mobile components** (`packages/ui-native`, used by `apps/mobile`). The same names and props as the web set wherever possible, plus:

- bottom tabs, large-title header, bottom sheet and list rows
- swipe actions with visible alternatives (swiping is never the only way)
- pull to refresh, offline banner and haptics

**Mobile styling (decided: Unistyles 3):** **Unistyles 3** (stable; supports React Native 0.85+; themes, breakpoints and text scaling built in; no runtime cost). NativeWind 5 would let us share Tailwind classes with the web, but it is still a release candidate tested on Expo SDK 57, not 58. Plain StyleSheet is the fallback.

**Storybook:**

- **Web:** Storybook 10 for the web components, with the accessibility add-on.
- **Mobile:** React Native Storybook 10, on-device in development builds.
- **Viewer:** a published static Storybook on staging is the single place designers, developers and Nelle review components.

**Accessibility rules, written down and tested:**

- WCAG 2.2 AA throughout.
- Every interactive element has a label, a visible focus state and a 48 × 48 target.
- Screens work at 200% text and with VoiceOver, TalkBack and the keyboard.
- Colour is never the only signal.
- Error messages say how to fix the problem.
- Automated checks: axe on every Storybook story in CI (a story with violations fails the run), and React Native Testing Library checks for accessibility roles and labels.

**Language:**

- `packages/i18n` holds the shared English and Spanish strings for web and mobile (i18next), and Laravel uses the same files for emails.
- A CI check fails when a key exists in English but not in Spanish.
- Spanish starts as a reviewed machine draft, marked for professional translation before the pilot.

**Copy rules:** plain words (grade 6–8 reading level), one main action per screen, verbs on buttons ("Book session", not "Submit"), no jargon without an explanation tap.

**Done when:** the core components match the journey screens side by side (design review signed off), Storybook is published on staging, and all accessibility, contrast and translation checks run in CI.

### 2.3 Mobile release pipeline (Team A + DevOps)

**Scope**

- **`eas.json` profiles:**

  | Profile | Build | API | Distribution |
  | --- | --- | --- | --- |
  | `development` | Dev client | Local | Engineers |
  | `preview` | Release | Staging | Internal testers, TestFlight internal and Play internal |
  | `production` | Release | Production | App Store and Play |

  Each profile has its own app id (`com.reos.app.dev`, `.preview` and `com.reos.app`), so all three install side by side.
- **Runtime version from the native fingerprint.** EAS Workflows, triggered from GitHub:
  - On a PR: compare fingerprints and comment whether the change needs a new native build.
  - On merge: if the fingerprint changed, build `preview` and submit it to TestFlight and Play internal. If not, publish an **EAS Update** to the `preview` channel (minutes, no store review).
  - On a `mobile-v*` tag: build `production`, submit, and roll out updates in stages (10% → 50% → 100%) with one-click rollback.
- **Credentials** are managed by EAS: an App Store Connect API key and a Play service account. Nothing lives on laptops.
- **Sentry** for React Native: crash and error reports with source maps uploaded on every build and update, release health, and personal data scrubbed.
- **App store groundwork:**
  - privacy nutrition labels and the Play data-safety form drafted from the register's `personal_data` flags
  - one store listing; tenant branding shown after sign-in
  - no in-app purchases, per the PRD

**Done when:** an empty REOS app (the home screen from Sprint 1) is on TestFlight and Play internal testing from a merge, and a JavaScript-only change reaches testers' phones as an update with no new build.

### 2.4 Observability and analytics (DevOps + Team B lead)

**Scope**

- **Tracing:** OpenTelemetry in Laravel (requests, queries, queues, outbound HTTP) and in the mobile app, sent over OTLP to **Grafana Cloud**.
  - Trace ids flow from mobile through the API to jobs, so one user action can be followed end to end.
  - Dashboards: latency, errors and saturation per module and per tenant.
- **Errors:** Sentry for the web app, API and mobile, with releases tied to git SHAs and deploys, and alert rules routed to the team.
- **Logs:** structured JSON with trace id and tenant id; no personal data in logs (a test checks the log formatter).
- **Product analytics: PostHog**, behind a cookie consent banner, in line with the Texas Data Privacy and Security Act (TDPSA).
  - A typed event catalogue in `packages/analytics` (event names and properties shared by web and mobile, so dashboards don't break).
  - The first funnels: sign-up and first lesson.
- **Feature flags:** Laravel Pennant is the single source of truth. Flags reach the web through Inertia shared props and the mobile app through `GET /api/v1/features`.
  - Flags are scoped by environment now, and by tenant, role and plan once Sprint 3 adds identity and tenancy.
  - PostHog is used for analytics and later experiments, not as a second flag system.
- **Uptime:** external checks on staging `/up` and `/api/v1/health`, with alerts to the team.

**Done when:** a request made in the mobile app on staging can be followed in Grafana from the tap to the database query, its errors appear in Sentry with the right release, its events appear in PostHog, and a flag can be switched for staging without a deploy.

### Housekeeping

- Raise `apps/platform/composer.json` to `"php": "^8.5"`, as recorded in ADR 0002. CI and staging already run 8.5.
- **PHP 8.6** came out on 19 November 2026. We move when FrankenPHP and our packages support it, and not before 8.6.1, as the PRD says. An ADR records the check.

## 4. Definition of done (whole sprint)

- Every sub-sprint has its own PR with green CI, merged before the next starts.
- Every PRD and sprint-plan item above is checked against code and tests, and its status is reported.
- The register is updated for every account opened (`status`, dates, references).
- ADRs written for: the Terraform layout and staging access, mobile styling, the design-token pipeline, and observability.
- The usability check prep: the 40 pilot users Nelle recruits by Sprint 6 can open staging.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Accounts not ready (AWS, Apple, Google) | Each sub-sprint has a fallback path (see section 1); the register's action list is shared with the client now |
| Holiday capacity | 70% plan; mobile components can move to early Sprint 3 |
| Design system grows into a time sink | The fixed component list above; anything else is built when a feature needs it |
| Staging cost creep | Budget alarm, scale-to-minimum schedule, Aurora auto-pause |
| NativeWind/Unistyles churn | ADR with the chosen library; components wrap it, so a later switch touches `packages/ui-native` only |

## 6. Decisions (approved 20 November 2026)

1. **Mobile styling:** Unistyles 3.
2. **Staging access:** Cloudflare Access with email codes.
3. **Observability:** Grafana Cloud (free tier to start).
4. **Account timing:** still open. The expected dates for AWS, the domain, Apple and Google decide whether the fallback paths in section 1 are needed.

## Sources

- Follow Up Boss reviews: https://www.capterra.com/p/130020/Follow-Up-Boss/reviews/
- Lofty reviews: https://www.capterra.com/p/157457/Lofty/reviews/
- AceableAgent review: https://theclose.com/aceable-agent-review/
- NativeWind 5 release plan and status: https://github.com/nativewind/nativewind/discussions/1818
- Storybook 10 for React Native: https://storybookjs.github.io/react-native/blog/storybook-v10
