# Sprint 3: Identity, roles and connections (4 to 15 January 2027)

**Goal:** by the end of the sprint, anyone can try REOS (free quiz, no account), sign up in one tap on the phone or the web, hold several roles and switch between them, and connect their own tools. The "Connections" screen is driven by the integrations register, so a tool's status changes without an app release.

**Status:** decisions taken 20 November 2026 (section 6). Source: REOS Sprint Plan v1.0 (Sprint 3: A3.1–A3.3, B3.1–B3.2), REOS Project Requirements Document v1.0 (identity and security), and the candidate, agent and shared journey screens.

## 0. What Sprint 2 already delivered (scope adjusted)

| Planned for Sprint 3 | Already in place | Sprint 3 now does |
| --- | --- | --- |
| Email and password, email verification, passkeys, two-step (A3.1, A3.2) | Fortify from the starter kit: sign-in, sign-up, email verification, password reset, passkeys, authenticator-app two-step | Rebuild the pages to the journey design, translate them, add Google, Apple and phone sign-in, mobile tokens, devices |
| Encrypted credentials vault, health and auto-pause (B3.1) | Integration Hub, 2.4a–b: encrypted vault, Test connection, 15-minute health checks, auto-pause, audit | Per-person and per-organisation **connections** (OAuth sign-in to the vendor), the sync engine, inbound webhooks |
| Integration catalogue (B3.2) | Generated catalogue for super admins, with modes | Catalogue statuses, availability rules and the **Connections** screens people use |
| Audit log (A4.3) | `audit_events` table (2.4a) | Every sign-in, role change and connection is audited |

Team B has spare capacity from this. It goes to the register gap (section 3, B3.2) and to a real sample connector.

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Google Cloud OAuth client for **sign-in** (openid, email and profile only; no Google verification needed for these scopes) | A3.1 | Client with Mayura | Built and tested against mocked ID tokens. Switched on in Settings › Integrations when keys arrive |
| Apple Developer account (Sign in with Apple needs it) | A3.1 | Client | Built and tested against mocked ID tokens. App Store guideline 4.8 asks apps that offer Google sign-in to also offer a privacy-friendly login: one that limits data to name and email, lets people hide their email, and doesn't track for ads. Sign in with Apple meets that, and the journeys include it. So a late account can hold up the **iOS release**, not the build |
| SMS for phone codes (Twilio Verify, added to the register) | A3.1 | Client | Runs on the Simulator: codes appear in Mailpit and the log on development and staging |
| Google Calendar keys (sample connector) | B3.1 | Client with Mayura | The sample connector runs on demo data; the sandbox check runs once keys exist |

## 2. Market leaders: what they do, where they fall short, what we do

Benchmarks from the sprint plan: AceableAgent, The CE Shop and Follow Up Boss for onboarding and accounts; Lofty and Zapier for integration marketplaces.

| Leader | What they do well (we match it) | Where they fall short | REOS answer in this sprint |
| --- | --- | --- | --- |
| **AceableAgent** | One account across phone, tablet and computer; pick your state and product in the app; progress saves automatically | Value shows up only after you buy; the app flow is install → sign in → choose state → buy | **Free 10-question quiz before any account**. The answers carry over on sign-up and the score becomes the reason to sign up |
| **The CE Shop** | Multi-state catalogue; one account for pre-licence and CE | Web-first; state and licence details live in separate checkouts | **Several roles and several state licences on one account**, with a role switcher that remembers where you were |
| **Follow Up Boss** | Long list of integrations, two-way sync with major CRMs | Many connections mean copying API keys from help-centre articles | **One-tap vendor sign-in (OAuth)** wherever the vendor allows it. API-key tools get a guided paste screen with a live check |
| **Lofty** | Integration centre by category (lead sources, transactions, communication, marketing, back office); open API and Zapier for the rest | Doesn't show which tools are coming, which plan includes them, or whether a connection is healthy | **Statuses on every tool** (on, beta, coming soon with "Notify me", paused), **health per connection** with a plain "Reconnect" prompt, and availability by state, role and plan |
| **Zapier** | Thousands of apps; clear account-connection screens that say what an app can do | Generic; the agent builds and maintains the workflows | **Smart defaults**: connecting turns on the right sync with no setup. A consent screen says what REOS **can** and **never** does |

**Our own additions:**

1. **Passwordless on the phone.** Apple, Google or a phone code; Face ID to reopen. On the web, passkeys come first and passwords stay as a fallback.
2. **Device list with sign-out and a new-device email**, from day one. Members of the public use the app on shared family phones.
3. **The role switcher remembers the last role and screen per device.** A broker who is also an instructor never lands in the wrong place.
4. **Two-step is required before any admin role works.** Admins are walked through setting it up, never left locked out.
5. **The "Broker" tag in the library.** Tools a brokerage connects once for everyone explain that, with an **Ask my broker** button.
6. **"My tool isn't listed"** records the request and adds a vote, so the super admin sees demand per tool.

## 3. Sub-sprints

### A3.1 Accounts (Team A): web and phone

**Phone:**

- **Screens:** "Sign in" (Apple, Google, phone); phone code with auto-fill (6 digits, valid 10 minutes, 5 wrong tries locks 15 minutes); "Use Face ID"; "Signed in on a new phone" with the device list.
- **Verified sign-in:** native Apple and Google sign-in (`expo-apple-authentication`, `@react-native-google-signin/google-signin`). The app sends the identity token to `POST /api/v1/auth/{apple|google}`, and the server verifies it against the provider's published keys.
- **Phone codes:** `POST /api/v1/auth/phone/start` and `/verify`, sent through the SMS driver (Simulator until Twilio keys exist).
- **Tokens:** Sanctum, one token per device, kept in `expo-secure-store`. Tokens expire after 30 days unused and can be revoked from the device list.
- **Face ID or fingerprint:** reopens the app (`expo-local-authentication`). Nothing biometric leaves the phone.
- **Delete account:** required by both app stores. A two-step check, then a 30-day grace period, keeping only records the law requires.

**Web:**

- **Sign-in page (`w002`):** a brand panel (REOS now, the organisation's brand in A4.2), email and password, Google, Apple and passkey.
- **Sign-up, email verification and password reset:** rebuilt to the design system and translated into Spanish, including Laravel's validation messages.
- **Rate limits:** after repeated failures, sign-in locks with a plain message saying when to try again.

**Both:**

- **One person, one account:** the same verified email from Google, Apple or a password sign-up joins the same account. An unverified email never joins an account automatically.
- **New devices:** an email for every new-device sign-in. Every sign-in, sign-out and failure is audited.

**API:** `/api/v1/auth/*` and `/api/v1/me`, documented, with the api-client regenerated.

**Done when:** a person signs up and signs in on the web and on the phone with every method, which the end-to-end tests prove on demo keys.

### A3.2 Roles and permissions (Team A)

- **Roles from the journeys:**
  - Candidate, agent, broker, mentor, instructor, school admin, service provider and association admin.
  - Plus the platform's own super admin.
  - A person can hold several.
- **Organisations, pulled forward from A4.1:** a minimal table (type, name, parent) so a role can belong to *this* brokerage or school from the start. A4.1 adds row-level security, the full hierarchy, branding and domains.
- **Permissions:**
  - Named permissions grouped by area, granted to roles.
  - Policies on every model and screen.
  - The web app and the phone app read "what can I do" from `/api/v1/me`.
- **Role switcher:**
  - Web: in the header. Phone: in Me.
  - The last role used is remembered per device.
  - Signing in returns to the last role.
- **Two-step for admin roles:** broker, school admin, association admin and super admin can't use those roles until two-step is set up. They're guided to an authenticator app or a passkey, with recovery codes.
- **People screen (super admin):** find a person, see their roles and organisations, grant or remove a role. Every change is audited.
- **Replaces the Sprint 2 `is_super_admin` flag:** a migration moves the demo ops persona to the super-admin role, and the Integration Hub gate checks the role.
- **Demo personas:** each persona gets its role and organisation from `Personas.php`.

**Done when:** an admin without two-step is blocked from admin screens, and the policy tests cover every role.

### A3.3 Onboarding (Team A): phone first, then the web

- **Path chooser (`00a`):** become an agent, licensed agent, broker or team, instructor or mentor, service provider. The choice is stored on the account so the chooser shows only once. Broker and association set-up points to reos.com.
- **State choice:** Texas live. Other states show "Coming soon" with a waitlist and a waiting count for the super admin. This is a minimal states table; A5.1 adds the full state switcher and licences.
- **Welcome and free quiz (`01`–`03`):**
  - 10 questions with no account, one per screen, with an explanation and source after each answer.
  - Answers are kept on the device and attached to the account on sign-up.
  - Questions come from a seeded sample set marked "sample". The real Texas question bank arrives with content in A5.3.
  - Then the score, the strong and weak topics, and one-tap sign-up.
- **60-second setup (`04`):** exam month, minutes per day, language and reminder time. Notification permission is asked only after a time is picked.
- **"Have a code?":** the entry screen is built now. Codes and seats are checked from A4.3 (invites and seats).
- **Language:** follows the phone and can be changed at any step.
- **Web:** the same path on reos.com, with the role landing pages.

**Done when:** the candidate path matches journey screens `00a`–`05` side by side, as in 2.2e, in English and Spanish.

### B3.1 Connections engine (Team B)

- **Connections** belong to a person or an organisation: tool, account label, granted scopes, status, last sync. Tokens are encrypted with the Integration Hub cipher.
- **OAuth 2 vendor sign-in:**
  - Authorisation code with PKCE and a signed `state`, through the redirect address the hub already shows.
  - Token refresh, and revocation on disconnect.
  - On the phone it opens in a secure browser sheet (`expo-web-browser`) and returns to the app.
- **Sync engine:**
  - Queued jobs per connection, fair across organisations.
  - Cursors, idempotent upserts, retries with backoff, and per-vendor rate limits.
  - First sync with a history range (3 or 12 months, or everything).
- **Inbound webhooks:** `POST /webhooks/{integration}` with signature checks, duplicate protection and queued handling.
- **Driver contract:**
  - Every tool implements one interface. The Simulator returns realistic demo data for the journey personas.
  - Contract tests run each driver against recorded sandbox responses, so tests never call vendors.
- **Health per connection:**
  - Reuses 2.4b, per connection.
  - "Reconnect needed" (`12_reconnect`) after the vendor refuses the token, and an alert on Today.
- **Sample connector:** Google Calendar, read-only (two-way comes in B4.1).

**Done when:** the sample connector syncs on demo data and on a sandbox, and pauses with a "Reconnect" prompt on errors.

### B3.2 Catalogues and the Connections screen (Team B)

- **Catalogue statuses:**
  - On, beta, coming soon, paused and retired.
  - The register's `launch` field is the default; the super admin can override it (audited).
  - The same model covers payment gateways.
- **Availability:**
  - By state, role and plan.
  - Plans arrive with B5.1, so the rule is stored now and enforced then.
  - An organisation can hide a tool from its people (A4 adds the organisation admin screen).
- **Connections screens, web and phone:**
  - Hub (`01_hub`, `12_hub_multi`).
  - Library with search and tabs (CRM, Deals, Calendar, Leads, More).
  - "Vendor sign-in" consent sheet: what REOS can do and never does.
  - "Connected with smart defaults", "Change sync settings" and the tool's detail screen.
  - "Notify me" on coming-soon tools, and "My tool isn't listed".
- **The app reads the catalogue from `/api/v1/catalogue`**, so a status change shows without a release.
- **Register gap:** the journey library lists tools the register doesn't have yet. They're added as `coming_soon` entries, so the docs, the hub and the library stay one list:
  - Deals: Open to Close, Paperless Pipeline, ListedKit, BoldTrail BackOffice.
  - Calendar and meetings: ShowingTime, BrokerBay, Apple Calendar, Zoom, Teams and Meet.
  - Leads: Zillow, Realtor.com and Homes.com.
  - Phone contacts.

**Done when:** switching a tool to "coming soon" in Settings › Integrations changes the phone's library without an app release.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens for each journey step ship together, both in English and Spanish, and checked against the journey PNGs.
- Every new endpoint is in `/api/v1`, documented, with the api-client regenerated and Pest feature tests.
- Security tests:
  - Sign-in lockouts, token revocation and the two-step gate for admin roles.
  - A role can't reach another organisation's data.
  - An OAuth `state` can't be replayed.
- ADRs for: identity and tokens, roles and organisations, and the connections engine.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Apple Developer account late | Apple sign-in built and tested on mocked tokens and switched on later; Android and the web go first |
| SMS cost and fraud (SMS pumping) on public phone sign-in | Rate limits per number, device and IP; country allow-list (US first); Twilio Verify's fraud guard (decision 3) |
| Roles pulled ahead of tenancy | A minimal organisations table now, built so A4.1 extends it rather than migrating data |
| Vendor OAuth approvals not ready | Every connector runs on the Simulator; the Google Calendar sandbox proves the real path |
| Two teams touching auth and connections at once | One ADR per area agreed on day 1; shared `/api/v1/me` contract frozen early |

## 6. Decisions (20 November 2026)

1. **Roles and permissions:** `spatie/laravel-permission` with "teams", where a team is an organisation.
2. **Organisations:** a minimal organisations table now (pulled from A4.1), so every role belongs to an organisation from the start.
3. **Phone codes:** Twilio Verify (code delivery, retries and SMS-pumping protection). It's added to the register and runs on the Simulator until keys exist.
4. **Phone tokens:** Sanctum per-device tokens (the default recommendation; no third-party sign-in with REOS accounts is planned).
5. **Association single sign-on:** built in A4.2 (branded sign-in) once Texas REALTORS confirms which system its members use.

## Sources

- AceableAgent app: https://www.aceableagent.com/about/aceableagent-app/
- Lofty integrations: https://lofty.com/integrations
- Follow Up Boss integrations (example article): https://help.followupboss.com/hc/en-us/articles/13149472932375-Lofty
- The CE Shop: https://www.theceshop.com/why-choose-us/
- Apple's January 2024 change to guideline 4.8 (Sign in with Apple no longer mandatory; a privacy-friendly option is): https://9to5mac.com/2024/01/27/sign-in-with-apple-rules-app-store/
