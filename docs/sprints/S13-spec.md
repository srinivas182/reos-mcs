# Sprint 13 spec: Mentors, pods, rewards · compliance engine

- **Dates:** 24 May – 4 June 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 27 November 2026 under the standing approval to build the next sprints in order; the decisions in section 6 are all as recommended.
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 13: A13.1–A13.3, B13.1–B13.3).
  - The Licensed Agent Complete Journey:
    - Improve screens: 110 weekly scorecard, 111 daily goals and points, 112 rewards.
    - Get-help screens: 114 mentor, 116 mentors at your brokerage, 117 book a paid mentor session, 118 live mentor session, 119 rate the session.
    - 125 feedback from agents, 131 accountability pod.
  - The Instructor and Mentor Journey: mentor screens 22 Today for mentors, 23 mentees, 24 mentee detail, 25 help with a shared deal, 26 availability, 27 session wrap-up, 28 earnings.
  - The Broker and Team Leader Journey: screens 21 mentor program, 22 mentor settings, 23 assign a mentor.
  - The Web App Journey: goals and rewards (agent), mentor program (broker).
  - The Candidate Journey: the studio for candidates.
  - The S12 wrap-up.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 13 | Already in place | Sprint 13 now does |
| --- | --- | --- |
| A13.1 Mentors | Brokerage-scoped mentor list for new agents (A10.3c); 1-on-1 bookings with paid checkout, no-show refunds, live video rooms that end on time, ratings and follow-up tasks (A10.2); earnings and Stripe payouts (B5.2) | **The broker's mentor program** (web screens 21–23): add mentors, set topics, price, points accepted, max mentees and who can book. **Assign a mentor** with matching by language, focus, mode and capacity; the **assigned mentor is free** to the agent. **Pay with points.** **Wrap-up** with an AI summary and action items to the mentee's to-dos. **Ratings shown after 24 hours**, with low ratings queued for review. **The mentor's workspace**: Today, mentees, mentee detail with private notes and goals |
| A13.2 Pods and challenges | Practice challenges between candidates (A9) | **Accountability pods** of five agents with the same goal, with weekly scorecards shared only inside the pod, a 15-minute pod call and pod messages. **Brokerage challenges** with opt-in leaderboards |
| A13.3 Goals, points, feedback | The weekly scorecard and targets per mode (A11) | **Daily goals and points** (screen 111) and **rewards** (112): 1,000 points = $10 inside REOS, redeemed for mentor sessions, AI credits and membership, plus brokerage bonus campaigns. **Agent-to-agent feedback after verified closings** (125) |
| B13.1 Texas checks | Broker name and size, the title check, and fair housing and claims phrases on social posts (B11, B12.1) | **One compliance engine** for every outbound message (posts, videos, emails and texts to contacts, the public page). **Texas rules as data with citations**: the broker's name, team names, the IABS and Consumer Protection Notice links, and "licensed in Texas". **Context-aware fair housing**: place names and room names aren't flagged, and Claude gives a second opinion when the wording is unclear |
| B13.2 Brokerage rules | Banned phrases, Fix automatically and approval for new agents on social posts (B12.1) | The same rules on **every channel**. **Routing**: a violation is fixed or sent to the broker. **Compliance log** for the broker: what was checked, fixed, approved or sent back, by whom, exportable |
| B13.3 Candidate studio | The studio for licensed agents (B12) | **A free studio for candidates**: study streak, practice scores and the pass announcement, with the same checks. Nothing that advertises real estate services until the license is active (Tex. Occ. Code §1101.351) |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Points value and earning rates confirmed (1,000 = $10) | A13.3 | Client | REOS defaults in the super admin, editable later |
| Who funds points spent on mentors | A13.1 | Client | REOS pays mentors for the points it issues; brokerages fund their own bonus campaigns |
| Pilot brokerages' mentors | A13.1 | Client | The demo brokerage's mentors (Sarah Kim, Dana Brooks) |

## 2. Market leaders: what they do, where they fall short, what we do

| Area | Leaders: what to adopt | Where they fall short | What REOS does better |
| --- | --- | --- | --- |
| Mentoring | **eXp** pairs agents with fewer than three closings with a certified mentor, plus an 8-week course; the mentor is paid from the mentee's first deals. **Real** runs a free 8-week coaching series (Agent BreakThru). **Keller Williams** has MAPS coaching. | Mentors are paid from commission splits, not for time; quality varies and is rarely rated. Coaching is generic, held on Zoom, and never sees the agent's own pipeline. Paid coaching costs hundreds of dollars a month | Mentors from the agent's **own brokerage only**, priced by the brokerage. The **assigned mentor is free**. Sessions are **booked, held, ended and refunded inside REOS**. **Wrap-ups become to-dos**. **Ratings are delayed 24 hours** and **low ratings reviewed**. The mentor sees the mentee's plan and what they share, **never their CRM contacts**. Pay by card or **points** |
| Accountability | **Tom Ferry** coaching uses masterminds and accountability partners. **Sisu** offers goals and leaderboards for teams. | Pods are formed by hand, and scorecards are typed in each week. Public leaderboards discourage new agents. Each lives in a separate tool | **Pods of five matched automatically** by goal, mode, language and part-time status. **Scorecards come from real activity** (no typing) and are **shared only inside the pod**. A 15-minute pod call **ends on time**. Challenges are **opt-in**, with **"most improved"** and **personal-best** boards so new agents can win |
| Rewards | Sisu and team tools gamify activity; brokerages run contests. | Points have no use, or are paid in cash. Some reward schemes pay agents for referring a lender or title company, which RESPA forbids | Points come **only from the agent's own activity**, **never from choosing or recommending a service provider** (RESPA §8). **1,000 = $10 inside REOS**, with **no cash value**. They pay for mentor sessions, AI credits and membership. **Brokerage-funded bonus campaigns**. Every point is in an **audited ledger** |
| Agent reputation | Zillow, RealTrends and Google collect client reviews. | No agent-to-agent signal (who is good to work a deal with); fake reviews | **Feedback only after a verified closing** with both agents on REOS. **No average until three ratings.** **Private by default**; public only if the agent chooses. Agents can respond or report unfair feedback |
| Advertising compliance | **RealComply** (a Chrome extension) flags fair housing wording with AI. **Zillow's** guardrail combines a stop list with a trained classifier. | Keyword lists flag "Indian Wells" or "family room". The checks sit outside the posting flow, aren't Texas-specific (broker name size, IABS link, team names), and have no approval routing or audit | **One engine in every outbound flow**. **Texas rules with citations**. **Context exceptions** for place and room names. **Claude's second opinion** for unclear wording. **Fix automatically** or **send to the broker**. A **compliance log** the broker can export |
| Candidate social | Schools post generic congratulations. | Candidates have no tools, and some advertise services before they're licensed | **A free candidate studio** with the same checks; **service advertising is blocked until the license is active** |

## 3. Sub-sprints

### A13.1a Mentor program and pay with points (Team A)

- **Broker web, Mentor program (screens 21–23):**
  - **Mentors:** each mentor's focus (topics), load (mentees against the maximum), price, whether they accept points, rating and sessions.
  - **Add a mentor:** an invite to someone at the brokerage, using the existing Mentor role.
  - **Mentor settings:** topics, 30- and 60-minute price (set by the brokerage), points accepted, max mentees, and who can book (everyone at the brokerage, or assigned mentees only).
  - **Assign a mentor:** best matches with the reason (language, focus, mode, capacity). Both people are notified and a first session is suggested. Agents can ask for a change.
- **Agent:**
  - **Mentor (114):** the assigned mentor, free.
  - **Mentors at your brokerage (116):** the assigned mentor first, then the other mentors with topic, rating, price and next slot. Mentors from other brokerages are never shown.
  - **Book (117):** pay by card, or **use points** (points deducted). Free cancellation until 24 hours before.
- **Points ledger (core):** `point_entries` (earn, spend, refund, bonus, expire; balance per user), audited. Spending points on a session creates a mentor earnings line paid by REOS at 1,000 = $10.
- **Done when:** an agent books a mentor and pays with points.
- **As built, A13.1a** (ADR 0053):
  - **Data:** `mentor_settings` and `mentor_assignments` (row-level security), and `session_bookings.paid_with` and `points`.
  - **Services:** `App\Mentoring\MentorProgram` (program, save, matches, assign, requestChange) and `MentorDirectory` (assigned mentor, settings, who can book).
  - **Bookings:**
    - The brokerage's prices override the mentor's.
    - The assigned mentor is free; "assigned only" is enforced.
    - **Pay with points:** a points order goes through `RevenueSplit` and `MoneyPostings`, and refunds give the points back.
    - Mentor products pay the `mentor` payee role.
  - **Web:** `/organisation/mentors` (Mentor program in the broker sidebar) shows mentors with focus, load, price, points, rating and hours, a Settings form, and agents with Assign from the best matches. Agents' Your mentors shows the assigned mentor first and free.
  - **API:**
    - `GET /api/v1/mentors` gains `assigned`, `free`, `points`, `change_requested` and `points_balance`.
    - `POST /api/v1/mentors/change` is new.
    - `POST /api/v1/bookings` takes `pay_with`.
  - **Phone:**
    - Your mentors (114/116): your mentor and Free first, then the others with Points accepted, and Ask for another mentor.
    - Book (117): pay with Card or Reward points, showing the points needed and the balance.
  - **Notifications:** `mentoring` (MentorAssigned, MentorChangeRequested).
  - **Demo:** Dana Brooks, at $40 / $75 with points, is James Miller's assigned mentor.
  - **Done when:** a Pest test books Dana with 7,500 points. Dana's share reaches her payout lines, and cancelling the next booking gives the points back.

### A13.1b Mentor workspace, wrap-up and ratings (Team A)

- **Today for mentors (22):** mentees, sessions this week, who needs help (quiet for 7 days, a shared deal, a plan behind).
- **Mentees (23)** and **mentee detail (24):** mode and day, plan progress, conversations, deals, sessions, goals set together, and private notes (visible to the mentor only). The mentee's CRM contacts are never shown.
- **Wrap-up (27):** an AI summary (Claude, or a template) and action items with dates, sent to the mentee's to-dos with reminders.
- **Rate the session (119):** stars, quick tags and a comment.
  - Ratings show on the mentor's profile only after 24 hours.
  - A rating of 2 stars or less with a comment goes to the admin review queue.
- **Done when:** a session ends, its wrap-up reaches the mentee's to-dos, and the rating appears the next day.
- **As built, A13.1b** (ADR 0053):
  - **`MentorWorkspace`:**
    - **Today:** mentees, sessions this week, who needs help, upcoming sessions and wrap-ups to send. Someone needs help if they've been quiet for 7 days, haven't had a first session with their assigned mentor, or have 3 or more open plan tasks.
    - **Mentees:** all, assigned or booked in the last 90 days.
    - **Mentee detail:** the plan's week and progress, this week's scorecard, deal counts, sessions together with their summaries, goals set together (the mentee's to-dos, source `mentor`) and private notes.
    - Notes live in `mentee_notes` (row-level security, soft deletes for Undo). The mentee's contacts and clients are never shown.
  - **Wrap-up** (`WrapUp`, `ClaudeWrapUpWriter`): a summary and dated action items. Claude writes them when it's configured; otherwise REOS uses the notes' action lines, due a week out. `POST /bookings/{id}/tasks` takes the `summary`, which is stored on the booking and goes with the to-dos.
  - **Ratings:**
    - A mentor rating of 2 stars or less with a comment waits as `review_status = pending` and counts only once the broker keeps it (Mentor program → Ratings to review, audited).
    - Ratings still show only after 24 hours.
  - **API:** `/api/v1/mentoring/today`, `mentees`, `mentees/{agent}`, notes (add, remove, restore) and goals, plus `GET /bookings/{id}/wrap-up`.
  - **Web:** `/mentoring` (the Mentor workspace sidebar item) and `/mentoring/{agent}`. The broker's Mentor program has Ratings to review.
  - **Phone:**
    - Home → Mentor workspace (`mentoring/index`): Today and Mentees.
    - `mentoring/[agent]`: mentee detail.
    - The wrap-up screen (`bookings/tasks/[uuid]`) has the summary and dated action items.
  - **Later (A17.1):** help with a shared deal (mentor screen 25) comes with the mentor and team sprint.
  - **Done when:** a Pest test ends a session, sends its wrap-up to the mentee's to-dos with dates and the summary, and holds a 1-star rating until the broker keeps it. Only then does the rating count.

### A13.2 Pods and challenges (Team A)

- **Accountability pod (131):**
  - **Opt in** from Mentors → Pod. REOS matches five agents in the same state by goal and mode, language and part-time flag; the brokerage can also make pods.
  - **Weekly scorecard:** conversations, appointments, clients and CE against target, computed from activity. Shared only inside the pod; each member chooses what to share.
  - **Pod call:** 15 minutes at the pod's weekly time, using the existing video rooms (it ends on time).
  - **Pod messages:** the existing messaging. Leaving a pod is undoable.
- **Challenges:**
  - Brokerage challenges, for example most conversations this week, or CE lessons in November, with an opt-in leaderboard.
  - Boards for total, most improved and personal best.
- **Done when:** a pod of five sees each other's weekly goals.
- **As built, A13.2** (ADR 0054):
  - **Pods** (`App\Pods\Pods`):
    - Opting in puts the agent on `pod_requests`. They join an open pod at once, or wait.
    - The daily `reos:pods` run (06:10) makes pods of five, or three after 7 days, matching brokerage, state, goal, language and part-time. It also books each pod's next 15-minute call (a `pod_call` video session).
    - Each pod has a group conversation, and pod members are message contacts.
    - Weekly goals live in `pod_goals`; the numbers come from `App\Agents\Activity`, and each member chooses what to share.
    - Leave and Undo (rejoin).
  - **Challenges** (`App\Pods\BrokerageChallenges`):
    - The broker creates them at `/organisation/challenges` (audited).
    - Agents opt in; the baseline is stored at joining.
    - Boards for total and most improved, with personal best.
  - **API:** `/api/v1/pod` (show, join, leave, rejoin, goal, share) and `/api/v1/challenges` (list, join, leave).
  - **Web:** Pod and challenges (`/pod`, agent sidebar) and Challenges (`/organisation/challenges`, broker sidebar).
  - **Phone:** `pod` (screen 131), from Home and Mentors.
  - **Matching stays inside the brokerage.** Pods made by the brokerage by hand are deferred; matching covers the done-when.
  - **Done when:** a Pest test has five agents join, become a pod, and each see the others' goals and shared numbers. A sixth agent with another goal keeps waiting.

### A13.3 Goals, points and feedback (Team A)

- **Daily goals and points (111):**
  - Goals are set per mode by REOS or the brokerage: CRM conversations, a CE lesson, follow-ups, one post. Each is worth points.
  - The agent sees today's points and their streak, and can share the streak.
  - Points are earned only from the agent's own activity, never from a service provider.
- **Rewards (112):**
  - The balance and its value (1,000 points = $10), used for mentor sessions, AI credits and membership.
  - **Bonus campaigns** funded by the brokerage, for example double points on CE in November.
  - Rates are set by the super admin. There is no cash value.
- **Feedback from agents (125):**
  - After a **verified closing**: the deal is closed and the other side's agent is a REOS user found on the deal's parties.
  - Each agent rates the other on communication, responsiveness and professionalism, with a comment.
  - **The average shows only after three ratings.** It is private by default and public on the agent's page only if they choose. The agent can respond or report.
  - An AI tip on what to improve.
- **Done when:** a rating appears only after three ratings.
- **Split:** A13.3a covers daily goals, points and rewards. A13.3b covers feedback from agents.
- **As built, A13.3a** (ADR 0055):
  - **Rates** (`App\Rewards\PointRates`, super admin → Points at `/admin/points`, audited):
    - Points per goal: conversations 20, a CE lesson 25, follow-ups 15, a post 15.
    - 25 more for meeting every goal, and at most 200 points a day.
    - REOS's targets for each mode. 1,000 points = $10, fixed, with no cash value.
  - **Daily goals** (`App\Rewards\DailyGoals`):
    - **What counts:** conversations (CRM calls, emails, texts and notes), CE lessons finished, to-dos done, and posts published. All of it is the agent's own activity; no goal involves a service provider.
    - **Targets:** the brokerage can set its own per mode (`daily_goal_settings`, Goals and rewards at `/organisation/rewards`, audited); 0 turns a goal off.
    - **Awarding:** a met goal earns its points once (`goal:{day}:{key}` in the points ledger).
    - **Streak:** `daily_goal_days` records each day and keeps the streak and the best streak.
    - **Nightly run:** `reos:daily-goals` (06:40 UTC) settles yesterday in each agent's time zone, so days the app wasn't opened still count.
  - **Bonus campaigns** (`App\Rewards\BonusCampaigns`, `points_campaigns`):
    - The broker picks a goal or every goal, double or triple points, dates up to three months apart, and a points budget.
    - The extra points come from the brokerage's own points account (`brokerage_points_funded`) and stop when the budget is spent.
    - End now closes the budget. Created and ended are both audited.
  - **Rewards** (`App\Rewards\Rewards`):
    - **Shows:** the balance and its value, and the last 20 points lines in words.
    - **Points pay for:** mentor sessions (A13.1a), an AI credit pack, and one month or year of membership. Membership doesn't renew on its own.
    - **How a redemption works:** it's an order with gateway `points`, so it goes through `RevenueSplit` and `MoneyPostings` like any sale. It's audited `points.redeemed`.
  - **API:** `GET /api/v1/goals/today`, `GET /api/v1/rewards`, `POST /api/v1/rewards/redeem`.
  - **Web:**
    - Goals and rewards (`/rewards`, agent sidebar), with Share my streak (the share sheet, or copy).
    - Goals and rewards for brokers (`/organisation/rewards`).
    - Points for the super admin (`/admin/points`).
  - **Phone:** `goals` (screen 111, from Home) and `rewards` (screen 112), with Share my streak.
  - **Demo:** Lone Star Realty runs Double points on CE this month, and James Miller is on a four-day streak.
  - **Done when:** Pest tests cover these cases:
    - each met goal is awarded once and the streak grows;
    - the nightly run settles days the app wasn't opened;
    - brokerage bonus points come from the brokerage, up to the budget;
    - points pay for AI credits and a month of membership, never more than the balance.
- **As built, A13.3b** (ADR 0056):
  - **Verified closing** (`App\Feedback\AgentFeedbacks`):
    - The agent's deal is closed, within the last 180 days.
    - The other side's agent is on the deal's parties with an "agent" role and an email that belongs to a REOS user with the Agent role.
    - Clients and other REOS users can't be rated, and each pair rates once per deal.
  - **Rating:** communication, responsiveness and professionalism (1–5), with an optional comment (`agent_feedback`).
  - **What the rated agent sees:**
    - Nothing until **three** ratings: just how many have come in.
    - After three: the averages, the comments with no names or deals (only the month), and a tip on the lowest area.
    - The tip comes from Claude using the comments when it's configured, otherwise REOS's tip for that area.
  - **Respond:** the agent can reply to each comment.
  - **Report:**
    - A reported rating leaves the average until REOS support looks.
    - Undo takes the report back.
    - Reports are audited `agent_feedback.reported`.
  - **Public average:** off by default (`agent_profiles.feedback_public`). When on, the public agent page shows the average and the count, and only with three ratings or more.
  - **Notification:** the rated agent gets `agent_feedback` (in-app inbox and push), which says whether feedback can be seen yet.
  - **API:** `/api/v1/feedback`: list, rate, respond, report, visibility.
  - **Web:** Feedback from agents (`/feedback`, agent sidebar).
  - **Phone:** `feedback` (screen 125), from Home.
  - **Done when:** a Pest test has two ratings show nothing, and the third show the averages, the comments and a tip.

### B13.1 Texas checks (Team B)

- **The compliance engine:** `App\Compliance`, with rules as data (code, citation, applies to, severity, fix).
  - It runs on social posts, studio videos, emails and texts to contacts, and the agent's public page.
  - Texas rules, always on:
    - the broker's name in every ad, at least half the size of the largest contact information (22 TAC §535.155);
    - a team name only with the broker's name, and without "realty", "brokerage" or similar words (§535.154);
    - the IABS and Consumer Protection Notice links on the public page and social profiles (§531.18, §531.20);
    - no misleading claims (§535.155(d)).
- **Fair housing in context:** phrases plus exceptions for place names ("Indian Wells") and room names ("family room"). When a phrase is unclear, Claude gives a second opinion (free) if it is configured.
- **Done when:** a post without the broker's name is blocked.
- **As built, B13.1** (ADR 0057):
  - **The engine** (`App\Compliance\ComplianceEngine`) has rules as data (`TexasAdRules`), each with a citation and channels:
    - `broker`: the broker's name in posts, videos and the public page;
    - `broker_size`: the broker's name in full, at half the size of the largest contact information or more, on designs and videos;
    - `team_name`;
    - `links`: IABS and CPN on the page;
    - `claims` and `title`;
    - `fair_housing`, plus `unclear` (warn only);
    - `banned`.
  - **Wording** (`App\Compliance\Wording`):
    - The phrase lists moved here from `AdvertisingPolicy`.
    - Context exceptions: Indian Wells and other "Indian …" place names, Christian Street and Christian County, "family room".
    - Unclear phrases get Claude's second opinion (free, cached for a day). Without Claude, they're a "check this" warning.
  - **Channels:**
    - **Social post checks:** each failing rule shows its citation, plus `team_name` and a `review` list.
    - **Videos:** won't render with a problem; the band check uses `VideoFrames::attributionLines`.
    - **Public page:** won't publish without the broker's name and a clean bio. It shows the brokerage when there's no MLS office, and the settings card shows the IABS and CPN links to copy into social profiles.
    - **Assistant drafts:** fixed, with the changed phrases listed (`draft.fixed`).
    - **Texts from the phone:** fixed before Messages opens.
    - **The web text dialog:** Check before sending and Fix automatically.
    - **Studio brand screen (phone):** the profile links to share.
  - **API:** `POST /api/v1/compliance/check` and `/fix`, for email and text; web `POST /compliance/check` and `/fix` return JSON.
  - **Designs and video bands:** the broker's name goes on its own line at the same size when the line is too long, so it's never cut off.
  - **Done when:** a Pest test blocks a post (check and publish) from an agent with no brokerage or sponsoring broker, and passes it once the broker's name is on it.

### B13.2 Brokerage rules (Team B)

- The brokerage's banned phrases, Fix automatically and approval (for new agents or everyone) on **every channel** the engine covers.
- **Routing:** a violation is fixed, or sent to the broker with the reason.
- **Compliance log (broker web):** every check, fix, approval and send-back, with who did it and when. It can be filtered by agent and channel and exported to CSV.
- **Done when:** a rule violation is fixed or sent to the broker.
- **As built, B13.2** (ADR 0058):
  - **Every channel:**
    - Banned phrases are checked on every channel (B13.1's engine).
    - When the brokerage approves ads first, the public page waits for the broker (`agent_pages.approval_requested_at`). The broker approves it or sends it back with a note in Advertising policy. Both decisions are audited.
  - **Routing:**
    - When only wording is flagged (`fair_housing`, `claims`, `banned`), the composer on web and phone offers **Send to my broker instead**, with a reason (`ask_broker` → `social_posts.approval_reason`).
    - The broker's queue shows the flags and the agent's reason.
    - The broker's name, team names and the post's shape must be fixed; they can't be sent to the broker.
  - **Compliance log** (`compliance_events`, `App\Compliance\ComplianceLog`):
    - **Posts:** checked, blocked, fixed, sent to broker, approved and sent back.
    - **Videos:** checked or blocked at render.
    - **The page:** checked, blocked, waiting, approved and sent back.
    - **Emails and texts:** checked or fixed, plus fixed assistant drafts.
    - **Broker web:** Compliance log at `/organisation/compliance-log`, filtered by agent, channel, action and dates, with Export CSV (audited, and no cell can start a formula).
  - **Done when:** a Pest test blocks a post with a banned phrase, fixes it, sends it to the broker with a reason, and the broker approves it. The log holds blocked, fixed, sent to broker and approved, with the broker as the actor.

### B13.3 Candidate studio (Team B)

- **A free studio for candidates:** study-streak, practice-score and exam-pass posts, with designs and captions from templates, the school's or REOS's brand, and the same checks.
- **No service advertising before the license is active:** no listings, prices, "call me to buy or sell", or brokerage solicitation. "Future agent" wording is allowed.
- **Done when:** a candidate posts a pass announcement safely.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the journeys side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - agents see only their own brokerage's mentors;
  - mentors never see a mentee's CRM contacts, and private notes are the mentor's only;
  - pod scorecards are visible only inside the pod;
  - points can't be cashed out, and are never earned from a service provider;
  - feedback is possible only after a verified closing.
- **ADRs:** 0053 mentor program and points; 0054 pods, challenges and feedback; 0055 compliance engine.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Points read as an inducement under RESPA | Earned only from the agent's own activity, never from a service provider; no cash value; ledger audited |
| A mentor gives legal or money advice | Mentors advise and the agent stays responsible; legal and money questions go to the broker (Ask the broker) |
| Leaderboards discourage new agents | Opt-in; most improved and personal-best boards |
| Unfair agent feedback | Verified closings only; three ratings before an average; respond and report; private by default |
| False positives in compliance checks | Context exceptions, Claude's second opinion, and the broker can approve with a note |

## 6. Decisions (27 November 2026, all as recommended)

1. **Points:** 1,000 = $10 inside REOS. No cash value. No expiry during the pilot. Earned only from the agent's own activity. REOS funds the points it issues; brokerages fund their bonus campaigns.
2. **Mentors:**
   - The assigned mentor is free to the agent.
   - Other brokerage mentors are priced by the brokerage, payable by card or points.
   - Points spent on a mentor are paid to the mentor by REOS.
   - Ratings show after 24 hours.
3. **Pods:** five agents, matched automatically (or made by the brokerage), opt-in. Scorecards come from activity and are shared only inside the pod.
4. **Feedback:** only after a verified closing; an average after three ratings; private by default.
5. **Compliance:** one engine with Texas rules as data and citations, applied to every outbound channel; Claude's second opinion for unclear wording; a compliance log for the broker.
6. **Candidate studio:** free; no service advertising until the license is active.

## Sources

- [eXp Mentor Program](https://us.exprealty.com/?p=1855)
- [eXpand Mentoring Program Overview](https://files.exprealty.com/PDF/eXpand-Mentoring-Program-Overview.pdf)
- [The Real Brokerage launches a free coaching program for all agents (HousingWire)](https://www.housingwire.com/articles/the-real-brokerage-launches-free-coaching-program-to-all-agents/)
- [Tom Ferry: Mastermind groups](https://www.tomferry.com/our-podcast/experience-290-mastermind-groups/)
- [RealComply (Product Hunt)](https://www.producthunt.com/products/realcomply)
- [Zillow: Navigating fair housing guardrails in LLMs](https://www.zillow.com/tech/navigating-fair-housing-guardrails-in-llms)
