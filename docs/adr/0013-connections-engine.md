# ADR 0013: Connections engine

- Status: Accepted
- Date: 2026-11-21 (Sprint 3, B3.1; spec in docs/sprints/S03-spec.md)

## Context

The Integration Hub (ADR 0009) holds REOS's own keys for each outside tool. Most tools also need each person's or brokerage's **own** account: Maria's Google Calendar, a brokerage's transaction system. Those accounts must be connected by the person, kept in sync, and survive tokens that expire or get revoked, all without waiting for vendor approvals.

## Decision

1. **A connection** (`connections`) belongs to a person or an organisation (`owner_type` + `owner_id`; personal tools belong to the person, organisation tools to their active organisation when their role manages it). It holds the account at the tool, granted scopes, status, cursors, and the run mode it was made in.
   - Tokens are encrypted with the hub's key (`REOS_CREDENTIALS_KEY`, `HubEncrypted` cast), never `APP_KEY`, and `reos:credentials:rekey` re-encrypts them after rotation. They are never returned by the API.
   - No Eloquent morph map: adding one would rename the user type in existing role rows.
2. **Vendor sign-in is OAuth 2 authorisation code with PKCE** (`ConnectionAuthorizer`).
   - `start` saves a single-use authorisation (PKCE verifier encrypted, owner, history choice, where to return) valid for ten minutes. The `state` is its id plus an HMAC, so forged states are refused without a lookup and a used or expired one is refused after it.
   - The callback is the address Settings › Integrations already shows: `/integrations/{tool}/callback`. The `state` says who it is for, so it needs no session: the phone opens sign-in in a secure browser sheet and comes back to `reos://connections?outcome=…`.
   - Reconnecting the same account updates the connection rather than adding another.
3. **One driver contract** (`ConnectorDriver`): sign-in address, code exchange, refresh, revoke, account, streams, one page of a stream, the tool's rate allowance, webhook verification. Optional `SubscribesToChanges` for push.
   - Each tool has a **real driver** (sandbox and live keys) and a **Simulator** (demo data) behind the same contract; `ConnectorRegistry` picks one from the hub's run mode. Off, or paused by health checks, means no driver, so nothing pretends to work.
   - The Simulator runs the same path as the real tool: a REOS-labelled demo sign-in page (never a copy of the vendor's), a PKCE-bound signed code, expiring tokens, paged sync and signed webhooks. `reos:connections:simulate {id} --revoke` makes it refuse, for demos.
   - **Contract tests** run each real driver against recorded responses (`tests/Fixtures/<tool>/`), so tests never call vendors. Until the sandbox keys exist the fixtures follow the vendor's documented formats; they are re-recorded from the sandbox when the keys arrive.
4. **The sync engine** (`SyncEngine`, job `SyncConnection` on the `connections` queue).
   - The first sync starts at the chosen history (3 or 12 months, or everything); later ones carry on from the cursor, saved after every page. Upserts are keyed by the tool's own id, so repeats change nothing.
   - A job does at most five pages, then re-queues itself, so one big account can't starve others. Per-owner and per-tool per-minute allowances (`connection-owner`, `connection-vendor`) keep it fair; a tool's "slow down" is honoured.
   - Retries with backoff (30 s, 2 min, 10 min, 30 min). A refused access token gets one refresh; refused again, the connection needs reconnecting. An expired cursor (Google's 410) restarts the stream.
   - `reos:connections:sync` (every 15 minutes) queues connections not synced in that time.
5. **Inbound webhooks**: `POST /webhooks/{tool}`, without session or CSRF. The driver checks the signature (Google: the channel token REOS signed when it subscribed); `webhook_receipts` refuses repeats; syncs are queued, so the tool gets its answer at once. Tools are asked to push only when REOS has a public HTTPS address; otherwise the schedule catches up.
6. **Health per connection** (as 2.4b does per tool): three failed runs in a row mark it `failing` (still retried); a refused token, or the super admin moving the tool to another run mode, marks it `reconnect_needed`. The person is emailed once, and the change is audited (`connection.connected`, `reconnected`, `disconnected`, `reconnect_needed`).
7. **Disconnect** asks the tool to revoke REOS's access, deletes everything synced from that account, then the connection.
8. **API**: `GET /api/v1/connections`, `POST /api/v1/connections` (returns the sign-in address), `POST /api/v1/connections/{id}/sync`, `DELETE /api/v1/connections/{id}`. Website: `/connections/{tool}/connect`.

## The sample connector

Google Calendar, read-only at first (`calendar.readonly`). It synced the primary calendar's events into `calendar_events`, subscribed to push changes, and runs on demo data until the sandbox keys exist. B4.1 made it two-way, with calendars to choose and push channels renewed (ADR 0017).

## Plan

- **B3.1a (this change):** the engine, API, Google Calendar sample connector, contract tests.
- **B3.1b (done):** connecting and reconnecting from the phone and the website.
  - **Phone:** `connectTool()` opens the tool's sign-in in a secure browser sheet (`expo-web-browser`) and reads the outcome from `reos://connections`. A red card on Today (Connections and Sync journey, screen 26) opens **Reconnect {tool}** (screen 27): why it stopped, what happens next, one sign-in button. "Chat with support" and "we tried N times" are left out: there is no support chat yet, and REOS stops retrying a refused sign-in rather than counting attempts.
  - **Website:** the same alert under the top bar on every page (`connectionAlerts` shared prop), and a toast after returning from the tool.
  - Signing in again clears a revocation simulated on demo data. Demo data: Linda Park's calendar syncs; James Miller's stopped two days ago (journey screen 26).
- **B3.2:** catalogue statuses and the Connections screens.

## Alternatives considered

- **A unified-API vendor (Nango, Merge, Paragon) as the engine.** Rejected for the core, as in ADR 0009: a third party would sit between each customer's isolated deployment and its data. A driver can still wrap one later.
- **Laravel Socialite for vendor sign-in.** Socialite is built for signing people in to REOS, not for storing long-lived tool access with PKCE, refresh and revocation; the shared `OAuth2Client` is small and keeps the flow under test.
