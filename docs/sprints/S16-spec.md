# Sprint 16 spec: Agent oversight · provider network

- **Dates:** 5 – 16 July 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 28 November 2026 ("Let us start sprint 16") under the standing approval to build the sprints in order. The decisions in section 6 are all as recommended.
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 16: A16.1–A16.3, B16.1–B16.3).
  - The Broker and Team Leader Journey: oversight, CE and sponsorship, announcements, training, reports and the morning brief.
  - The Service Provider Journey: agents in my areas, requests, the private network.
  - The Licensed Agent Journey: providers near you.
  - The Web App Journey: broker agents and reports, the agent's service providers.
  - The S15 wrap-up.
- **Benchmark first** (sprint plan):
  - Brokerkit and Follow Up Boss reports, for brokers;
  - Thumbtack and Angi request flows, for the provider network.
- **Gate (end of Phase 4, sprint 18):** a pilot brokerage runs a full week on REOS with its agents, mentors, coordinators, providers, school and association active.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 16 | Already in place | Sprint 16 now does |
| --- | --- | --- |
| A16.1 Oversight | The roster with team and last activity (A4.3a). Each agent's own activity, counted once (A11, A13). Deals, with deadlines and closings from SkySlope, dotloop, Lone Wolf and BoldTrail (B8, B9). The mentor's "needs help" signals (A13.1b). | **Agents needing help**: quiet for 14 days, no deal in 90 days, CE behind, a plan behind, a deadline missed, and new agents in their first 90 days. **Deals at risk**: a key date within 72 hours with tasks open, a deal stuck in one stage, an option period ending. **The agent profile for the broker**, with privacy rules: counts and stages, never client names, contact details or notes |
| A16.2 CE compliance and sponsorship | Each licence's CE or SAE requirement from TREC's rules (A5.2), the CE wallet and reminders (A11.2), TREC's nightly file (B10.3a), switching brokerage (A14.3). | **CE compliance for the broker**: every agent's renewal, hours left and SAE status, in one table. **Remind all due agents in one action.** **Assign a course** to an agent or a group. **Sponsorship join and release** as TREC records it, with a **handover** of open deals and the release date |
| A16.3 Announcements, training and reports | Announcements with "Read by 82 of 140" (B6.1b). Courses and CE (A7). The agent's morning brief (A11.1c). | **Read receipts per person**, with a reminder to those who haven't read. **Brokerage training**: the brokerage's own course list with assignments and due dates, and CE credit where the course is approved. **Reports**: closings, volume, by agent and team, matched to the transaction tool. **The broker's morning brief** |
| B16.1 Agents in area | Service areas with the count of active agents (B15.2). | **Agents in the provider's areas by activity level** (new, active, top producer), from the MLS and REOS. Filters. **Requests to connect**, with a monthly limit per plan: Listed 5, Pro 25, Partner 75 |
| B16.2 Private network | Messages with block and report (B6.1). | **Private chat between a provider and an agent**, opened only when the agent accepts a request. **Mutual contact sharing**: phone and email show only after both agree. **Block and report.** **Agent opt-out** from all providers, and from each category |
| B16.3 Agent directory | Verified providers on the web and phone (B15.3). | **Providers near you** for agents: categories, profiles, **reviews from agents who worked with them**, and **Request to connect** in two taps |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| RESPA counsel review | B16.1–B16.3 | Client | Providers stay off for production tenants (the `provider_network` flag, B15.2). Requests are requests to talk, never referrals, with no fee per request, connection or closing |
| A pilot brokerage's transaction tool | A16.3 reports | Client | The Simulator's SkySlope and dotloop deals |
| TREC sponsorship changes | A16.2 | TREC (nightly file) | Join and release are recorded in REOS, and confirmed when TREC's file shows the new sponsor |

## 2. Market leaders: what they do, where they fall short, what we do

| Area | Leaders: what to adopt | Where they fall short | What REOS does better |
| --- | --- | --- | --- |
| Oversight and retention | **Brokerkit**: retention reminders for agents with no one-on-one in 30 days, "gone quiet" with last contact and sign-in, production for 12 months, and switch-risk scores from Relitix and Courted. **Lone Wolf** and **BoldTrail** add AI "likely to leave" signals. | The signals come from MLS production alone, sold as add-ons, and arrive after the agent has already gone quiet. The broker sees everything or nothing about an agent's business | **Signals from the agent's actual work in REOS**: conversations, appointments, plan, CE, deadlines. Included, not an add-on. **Help before it's too late**, with one tap to message, assign a mentor or a course. **Privacy rules**: the broker sees counts and stages, never the agent's clients |
| CE compliance and sponsorship | Brokers track CE in spreadsheets; schools email certificates. TREC's site shows one licence at a time. | Nobody tells the broker which agents are behind until a licence expires. SAE (98 hours before the first renewal, never deferrable) is missed by new agents | **Every agent's renewal and SAE status** from TREC's rules and nightly file, in one table. **Remind all due agents in one action.** Assign the course that closes the gap. **Join and release** with a handover of open deals |
| Reports | **Follow Up Boss**: agent activity, lead source and deal reports. **Brokermint** and **SkySlope**: closings and volume from the back office. | Activity reports and closing reports live in different tools and don't agree | **One report**, with closings matched to the transaction tool record by record, and a reason for every difference |
| Provider requests | **Thumbtack**: the customer chooses which pros to contact, and the pro pays per lead. **Angi**: a request goes to up to five pros, who pay per lead even when they lose. | Pay-per-lead means providers pay for contacts that go nowhere, and the person asking is spammed by five companies. For agents and lenders, pay-per-referral is a RESPA problem | **The agent decides**: providers send a few requests a month (a limit per plan, not a price per request), and the agent accepts or ignores them. **No payment per request, connection or closing.** **Contacts are shared only when both agree**; agents can opt out of everything or one category |

## 3. Sub-sprints

### A16.1 Oversight (Team A)

- **Agents needing help**, each with the reason:
  - quiet for 14 days (no conversation, appointment or sign-in);
  - no deal in 90 days;
  - CE or SAE behind for the time left;
  - a plan behind;
  - a deadline missed;
  - new agents in their first 90 days.
- **Deals at risk:** a key date within 72 hours with tasks open, a deal in one stage for 30 days, an option period ending.
- **The agent profile for the broker:** mode, plan, activity this month, deals by stage, CE and licence, mentor. **Privacy rules:** counts and stages only; never client names, contact details or notes. The property address shows only for deals the brokerage must supervise (under contract).
- **Done when:** the broker sees risk without seeing clients.
- **As built, A16.1** (ADR 0076):
  - **Data:** `organisations.oversight_settings`: the quiet days, no-deal days and new-agent days.
  - **Services:**
    - `App\Brokerage\Oversight`:
      - board: agents with their signals, and deals at risk;
      - profile: one agent as their broker sees them, audited;
      - mine: what my broker sees;
      - setSettings: the designated broker only.
    - `BrokerageAccess::scope`: the brokerage, and the team for a team leader.
  - **Routes:**
    - API: `GET /api/v1/organisation/oversight`, `GET /api/v1/organisation/oversight/agents/{member}`, `PUT /api/v1/organisation/oversight/settings` and `GET /api/v1/me/broker-view`.
    - Web: `/organisation/oversight`, `/organisation/oversight/agents/{member}`, and the settings.
  - **Web:** Brokerage › Agents and deals (`oversight-board`): who may need help with the reasons, deals at risk, on track, the privacy note and the thresholds. An agent's page shows the month, plan, licences and CE, and deals by stage.
  - **Phone:**
    - Your brokerage → Agents and deals (`brokerage/oversight`), and an agent (`brokerage/agent/[id]`);
    - for agents, Me → What my broker sees (`me/broker-view`).
  - **Done when:** a Pest test shows Linda Park, at Lone Star Realty, that James Miller may need help: quiet, no deal, CE behind. Priya Shah's deal under contract is at risk, with its option period ending tomorrow. Neither the board nor Priya's profile contains her clients' names, emails or phone numbers, or the address of her active listing. Other tests cover:
    - Priya sees exactly what Linda sees;
    - a team leader sees only their team;
    - the thresholds are the designated broker's to change;
    - new agents and missed deadlines are flagged;
    - a plan behind its weeks is flagged.

### A16.2 CE compliance and sponsorship (Team A)

- **CE compliance:** every agent's licence, renewal date, hours left and SAE status.
- **Remind all due agents in one action.**
- **Assign a course** to an agent, a team or everyone due.
- **Sponsorship:** join and release with the date. The release has a **handover** of open deals to another agent or the broker. It's confirmed when TREC's file shows the change.
- **Done when:** the broker reminds all due agents in one action.
- **As built, A16.2** (ADR 0077):
  - **Data:** `course_assignments`, `ce_broker_reminders` and `sponsorship_changes`, all with row-level security.
  - **Services:**
    - `App\Brokerage\CeCompliance`: table (status per agent), remindDue (once a week per agent, audited), assign (chosen agents or everyone due), markCompleted.
    - `App\Brokerage\Sponsorships`:
      - board;
      - release, with the date and the handover;
      - joined, from accepted invitations;
      - run: `reos:sponsorships` daily after TREC's import, which ends roles on the date and confirms changes against TREC's file.
    - Notifications: `CeBrokerReminder`, `CourseAssigned` (kind `course_assigned`) and `SponsorshipChanged` (kind `sponsorship`).
  - **Routes:**
    - API: `GET /api/v1/organisation/ce`, `POST /api/v1/organisation/ce/remind`, `POST /api/v1/organisation/ce/assign`, `GET /api/v1/organisation/sponsorships` and `POST /api/v1/organisation/sponsorships/release`.
    - Web: the same actions, and `/organisation/ce`.
  - **Web:** Brokerage › CE compliance (`ce-compliance`): the table with status, SAE, renewal, hours, assignments and the last reminder; Remind all due agents; Assign a course; and Sponsorship, with changes and their TREC status, and Release with a handover. CE compliance is now in the broker sidebar.
  - **Phone:** Your brokerage → CE compliance (`brokerage/ce`: Remind all, assign to everyone due) and Sponsorship (`brokerage/sponsorship`).
  - **Done when:** a Pest test shows Linda Park three agents:
    - James Miller, renewing in a month with 18 hours to do: behind;
    - Priya Shah, on SAE: behind;
    - Dana Brooks: on track.

    One "Remind all due agents" reaches James and Priya, the reminders naming Linda, but not Dana. A second the same week is skipped, and a week later it goes again. Other tests cover:
    - a course assigned to everyone due, once, and marked done when completed;
    - the duty and team scope;
    - James released with his deal handed to Dana, his role ended, and confirmed once TREC's file no longer lists him;
    - a future release taking effect on its date;
    - a join confirmed against TREC.

### A16.3 Announcements, training and reports (Team A)

- **Read receipts** per person, and a reminder to those who haven't read.
- **Brokerage training:** courses the brokerage assigns, with due dates and CE credit when approved.
- **Reports:** closings and volume by agent, team and month, matched to the transaction tool.
- **The broker's morning brief:** who needs help, deals at risk, approvals waiting, CE due.
- **Done when:** the report matches the closings in the transaction tool.
- **As built, A16.3** (ADR 0078):
  - **Data:** `announcements.reminded_at`.
  - **Services:**
    - `App\Brokerage\Reports`:
      - closings: by agent, team and month for a period, matched to each connected transaction tool (with its last sync, and stale after a day), and REOS-only closings listed as differences;
      - training: each assigned course's assigned, done and overdue counts, with CE hours.
    - `App\Brokerage\BrokerBriefs`: the brief (need help, at risk, approvals, CE due, closings this month), and `reos:brokerage:briefs` on weekdays at 7:30 Central (`BrokerBriefReady`, kind `morning_brief`), sent only when something is in it.
    - `Announcements::readers` and `remindUnread`: once a day at most, audited `announcement.reminded`.
  - **Routes:**
    - API: `GET /api/v1/organisation/reports/closings?period=`, `GET /api/v1/organisation/reports/training`, `GET /api/v1/organisation/brief`, `GET /api/v1/announcements/{id}/readers` and `POST /api/v1/announcements/{id}/remind`.
    - Web: `/organisation/reports`, `GET /announcements/{id}/readers` and `POST /announcements/{id}/remind`.
  - **Web:**
    - Brokerage › Reports (`organisation/reports`): period, totals, the match to the tool with differences, by agent, team and month, and training. Reports is now in the broker sidebar.
    - The broker dashboard's morning brief card.
    - Announcements: "See who's read it" opens a sheet with unread people first and Remind.
  - **Phone:** Your brokerage → Morning brief (`brokerage/brief`, also opened from the notification) and Reports (`brokerage/reports`).
  - **Done when:** a Pest test connects SkySlope for Lone Star Realty (simulator):
    - with nothing closed, the report shows SkySlope with 0;
    - James Miller's sale closes in SkySlope and syncs: the report's SkySlope line is 1 closing for $389,000, exactly the tool's closing;
    - Maria Garcia's closing recorded only in REOS shows as the one difference, by property.

    Other tests cover periods, a team leader's scope, training progress, the brief and its weekday notification, and read receipts with the once-a-day reminder.

### B16.1 Agents in area (Team B)

- **Agents in the provider's areas by activity level:** new, active, top producer.
- **Filters.**
- **Requests to connect, limited per plan each month:** Listed 5, Pro 25, Partner 75.
- **Done when:** a provider sends 3 requests and the limit is enforced.
- **As built, B16.1** (ADR 0079):
  - **Data:** `provider_requests` (between two people, so no tenant column).
  - **Services:**
    - `App\ProviderNetwork\AgentsInArea`: REOS agents in the provider's areas from their confirmed MLS listings and REOS deals in 12 months, by level (top producer 12+, new when first licensed in the last 12 months, active), with filters and the month's usage. The provider sees first name and initial only. Agents who turned the network off are left out.
    - `App\ProviderNetwork\ProviderRequests`: send (the plan's limit under a lock; once at a time; declined is final; expired may be sent again after 30 days; no contact details in the note), withdraw, sent, and expire (`reos:providers:requests`, daily).
    - `ProviderPlans::REQUESTS` (Listed 5, Pro 25, Partner 75); notification `ProviderRequestReceived` (kind `provider_request`).
    - The provider-network consent is now on by default for agents (decision 4), and can be turned off in Settings › Privacy.
  - **Routes:**
    - API: `GET /api/v1/provider/agents?level=&area=`, `GET /api/v1/provider/requests`, `POST /api/v1/provider/requests` and `POST /api/v1/provider/requests/{id}/withdraw`.
    - Web: `/provider/agents`, and the same two actions.
  - **Web:** Agents in my areas (`provider/agents`, provider sidebar): usage, privacy line, level and area filters, agents with Ask to connect (a dialog with an optional note), and My requests with Withdraw.
  - **Phone:** Me → Agents in my areas (`provider/agents`), with level pills, a request sheet and My requests.
  - **Done when:** a Pest test has Maya Chen on Listed (5 a month) send 3 requests to Katy agents (3 used, 2 left). Two more go through, and the sixth is refused with "You've used all 5 requests for this month. More on December 1". Withdrawing doesn't give one back. On December 1 the month starts again. Other tests cover:
    - levels and the first-name-only view;
    - opted-out agents left out, and live providers only;
    - one request at a time, a decline respected, and expiry after 14 days with another request allowed 30 days later;
    - no contact details in the note, and nobody outside the provider's areas;
    - the web page.

### B16.2 Private network (Team B)

- **Private chat**, opened when the agent accepts.
- **Mutual contact sharing.**
- **Block and report.**
- **Agent opt-out**, from all providers and per category.
- **Done when:** contacts show only after both agree.

### B16.3 Agent directory (Team B)

- **Providers near you:** categories, profiles, reviews.
- **Request to connect** in two taps.
- **Done when:** an agent connects with a lender in two taps.

## 4. Definition of done (whole sprint)

- Every endpoint is in `/api/v1` with Pest feature tests, and the api-client is regenerated.
- Every string is in English and Spanish.
- Every new shared component has a story passing axe.
- **Security tests:**
  - a broker never sees an agent's clients, contacts or notes;
  - a team leader sees only their team;
  - a provider sees an agent's contact details only after both agree, and never an opted-out agent;
  - request limits hold.
- ADRs and the "As built" sections are written.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Oversight feels like surveillance to agents | Counts and stages only; agents see what their broker sees (a "What my broker sees" view) |
| Provider requests become spam | A monthly limit per plan, opt-out per category, block and report, and requests that expire after 14 days |
| RESPA: a request looks like a paid referral | No fee per request, connection or closing; plans are flat fees; the network stays off until counsel signs off |

## 6. Decisions (28 November 2026, all as recommended)

1. **What the broker sees about an agent:** counts and stages, never clients, contacts or notes. The property address appears only for deals under contract, which the broker supervises.
2. **"Needs help" thresholds:** 14 quiet days, no deal in 90 days, CE behind for the time left, a plan behind, a missed deadline, and the first 90 days; brokerages can change the numbers.
3. **Monthly request limits:** Listed 5, Pro 25, Partner 75, reset on the 1st; an unanswered request expires after 14 days and doesn't come back.
4. **Agent controls:** agents can turn off provider requests altogether or for a category; contact details are shared only when both press Share.
5. **Provider reviews:** only from agents with an accepted connection, after 30 days, one per agent per provider.

## Sources

- [TREC: renewing a sales agent license (SAE and CE hours)](https://www.trec.texas.gov/renew-license/real-estate-sales-agent)
- [Brokerkit: real estate agent retention software](https://getbrokerkit.com/home/real-estate-agent-retention-software/)
- [Housecall Pro: Thumbtack vs Angi](https://www.housecallpro.com/resources/thumbtack-vs-angi/)
- [HousingWire: warning signs an agent may leave](https://www.housingwire.com/articles/the-warning-signs-an-agent-may-leave-your-brokerage-and-what-to-do-about-them/)
