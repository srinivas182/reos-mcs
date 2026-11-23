# Sprint 11 spec: Agent core and CE · social channels

- **Dates:** 26 April – 7 May 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 24 November 2026 under the standing approval to build the next sprints in order. The decisions in section 6 are all as recommended.
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 11: A11.1–A11.3, B11.1–B11.3).
  - The Licensed Agent Complete Journey:
    - Setup screens: 4 welcome, 5 pick your goal, 6 how you work, 10 90-day plan.
    - Home screens: 14 Today, 15 Today on day one.
    - CE screens: 88 CE library, 103 CE certificate, 104 CE reporting, 105 add CE from another provider, 106 renew your licence.
    - Posting screens: 108 publish or schedule a post, 169 my posts and results, 170 social accounts.
    - Scorecard and modes: 110 weekly scorecard, 121 Launch home, 126 suggested switch to Grow, 127 Grow home, 132 suggested switch to Lead, 137 Own locked, 146 My goal.
    - 184 morning voice brief.
  - The Agent Modes Journey (one membership, four modes, flags, switch signals set per state).
  - The Agent App Journey (five tabs; Today as one ranked list from every tool).
  - The Instructor and Mentor Journey (CE reporting for schools).
  - The Connections and Sync Journey (social accounts).
  - The S10 wrap-up.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 11 | Already in place | Sprint 11 now does |
| --- | --- | --- |
| A11.1 Agent home | Today on the phone and the agent dashboard on the web, with calendar, to-dos, CRM insight, deals and mentors rows (B4, B7, B8, B9, A10.3c); switch to agent (A10.3c); TREC lookup (B10.3a) | **Modes:** Launch, Grow, Lead and Own on one Agent membership, from the welcome, the goal pick (Own locked until 4 years or a broker licence, read from the TREC record) and work-style flags. **My goal** with its history. **Suggested switches** from signals: the first verified closing suggests Grow; about 12 closings in 12 months suggests Lead; thresholds are per state. **90-day plan** and **weekly scorecard** (targets halve for part-time). **Today as one ranked list** from CRM follow-ups, deal deadlines, calendar and plan tasks, each with its source, plus the day-one empty state. **Spoken morning brief.** The skills assessment (screen 7) moves to Sprint 14 (practice); until then the plan starts from the phases |
| A11.2 CE wallet | Texas CE rules and the CE card (A5.2); REOS course completions (A8); certificate OCR into completions (B10.3b) | **CE wallet** on web and phone: hours against the renewal per licence, required and elective. Completions are REOS-reported, self-reported or confirmed on TREC's record (the nightly TREC data's education and MCE status). **Reminders** at 60, 30 and 7 days before renewal. **Buy CE** from the course catalogue. **Renew your licence** checklist |
| A11.3 CE reporting | Course approvals and roster rules (A7); live-class CE report (A10.2c) | **Reporting steps** for each REOS completion: completed, final passed, reported to TREC (reference), member record, certificate. Schools either download **TREC's completion file** to upload themselves, or let **REOS submit** on their behalf. Each step has a status, and the TREC reference is entered on confirmation |
| B11.1 Meta | Connection engine with OAuth, sync streams and the Simulator (B3); listing posts (B10.2a) | **Instagram and Facebook** pages connected per agent. **Publish now or schedule.** **Insights** read back (reach, likes, comments, clicks). One posting engine all channels use |
| B11.2 LinkedIn and Google Business Profile | Same | **LinkedIn** (member posts; pages behind Community Management approval, beta) and **Google Business Profile** local posts, with insights |
| B11.3 YouTube and TikTok | Same | **Short video** posting to YouTube Shorts and TikTok (private until TikTok's audit), with insights |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| TREC CE roster submission route for providers (file layout, upload portal) | A11.3 | Nelle (school) with Mayura | REOS generates the file; the school uploads it, and the step shows "Pending upload" |
| Texas REALTORS® member record update route | A11.3 | Client | The step shows "Not connected" and is skipped |
| Meta app review (`pages_manage_posts`, `instagram_content_publish`, insights) | B11.1 | Client with Mayura | Simulator; sandbox with test users |
| LinkedIn Community Management API approval | B11.2 | Client with Mayura | Member posting only (beta) |
| Google Business Profile API access | B11.2 | Client with Mayura | Simulator |
| YouTube Data API quota; TikTok Content Posting audit | B11.3 | Client with Mayura | Private TikTok posts until audited; Simulator |

## 2. Market leaders: what they do, where they fall short, what we do

| Leader | What they do well (we match it) | Where they fall short | REOS answer |
| --- | --- | --- | --- |
| **The CE Shop, Champions School, McKissock** | CE catalogues, certificates, reporting to TREC | Only their own courses count; renewal tracking stops at their certificate | **One CE wallet** with every course: REOS, other providers (read from the certificate) and TREC's own record, against the renewal date, with reminders and a renewal checklist |
| **Buffer, Later, Hootsuite** | Scheduling, multi-channel publishing, analytics | No real estate rules: broker name, fair housing and MLS terms are left to the agent | **One posting engine** with the MLS's display rules (B10.2) and the brokerage's attribution on every post, and insights next to the CRM leads they bring |
| **Coaching apps and CRMs (BoldTrail, Follow Up Boss)** | Smart lists and tasks | One tool's list; a new agent sees empty screens | **Today ranks everything**: CRM follow-ups, deal deadlines, calendar and plan tasks. **Modes** change the home as the agent's goal changes, and day one shows three first steps instead of empty lists |

## 3. Sub-sprints

### A11.1 Agent home (Team A)

- **A11.1a Modes and goal:**
  - **Screens:** welcome (screen 4), pick your goal (5), how you work flags (6), My goal (146).
  - **Agent profile:** goal, flags and history.
  - **Own** unlocks at 4+ years licensed (from the licence's licensed-on date) or a broker licence; until then it shows the countdown (137).
  - **Suggested switches** (126, 132) come from signals: the first verified closing suggests Grow, and closings in 12 months suggest Lead. The thresholds are per state in the state rules. "Not yet" asks again in 30 days.
- **A11.1b Plan, scorecard and Today:**
  - **90-day plan** (10): four phases (set up, conversations, appointments, contract to close), a weekly target, and the First-Closing Promise. Grow, Lead and Own get their own plans, and targets halve for part-time.
  - **Weekly scorecard** (110): conversations, appointments, clients and CE against target, with the one fix.
  - **Today** (14):
    - One ranked list of CRM follow-ups, deal deadlines, calendar and plan tasks, each with its source.
    - Mode-specific stats at the top.
    - Done here means done at the source where the tool allows it.
    - The day-one empty state (15) shows until 20+ contacts or a connected tool.
- **A11.1c Morning brief** (184): read aloud on the phone, with the same text on the web: to-dos, appointments and the lead to call first. It goes out at the agent's chosen time.
- **As built, A11.1a** (ADR 0044):
  - **Agent profile:**
    - The goal (Launch, Grow, Lead or Own), work-style flags and the goal's history.
    - Own unlocks with a broker licence or after the state's years licensed. Texas uses 4, TREC's broker experience rule, and the screen shows the date it opens.
    - Default: Launch for agents licensed under a year with no closings.
  - **Suggested switches** from verified closings (transaction-tool deals only): the first suggests Grow, and the state's count in 12 months (Texas 12) suggests Lead. "Not yet" waits 30 days. Thresholds are state rules under a new Agent modes topic.
  - **Phone:** welcome, goal and flags (screens 4–6); My goal in Me (146); Today shows "Pick your goal", the mode badge and suggestions.
  - **Web:** `/agent/setup`; the dashboard card; Settings › My goal for agents.
- **As built, A11.1b** (ADR 0044):
  - **90-day plan** (`agent_plans`, `agent_plan_tasks`): thirteen weeks per goal from REOS's templates.
    - Launch has four phases (set up, conversations, appointments, first contract); Grow, Lead and Own have three each.
    - Weekly targets for conversations, appointments, active clients and CE hours; part-time halves them.
    - Some tasks repeat weekly (call your sphere, pipeline review, mentor check-in, team huddle); the rest are done once per phase.
    - REOS ticks two tasks itself: a connected tool, and 20 contacts.
    - A new goal starts a new plan; going back resumes the old one. Launch carries the First-Closing Promise.
  - **Today** (`AgentToday`): one ranked list: overdue, deal deadlines within 72 hours, today's appointments, follow-ups and CRM tasks due today, then this week's plan tasks.
    - Each item names its source from the integrations register.
    - Done is done at the source: a to-do completes its CRM task or deal date (B7.2, B9.2), with Undo.
    - Six show at once, with a count of the rest.
    - Stats: Launch shows the plan week, conversations and appointments against target; Grow shows the pipeline, deals under contract and what needs the agent today; Lead and Own show closings in 12 months, the pipeline and deals under contract.
    - Day one (screen 15): three steps (import contacts, announce the license, connect tools) until 20 contacts or a connected tool.
  - **Weekly scorecard** (110): Monday to Sunday in the agent's time zone. Conversations are CRM calls, emails and texts plus notes logged in REOS; appointments are appointments and showings; clients are active and under-contract deals; CE is completed hours. The fix is the metric furthest behind its target.
  - **API:** `GET /api/v1/agent/today`, `GET /api/v1/agent/plan`, `PUT /api/v1/agent/plan/tasks/{code}`, `POST /api/v1/agent/plan/promise`, `GET /api/v1/agent/scorecard`.
  - **Web:** Today on the dashboard (screen 12); `/agent/plan` with the scorecard; to-dos are marked done from the dashboard through `/today/todos/{id}/done`. The public agent pages also reserve `plan`.
  - **Phone:** Today's ranked list and day one; `agent/plan` with the scorecard.
- **As built, A11.1c** (ADR 0044):
  - **The brief** (`AgentBriefs`): a greeting; then to-dos (with how many are overdue), today's appointments, deal deadlines in the next three days, new leads since yesterday and CE progress; who to call first; and a closing line. It's in the agent's language, about a minute read aloud.
    - "Call first" is the newest new lead with a phone, or else the contact on the first follow-up.
    - The commands offered at the end: call that person, open the first deadline's deal, and open Today.
  - **Settings** (on `agent_profiles`): on or off, the time (HH:MM in the agent's own time zone, sent by the phone or browser), and which sections it covers.
  - **Sending:** `reos:agents:briefs` every five minutes sends "Your morning brief is ready" once a day, within two hours of the chosen time. It's push only, never digested, and says nothing else, so nothing private shows on a lock screen.
  - **Phone** (`agent/brief`): read aloud with the phone's own voice (expo-speech) through the speaker or headphones; Play, Pause, Skip and Read instead; the transcript highlights the line being read.
    - At the end it listens for a command ("Call Ana", "Open the Oak Ridge deal"), recognized on the phone only. Commands can also be tapped.
    - "Hey Siri, play my REOS brief" (an App Shortcut) and the Google Assistant "open app feature" open it.
    - It's linked from Today.
  - **Web** (`/agent/brief`, linked from the dashboard's Today): the same transcript read aloud by the browser's speech, the commands as links (Call opens the phone dialer), and the settings.
  - **API:** `GET /api/v1/agent/brief`, `GET` and `PUT /api/v1/agent/brief/settings`. Public agent pages also reserve `brief`.
- **Done when:** Today shows to-dos from CRM, deals and calendar.

### A11.2 CE wallet (Team A)

- **Wallet:** per licence, hours done against required and electives, with the renewal date. Completions are marked REOS-reported, self-reported or on TREC's record. The nightly TREC data's education and MCE status confirms them.
- **Reminders** at 60, 30 and 7 days before renewal while hours are missing.
- **Buy CE:** CE courses from the catalogue through the existing checkout.
- **Renew your licence** (106): a checklist (CE reported, sponsoring broker, renew with TREC, confirm), then REOS checks TREC's record.
- **Done when:** an outside certificate counts as self-reported.
- **As built, A11.2** (ADR 0045):
  - **Wallet:** `CeWallet`, per licence:
    - hours against the renewal, with the required courses and electives (A5.2's rules);
    - the completions in the period, each REOS-reported, self-reported or on TREC's record;
    - TREC's own view (MCE or SAE status, from the nightly file).
  - **Confirmation:** `reos:ce:confirm` runs nightly after TREC's import.
    - When the record shows the requirement met, the period's completions become "on TREC's record".
    - When it shows a later expiry, the renewal is confirmed and the licence moves to its new period.
  - **Reminders:** `reos:ce:reminders` sends reminders at 60, 30 and 7 days while hours are missing, then "Renewal ready". Each goes once per period (`ce_reminders`), and only the nearest goes if days were missed.
  - **Renew your license** (106):
    - The checklist comes from a new state rule, `ce.renewal_steps`: CE reported, sponsoring broker, renew in REALM, confirmed.
    - "I've renewed with TREC" has Undo; REOS confirms from the record.
    - Fees are paid to TREC.
  - **Buy CE:**
    - Web: the cheapest live agent plan with `learn.ce` in the state, through the existing checkout, or "Take CE in Learn" when the plan is held.
    - Phone: opens Membership, with no price shown.
  - **API:** `GET /api/v1/me/ce`, `PUT /api/v1/me/licences/{licence}/renewal`.
  - **Web:** `/ce` (CE wallet in the agent sidebar), with Add a certificate (B10.3b's dialog) and the renewal checklist.
  - **Phone:** Me → CE wallet (`ce`) and `ce/renew/[id]`.
  - REOS completions now record their course (`course_id`) for A11.3.

### A11.3 CE reporting (Team A)

- **Steps** per REOS completion (104): completed, final passed, reported to TREC (reference), member record updated, certificate issued.
- **Schools:**
  - Download TREC's completion file for a date range and upload it themselves, or let REOS submit for them.
  - Enter the TREC confirmation reference.
  - The steps move for every student in the file.
- **Done when:** a completion moves to Reported with the TREC reference.

### B11.1 Meta (Team B)

- **One posting engine:**
  - posts with channels, a schedule, status per channel, and retries;
  - the listing service's rules for listing posts (B10.2) and the attribution on every post.
- **Instagram and Facebook:** connect a page and its Instagram business account; publish now or schedule; insights (reach, likes, comments, clicks) read back.
- **Screens:**
  - Social accounts (170).
  - Publish or schedule (108), from a listing post or a new post.
  - My posts and results (169).
- **Done when:** a post appears on both with insights back.

### B11.2 LinkedIn and Google Business Profile (Team B)

- **LinkedIn:** member posts; page posts once Community Management API approval arrives (beta); insights.
- **Google Business Profile:** local posts (what's new, offers) on the agent's profile; insights.
- **Done when:** the same as Meta.

### B11.3 YouTube and TikTok (Team B)

- **Short video upload:** YouTube Shorts and TikTok, from a video the agent picks (the content studio makes them in Sprint 12).
- **TikTok:** private until the Content Posting audit.
- **Insights:** views, likes and comments.
- **Done when:** a short video posts to both.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the journeys side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - an agent sees and changes only their own mode, plan and wallet;
  - CE reporting files are for the school's own students only;
  - social tokens are encrypted, posts go only to the agent's own connected accounts, and listing posts keep the MLS's rules.
- **ADRs:** 0044 agent modes and Today; 0045 CE wallet and reporting; 0046 social publishing.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Mode rules feel forced | The agent always decides; every feature stays reachable in every mode; thresholds are set per state |
| Today ranks the wrong thing first | A transparent order: overdue first, then deadlines within 72 hours, then appointments, then follow-ups, then plan tasks. Each item names its source |
| Social platform approvals take weeks | The Simulator for every channel; sandbox test users; LinkedIn pages and public TikTok are marked beta until approved |
| TREC's CE reporting route differs from the plan | The file is generated in REOS either way; the upload step can be done by the school |

## 6. Decisions (24 November 2026, all as recommended)

1. **Modes:** four modes on one Agent membership. Own unlocks at 4 years or a broker licence. Switch signals are per state (first verified closing → Grow; 12 closings in 12 months → Lead).
2. **Skills assessment:** moves to Sprint 14 with role-plays; the 90-day plan starts from its four phases until then.
3. **Today's order:** overdue, deadlines within 72 hours, today's appointments, CRM follow-ups, plan tasks. Done in REOS writes back where the source allows.
4. **CE status:** "self-reported" until TREC's nightly data shows the requirement met.
5. **CE reporting:** REOS generates TREC's completion file for every school. Submitting on a school's behalf is a per-school setting.
6. **Social:** one posting engine for every channel. Listing posts go through the listing service. Tokens are per agent, encrypted.

## Sources

- Meta Graph API, Instagram content publishing: https://developers.facebook.com/docs/instagram-platform/content-publishing/
- LinkedIn Posts API: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api
- Google Business Profile local posts: https://developers.google.com/my-business/content/posts-data
- YouTube Data API videos.insert: https://developers.google.com/youtube/v3/docs/videos/insert
- TikTok Content Posting API: https://developers.tiktok.com/doc/content-posting-api-get-started
