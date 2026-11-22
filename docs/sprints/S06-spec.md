# Sprint 6: Back office, messaging and video (15 to 26 February 2027)

**Goal:** by the end of the sprint the REOS super admin runs the whole platform from the web: the platform overview, tenants and plans, provider categories, AI behaviour and costs, and feature flags with staged releases. People talk to each other inside REOS (chat and announcements, live on the web and in the app), hear about what matters on the channel they chose without being woken at night, and meet face to face in video rooms that end on time and never record without consent. Every person's consents are recorded.

**Status:** decisions taken 22 November 2026 (section 6). Source: REOS Sprint Plan v1.0 (Sprint 6: A6.1–A6.3, B6.1–B6.3; gate "a tenant signs up and pays on the web; usability check 1 with pilot users"), the Web App Journey (screens 23, 26, 58, 68, 69, 70 and the announcements screens), the Instructor and Mentor Journey (screens 14–16, session room and notes) and the phone journeys (Today, Me, sheets).

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 6 | Already in place | Sprint 6 now does |
| --- | --- | --- |
| A6.1 Super admin v1: tenants, states, plans, catalogues | Tenants (59) and Tenant detail (60) with features, limits, codes and pools; States (61) and State rules (62); Content and approvals (63); Integrations catalogue and detail (64, 65); Payment gateways (66); Plans and pricing (67); Compliance and audit (71) | Builds the four back-office screens still missing: **Platform overview (58)**, **Provider categories (68)**, **AI and credits (69)**, and **self-serve tenant sign-up and payment on the web** (the sprint gate) |
| A6.2 Flags and releases | Per-tenant feature switches on the Tenant detail Features tab (`TenantFeature`: six features with defaults and beta) | Moves features to **Laravel Pennant** with rollouts by tenant, state, role and percentage, a kill switch, release notes and **Features and releases (70)**; the Tenant detail switches become overrides on top |
| A6.3 Privacy foundation | Data map, "Download my data", account deletion after 30 days, privacy requests and their screen, retention (A4.3b, kept current every sprint) | Adds **consent records** (what each person agreed to, when, which version, how withdrawn), the privacy switches on each role's settings screen, and consent checks wherever a feature depends on one (recording, SMS, marketing email, contact sharing) |
| B6.1 Messages | None (support sign-in consent exists, A4.1c) | All of it: conversations, live delivery (Reverb), attachments, report and block, announcements with read receipts |
| B6.2 Notifications | Email through Laravel notifications (14 kinds); the phone schedules its own local reminders (to-dos, B4.3) | One notification centre: push (Expo), email (SES), SMS (Twilio), an in-app inbox, per-person preferences by kind and channel, quiet hours and digests |
| B6.3 Video rooms | None | LiveKit rooms for 1-on-1 sessions and live classes, the shared whiteboard, automatic end, recording only with every participant's consent |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Amazon SES production access (out of the sandbox) and a verified sending domain (reos.com) with SPF, DKIM and DMARC | B6.2 | Client | Mailpit locally; SES sandbox (verified addresses only) in staging |
| Twilio account, A2P 10DLC brand and campaign registration (needs a public privacy policy and terms URL since 30 June 2026; review takes days to weeks) | B6.2 | Client | SMS stays on the Simulator; push and email carry everything |
| LiveKit Cloud project (Build plan is free to develop on; Ship from $50 a month for production) | B6.3 | Mayura | Rooms run on the LiveKit dev server locally and the Simulator in CI |
| Apple push key (APNs) and Firebase project (FCM) for Expo push | B6.2 | Client | Push works in development builds against Expo's sandbox; production keys before TestFlight |
| 40 pilot users across roles (5–8 per role) for usability check 1 | Gate | Nelle | Check moves to the first week of Sprint 7 |

## 2. Market leaders: what they do, where they fall short, what we do

| Leader | What they do well (we match it) | Where they fall short | REOS answer in this sprint |
| --- | --- | --- | --- |
| **LaunchDarkly / Laravel Pennant** | Flags per user, account or percentage; staged rollouts; a kill switch; audit of who changed what | LaunchDarkly is another vendor holding user attributes and priced per seat; Pennant alone has no screen, no release notes and no rollback | **Pennant inside REOS** (no data leaves), a **Features and releases screen** with rollout steps (internal → one tenant → state → percentage → everyone), release notes per role in plain words, and an **automatic rollback** when a flagged feature's error rate rises |
| **Brokerage chat (KW Command, Follow Up Boss inbox), Slack** | Fast, live messaging; threads; files | Work chat mixes clients, colleagues and vendors with no rules; no consent before sharing contact details; vendors can cold-message agents | **Rules by relationship:** agents talk to their broker and team, candidates to their instructors and mentors, providers only after an agent accepts (screen 26: contacts shared only when both agree). Report and block on every conversation |
| **Notification services (OneSignal, Knock, Courier)** | One API for push, email and SMS; preferences; digests | Preferences are usually global on/off; quiet hours are rare; SMS consent is the sender's problem | **Preferences by kind and channel**, **quiet hours** in the person's time zone that hold back anything not urgent, **daily digests**, and SMS only with recorded consent and a working STOP |
| **Zoom / Google Meet** | Reliable video, screen share, recording | Separate app and link; meetings overrun; recording consent is a banner, not a rule; nothing ties the call to the lesson | **Video inside REOS** (web and app), the **room ends at the booked end time** with a two-minute warning, a **whiteboard and quick actions** (practice questions, open a lesson), and **no recording unless every participant agrees** (recorded as consent) |
| **OneTrust-style consent tools** | Consent per purpose with versions and withdrawal | Built for marketing cookies, not for product consents like recording or contact sharing | **One consent record** for every purpose REOS asks about, shown and withdrawable on the settings screen, exported with "Download my data" |

## 3. Sub-sprints

### A6.1 Super admin v1 (Team A)

- **Platform overview (58):** tenants, people, people online now, request latency (p95) and uptime against the targets (p95 under 300 ms, 99.95% uptime), a traffic chart for 24 hours and 30 days, integrations by health, payments (today's orders, failures, refunds, the last reconciliation), queues (waiting and failed jobs per queue). "Open status page" and links into each area. Figures come from REOS's own data and Laravel's queue and request metrics (Pulse); cells and regions show "single region" until the AWS build.
- **Provider categories (68):** unlimited categories (prospect feedback) with name, description, whether a licence check is needed and which licence, the flat monthly price per state (RESPA: flat fees only, never per lead or per deal), status, and how many providers are in each. Add category and edit settings; changes audited. The provider app (later sprint) reads these.
- **AI and credits (69):** the answer order per role (course content first, then the state rules, then the general model, never another person's data), the model per feature and its data rules ("customer data is never used for training", shown and enforced in the request settings), the credit cost of each AI feature, limits per role and per day, and the candidate rule (never charged). Each AI feature reads its cost from here and shows it before it runs. Changes are versioned and audited.
- **Self-serve tenant sign-up (the sprint gate):** a brokerage, school or association signs up on reos.com, picks a tenant plan, pays on the web (B5 checkout), and its tenant is created with the owner as admin, the plan's limits and a sponsor pool. Super admins see it on Tenants (59) and can still create tenants by hand.
- **Done when:** the super admin creates a tenant and a plan, and a brokerage signs up and pays on the web on its own.
- **As built, A6.1a:** screen 58 as the super admin's home (tenants by type, users, online now and active today, p95 and uptime against the targets, requests per hour or day, the cell, integrations, today's payments, queues and failed jobs) from per-minute request metrics (counted in the cache, saved every minute) and a health check every minute (database, cache, queue, storage; kept 90 days); the public status page (`/status`); screen 68 with six starting categories (Texas prices for Basic, Pro, Premium), add and edit with prices per state and tier, audited; screen 69 with the answer order per role, the model, credit costs, default monthly credits per seat and the hourly abuse limit, saved as versions and audited, read by AI credits and voice to-dos.
- **As built, A6.1b:** "Start your brokerage" and "Start your school" on reos.com (from the audience pages): name, live states and a plan sold to that kind of tenant in the chosen state, then the B5 checkout; when the payment lands the tenant is created with the plan's seats, the owner becomes its broker or school admin and works there at once, and the sign-up is audited. Associations still join with the REOS team (screen 59), as the public site says.

### A6.2 Flags and releases (Team A)

- **Pennant** (database driver) for every feature flag, with scopes: person, tenant, state and role. `TenantFeature` becomes a set of Pennant features; the Tenant detail switches become per-tenant overrides.
- **Rollouts:** internal (REOS staff) → named tenants → states → a percentage of tenants → everyone. A flag can be switched off everywhere at once (kill switch).
- **Features and releases (70):** the flag table (feature, rollout step, who sees it, errors since rollout) and releases (version, date, what changed in plain words per role, which flags it rolls out). New release and change rollout; every change audited.
- **Automatic rollback:** if a flagged feature's error rate (exceptions tagged with the flag) passes a threshold for 10 minutes, its rollout steps back one stage and the super admin is told. Canary cells wait for the AWS build; until then the rollback works per feature.
- **What's new:** release notes for a person's role appear once in the web app and the phone (dismissible).
- **Done when:** a feature turns on for one tenant only, and turning it off rolls it back everywhere.
- **As built, A6.2:** Pennant (database store) with every flag declared in `FlagCatalogue` and its rollout in `feature_flags` (ADR 0022); the six tenant features are flags (switches on Tenant detail stay as overrides; the kill switch beats them), and Messages, Notification center and Video rooms start with REOS staff for B6. Rollout steps REOS staff → named tenants → states → a stable share of tenants → everyone, with an optional list of roles; screen 70 with Change rollout, Turn off everywhere (with Undo) and Turn back on, errors since the rollout, and releases with notes per role that roll their flags out to everyone; "What's new" once per role on the web and on Today in the app; `reos:flags:watch` every minute steps a flag back one stage when its error rate stays above its threshold for 10 minutes and emails the super admins. All audited.

### A6.3 Privacy foundation (Team A)

- **Consent records:** purpose (terms, privacy notice, marketing email, SMS, session recording, contact sharing with a provider, AI help using their own data), the text version agreed to, when, how (web, app, by an admin on their behalf) and when withdrawn. Never edited; withdrawal is a new record.
- **Where it's asked:** sign-up (terms and privacy), turning on SMS, each recording (per session), sharing contacts with a provider, marketing email. Each feature checks the latest record before acting.
- **Settings screens:** each role's settings gain privacy switches (prospect defaults: provider network on, recruiting off) and "Your consents" with withdraw; the phone's Privacy screen too.
- **Data map, export, deletion:** consent records, messages, notifications, sessions and recordings join the data map, "Download my data" and account deletion (messages stay for the other person, attributed to "Former member").
- **Done when:** a user downloads their data including their consents, messages and sessions.
- **As built, A6.3:** `consent_records` (never edited or deleted; kept without the person after account deletion) with nine purposes, the version of the words, when, how (web, app, by REOS on someone's behalf with the reason, by text reply) and the thing it was for. Every sign-up path (email and password with a required tick, Google, Apple, texted code, SSO, invitation) records the terms and privacy notice, published at `/terms` and `/privacy` in English and Spanish (drafts for counsel). Settings › Privacy (every role) and Me › Privacy on the phone: switches for news email, texts, AI help with your own data, provider network (on by default for prospects) and recruiting (off), and "Your consents" with Withdraw (Undo for switches); super admins change switches on someone's behalf from People with the reason, audited. `Consents::allows()` is the check before acting: voice to-dos use the built-in parser when AI help is off; texts, recording and contact sharing call it when B6 and the provider app arrive. Consents are in "Download my data" and the data map; messages and sessions join them with B6.1 and B6.3.

### B6.1 Messages (Team B)

- **Conversations:** one-to-one and small groups (up to 20), text, attachments (images, PDF; scanned like lesson files, 20 MB), replies, edits within 5 minutes, delete for everyone within 5 minutes, read state. Live through **Laravel Reverb** on the web and the phone (WebSocket while open, push when not).
- **Who can message whom:** people in the same tenant with a working relationship (broker and their agents and team leaders, school staff and their students, mentors and their mentees); providers only after an agent accepts their request, and contact details stay hidden until both share (screen 26). Super admin support can join only with the support consent (A4.1c).
- **Report and block:** on every conversation; a blocked person can't message or see read state; reports go to the tenant admin and REOS with the reported messages.
- **Announcements:** school, brokerage and association admins send to all members or a group (role, team, state), with read receipts and a "Read by 82 of 140" count; the phone shows them on Today.
- **Screens:** web Messages (each role's messages entry; provider screen 26), the phone's Messages tab or sheet as the phone journeys place it, announcements on the admin screens and Today.
- **Done when:** a message arrives live on the web and in the app.
- **As built, B6.1a:** conversations (one-to-one, reused, or groups up to 20), replies, edit or delete for everyone within 5 minutes, read state ("Read") and unread counts; who can message whom by relationship (ADR 0023); live through Laravel Reverb on private channels for the web (session) and the phone (token, key from app config); web Messages for every role with the unread count in the menu; phone Messages from Today, a conversation and New message; all behind the `messages` flag (demo: Lone Star Realty's agents, brokers and mentors). Attachments, report and block, and announcements follow in B6.1b.
- **As built, B6.1b:** attachments (up to 5 images or PDFs, 20 MB each, virus-scanned before they open through a 30-minute signed link; removed if infected; deleted with the message), report and block on every conversation (block both ways for one-to-one, read state hidden; reports copy the messages and go to the tenant's admins by email and to REOS on Compliance and audit, where they're marked handled), and announcements from brokerage, school and association admins to everyone or a group by role, team and state, shown above Messages and on Today until read, with "Read by X of Y". Messages, files, blocks, reports and announcements join the data map and "Download my data" (A6.3 done-when for messages; sessions join with B6.3).

### B6.2 Notifications (Team B)

- **One centre:** every notification (the 14 existing kinds plus messages, announcements and sessions) goes through REOS's notification centre, which picks channels from the person's preferences and records what was sent.
- **Channels:** in-app inbox (bell, web and phone), push (Expo; tokens per device, removed on sign-out and when Expo reports them dead), email (SES with bounce and complaint handling), SMS (Twilio; only with SMS consent; STOP and HELP handled; US numbers).
- **Preferences** by kind and channel on each role's settings (screen 23) and the phone's Me; sensible defaults per kind; security messages (new device, password, two-step) can't be turned off.
- **Quiet hours** in the person's time zone (default 9 PM to 7 AM): anything not urgent waits until the morning; urgent kinds (security, a session starting in 5 minutes) still arrive.
- **Digests:** daily or weekly summaries instead of single messages, per kind.
- **Done when:** quiet hours hold back non-urgent alerts and deliver them in the morning.
- **As built, B6.2a:** one notification centre for all 16 existing notifications plus announcements (ADR 0023): the inbox (bell on the web top bar and on Today), channels by kind with security messages locked, quiet hours in the person's time zone (default 9 PM to 7 AM) that hold non-urgent alerts and release them when they end, daily or weekly digests at 8 AM local time, and a delivery log; Settings › Notifications and Me › Notifications. Push (Expo) follows in B6.2b, SMS (Twilio with consent, STOP and HELP) and SES bounces in B6.2c.
- **As built, B6.2b:** push to the phone (ADR 0023 items 15–16): tokens per phone tied to its sign-in (removed on sign-out and when Expo reports them dead, at once or from the receipts), push as a channel for every kind (held in quiet hours, gathered in digests, off per kind), messages pushed one per conversation unless read first or from someone the person blocked; Today's "Get messages on this phone" card and Me › Notifications › Push on this phone ask for permission only after a tap. The delivery log is pruned after 90 days; the inbox, choices and phones are in "Download my data".
- **As built, B6.2c:** texts (ADR 0023 items 17–18): a verified US mobile number (code, lock after five wrong codes, one account per number) on Settings › Notifications and Me › Notifications, the "Get texts from REOS" switch that shows the carrier disclosure, records SMS consent and sends the confirmation text, texts as a channel per kind (off by default, never for locked kinds, consent checked again when sending), and the signed Twilio webhook for STOP, START and HELP. SES bounces and complaints, and the SES mailer from Settings › Integrations, follow in B6.2d.
- **As built, B6.2d:** email through Amazon SES (ADR 0023 item 19): the `reos` mailer resolving SES and the From address from Settings › Integrations at send time (the log until set up), the SNS-signed `/webhooks/ses` (subscription confirmation, optional pinned topic), suppression of hard bounces and complaints (audited) applied to every outgoing email and to the centre's choice of channels, and "Resume email" on Settings › Notifications and Me › Notifications. B6.2 is complete.

### B6.3 Video rooms (Team B)

- **LiveKit rooms** for 1-on-1 sessions and live classes (up to 100 viewers, instructor and up to 6 on camera), on the web and the phone (LiveKit React and React Native SDKs). Tokens are minted by REOS for one room and one person for the booked time.
- **Session room (instructor screen 15):** both videos, a timer with a two-minute warning, a **shared whiteboard** (tldraw, synced through the room's data channel and saved to the session), quick actions (send practice questions, open a lesson), and end. Join opens 5 minutes before.
- **Automatic end** at the booked end time: the room closes for everyone.
- **Recording only with consent:** recording starts only after every participant agrees in the room (a consent record each); anyone can stop it; recordings go to S3 (LiveKit egress) and play only to participants.
- **Scheduling for now:** an instructor or mentor schedules a session with a student from a simple form (time, length, who). Booking, payment and availability arrive with the marketplace sprints; the room doesn't change.
- **Session notes (screen 16):** notes and the whiteboard saved with the session, visible to both.
- **Done when:** two users hold a 45-minute session that ends on time.
- **As built, B6.3a** (ADR 0024): sessions behind the `video_rooms` flag. Instructors and mentors schedule a 1-on-1 or a live class on the web (people they work with; 15 to 120 minutes; no double-booking); guests are told and reminded 10 minutes before (urgent). Join opens five minutes before; REOS mints a LiveKit token per person and room valid until the booked end; `reos:sessions:tick` closes the room on LiveKit and in every open screen at the end. The room on the web and the phone has the videos, a timer with a two-minute warning, Leave and End for everyone; outside production, until LiveKit is set up, it's simulated with name tiles. Shared session notes (screen 16). Screens: the instructor's Live classes (web screen 46, week list and Schedule), every role's sessions list and a Next session card on Home; on the phone, the next session on Today, Sessions and the session screen. Sessions are in "Download my data". Scheduling on the phone, the whiteboard, recording with consent and quick actions follow in B6.3b.
- **As built, B6.3b** (ADR 0024 items 10–13): the shared whiteboard with **Excalidraw** instead of tldraw (tldraw needs a paid commercial licence in production; decided 22 November 2026), drawn together live through the room's data channel and saved with the session as the drawing and a picture (the phone shows the picture); quick actions for the host (share a lesson; send three practice questions answered in the room); scheduling on the phone. Recording with consent follows in B6.3c.
- **As built, B6.3c** (ADR 0024 items 14–16): the host asks to record; everyone in the room answers (each a consent record); it starts only when all agree and one "no" cancels; anyone can stop it; someone joining while it records agrees first. LiveKit records the room to REOS's content bucket and its signed webhook finishes it (also keeping who is in the room). Recordings play only to the people in the session, can be deleted by any of them, and are deleted 90 days after the session; listed in "Download my data". B6.3 is complete.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the locked journey screens side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:** people can only message, see and join what their relationship allows; blocked people are blocked everywhere; WebSocket channels authorise per conversation; room tokens are per person, per room and expire; recordings need every participant's consent; SMS never goes without consent; flags can't be changed without two-step and are audited.
- **Gate:** a tenant signs up and pays on the web; usability check 1 with pilot users (findings logged as issues for Sprint 7).
- **ADRs:** feature flags and releases (0022), messaging and notifications (0023), video rooms and consent (0024).

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| 10DLC approval takes weeks | Start registration on day 1 (needs the public privacy policy and terms URLs); push and email carry everything until approved |
| WebSocket connections at scale | Reverb with Redis for horizontal scaling; the phone falls back to push when backgrounded |
| Video quality on weak connections | LiveKit simulcast and adaptive stream; audio-only fallback; the session timer is server-side so a dropped call doesn't extend it |
| Recording consent misunderstood (Texas is a one-party state; other states are not) | REOS requires every participant's consent everywhere, recorded per session |
| Abuse in chat | Relationship rules, report and block, rate limits, attachment scanning |
| Flag sprawl | Every flag has an owner and a removal date shown on screen 70 |

## 6. Decisions (22 November 2026)

1. **Video provider:** LiveKit Cloud (Build plan to develop, Ship in production); can move to self-hosted LiveKit on AWS later without changing the app.
2. **Who can message whom:** by relationship (B6.1): broker with their agents and team, school staff with their students, mentors with their mentees; providers only after an agent accepts, with contacts hidden until both share.
3. **Quiet hours default:** 9 PM to 7 AM in the person's time zone; urgent kinds always delivered; each person can change it.
4. **Session recordings:** made only when every participant agrees; kept 90 days, then deleted.

## Sources

- LiveKit Cloud plans: https://livekit.com/pricing
- Twilio A2P 10DLC (privacy policy and terms URLs required from 30 June 2026): https://www.twilio.com/en-us/changelog/a2p-10dlc-campaign-registration-will-require-privacy-policy-and-
- Twilio A2P 10DLC overview: https://www.twilio.com/docs/messaging/compliance/a2p-10dlc
- Laravel Pennant (13.x): https://laravel.com/docs/13.x/pennant
- Texas Data Privacy and Security Act (Attorney General): https://www.texasattorneygeneral.gov/consumer-protection/file-consumer-complaint/consumer-privacy-rights/texas-data-privacy-and-security-act
