# Sprint 17 spec: Mentors, recruiting, teams · coordinators and communities

- **Dates:** plan Jul 19 – Jul 30, 2027; built from 28 November 2026.
- **Phase:** 4, broker, providers and association (sprints 15–18).
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 17: A17.1–A17.3, B17.1–B17.3).
  - The Broker and Team Leader Journey: mentor program (screens 21–23), recruiting (24–27), team leader (28–30).
  - The Service Provider Journey: transaction coordinators (screens 14–15), community, events and invites (16–18), plans.
  - The Instructor and Mentor Journey: the mentor workspace and payouts.
  - The Web App Journey: share with a coordinator, coordinator access ending at closing, community admin.
  - The S16 wrap-up.
- **Benchmark first** (sprint plan):
  - Open to Close and dotloop, for coordinator flows;
  - Circle and Skool, for communities;
  - Follow Up Boss, for lead routing (the team leader's tool today).

## 0. What earlier sprints already delivered (scope adjusted)

| Sub-sprint | Already built | Sprint 17 builds |
| --- | --- | --- |
| A17.1 Mentor program | Program admin, prices, points, matching and assignment (A13.1a); the mentor workspace (A13.1b); booking with points (A13.1a); weekly payouts through Stripe Connect (B5.2b) | **Points-funded sessions paid for properly**: the brokerage's points account funds the mentor's share. The mentor's share is paid **only after the session happens**: held until the session is completed, then released. If the session is cancelled in time or the mentor doesn't show, the points go back. **Mentor earnings** on the web and the phone |
| A17.2 Recruiting | Candidate pool and offers, with contacts shared on acceptance (A10.3b); sponsor credit pools for members (B5.3) | **Sponsored AI help in an offer**: a pool and a cap for each candidate, which ends when they pass or pick another sponsor. **Licensed agents open to a move**: agents who turned on the recruiting switch, with Invite to talk (a private chat). Their current broker is never told |
| A17.3 Teams and campaigns | Teams inside brokerages (A15.3); bonus point campaigns (A13.3a); leads from pages and open houses (B14) | **Lead routing**: rules by source and language, round robin or first to claim, claim timers, fallback and off hours. **Claim on the phone.** **Split sheets** from the team agreement, signed by the agent. Campaigns get a team scope |
| B17.1 Coordinators | Deals and deadlines (B9); the coordinator provider category (B15.1); connections and chat (B16) | **Share a deal with a coordinator** using access switches (dates, documents, parties, messages). Access **ends at closing** or when removed. **My files** board, checklist, missing documents, and drafted messages with the agent copied |
| B17.2 Communities | Provider profiles, plans and billing (B15); connections and reviews (B16) | **Communities**: posts, events, members, resources. **Expert sessions** (online or in person, free or paid on Partner), with RSVP. **Invites** by link and QR. Results for providers |
| B17.3 QuickBooks | The register entry (`quickbooks-online`) | **QuickBooks Online export** of split sheets and payouts for brokerages (OAuth, a simulator in development) |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Stripe Connect platform account | A17.1 payouts | Client | The Simulated Connect provider (B5.2b) |
| Intuit developer app (QuickBooks Online) | B17.3 | Client | The QuickBooks simulator |
| RESPA counsel review | B17.1, B17.2 | Client | Providers stay behind `provider_network`. Communities and sessions are information only, with no fee per referral |

## 2. Market leaders: what they do, where they fall short, what we do

| Area | Leaders | Where they fall short | REOS |
| --- | --- | --- | --- |
| Lead routing | Follow Up Boss: first to claim by push, a claim window of at most 30 minutes, then up to two fallback groups and the owner | A separate tool from training and compliance. Claim works only from its app; off hours is a rule you build yourself | Routing lives with the team (sources, language, round robin or claim, timer, fallback, off hours). The claimed lead lands in the agent's CRM. Every hand-off is logged for the team leader |
| Coordinators | Open to Close: an agent portal; coordinators run checklists and intake forms | The agent shares a whole file. Access isn't tied to closing, and the agent's broker can't see what the coordinator did | Access switches by part of the deal. Access ends at closing automatically. Every coordinator action is logged for the agent and broker |
| Communities | Skool: one feed, points and leaderboards, cheap; Circle: spaces, events, paid tiers, more branding | Generic: no licence checks, no CE rules, no advertising checks. Fees on every payment | Verified members only (agents and providers in the network). Posts pass REOS's advertising checks. Expert sessions say clearly whether CE applies. Paid sessions only on Partner, a flat fee |
| Recruiting | Brokerkit: recruiting CRM from MLS data | Agents are contacted whether or not they want it, and their broker may hear | Only agents who turned the switch on. First name until they talk. Their broker is never told |

## 3. Sub-sprints

### A17.1 Mentor program (Team A)

- Points-funded sessions are paid from the brokerage's points account: the mentor's share is a payout line funded by the brokerage, never by REOS.
- The mentor's share is held until the session is completed, then released. If the session is cancelled in time or the mentor doesn't show, the points go back.
- Mentor earnings, on the web and the phone.
- **Done when:** a mentor is paid for a points session.
- **As built, A17.1** (ADR 0082):
  - **Data:** a new payout line status, `held`. No migration is needed (the status is a string).
  - **Services:**
    - `Points::fundSession` and `unfundSession`: the brokerage's points account funds each points session, once, and gets it back on a refund.
    - `SessionBookings`: a points session's payout lines start held. `complete()` releases them to pending, and a refund cancels them.
    - `PayoutStatements::person` adds `held`; `MentorProgram::program` adds this month's points sessions and what the brokerage funded.
  - **Web:** Earnings show "Plus $67.50 for points sessions, paid once they happen" and the "After the session" status. Mentor program shows the points sessions and what the brokerage funded this month.
  - **Phone:** Earnings show the held amount.
  - **Done when:** a Pest test has Priya book Dana Brooks for 60 minutes with 7,500 points:
    - Lone Star Realty's points account funds it (−7,500);
    - Dana's $67.50 share is held, and the weekly payout skips it;
    - Dana teaches and the session completes; her share is pending, and the next payout sends her $67.50.

    Another test: when Dana doesn't show, Priya's points come back, the brokerage's funding is returned, and Dana's share is cancelled.

### A17.2 Recruiting (Team A)

- Offers can include sponsored AI help, with a per-candidate cap from the brokerage's pool.
- Sponsorship ends when the candidate passes or chooses another sponsor.
- Licensed agents open to a move, with Invite to talk.
- **Done when:** a candidate accepts an offer with sponsored help.
- **As built, A17.2** (ADR 0083):
  - **Data:** `broker_offers.ai_credits`, `candidate_sponsorships` and `recruiting_invites`. They span a brokerage and a person outside it, so the brokerage is `brokerage_id`, checked in the app.
  - **Services:**
    - `App\Recruiting\SponsoredHelp`: accepted (from `BrokerOffers::accept`), payerFor, used, board and setCap. It ends when the candidate passes or joins another brokerage. `AiCredits::candidatePayer` adds `offer` after the month's credits.
    - `App\Recruiting\OpenAgents`: list (opted-in, licensed in the brokerage's states, not ours) and invite (a private chat once every 90 days, audited for the inviting brokerage only). `Relationships` counts invites.
  - **Routes:**
    - Web (admin): `PUT /recruiting/sponsorships/{id}` and `POST /recruiting/agents/{id}/invite`, and `sponsored` and `agents` on `/recruiting`.
    - API: `GET /api/v1/organisation/recruiting/agents`, `POST …/agents/{id}/invite`, `GET …/sponsorships` and `PUT …/sponsorships/{id}`.
  - **Web:** Recruiting adds Sponsored AI help (pool, use, status, cap) and Licensed agents open to a move (Invite to talk). The offer dialog adds sponsored AI credits. The candidate's offer card says "Includes sponsored AI study help".
  - **Phone:** Your brokerage → Recruiting (agents open to a move with Invite to talk, and sponsored help). The candidate's offers show the AI help chip.
  - **Done when:** a Pest test has Lone Star fund its pool (1,000) and offer Ana 200 credits. She sees only that help is sponsored and accepts. Beyond her month's credits, an answer draws 5 credits from Lone Star's pool (995) and her use (5). A lower cap stops it, and once she passes the sponsorship ends. Other tests cover:
    - ending when she joins another brokerage;
    - only opted-in agents, never their own brokerage;
    - an invite opening a chat, audited for Lone Star only, with nothing sent to their broker.

### A17.3 Teams and campaigns (Team A)

- Lead routing rules: sources, language, round robin or first to claim, the claim time, the fallback and off hours.
- Claim from a push or the app; a claimed lead goes to the agent's CRM.
- Split sheets from the team agreement, signed in the app.
- Bonus campaigns can be scoped to a team.
- **Done when:** a lead is claimed in time or reassigned.

### B17.1 Coordinators (Team B)

- An agent shares a deal with a coordinator, with access switches for dates, documents, parties and messages.
- Access ends at closing.
- A file board, checklists, missing documents, and drafted messages sent with the agent copied.
- **Done when:** coordinator access ends at closing.

### B17.2 Communities (Team B)

- Communities with posts, events, members and resources.
- Expert sessions with RSVP and a CE note.
- Invites by link and QR.
- Results for the provider.
- **Done when:** a provider runs an expert session for 18 agents.

### B17.3 QuickBooks (Team B)

- QuickBooks Online for brokerages: split sheets and payouts posted as journal entries, with the accounts mapped once.
- **Done when:** a split sheet posts to QuickBooks.

## 4. Definition of done (whole sprint)

- Every endpoint is in `/api/v1` with Pest feature tests, and the api-client is regenerated.
- Every string is in English and Spanish.
- Every new shared component has a story passing axe.
- **Security tests:**
  - a coordinator sees only the parts of a deal that were shared, and nothing after closing or removal;
  - an agent open to a move is never shown to their own brokerage, and their broker is never told;
  - a team leader routes only their team's leads;
  - community members are verified agents and providers only.
- ADRs and the "As built" sections are written.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Points sessions paid twice, or paid for sessions that never happened | Lines are held until the session is completed, are written once per booking, and are reversed on a refund |
| A coordinator keeps access after closing | Access ends in the same transaction that closes the deal, with a daily sweep as a backstop, and is tested |
| Lead routing spams agents | Claim windows from 1 to 30 minutes, at most two fallbacks, and quiet hours respected except for claims |
| Communities used for referral fees (RESPA) | No payment tied to referrals; expert sessions are information only; paid sessions are a flat price on Partner |

## 6. Decisions (recommended)

1. **Who funds a points session:** the brokerage's points account. REOS bills the brokerage for points redeemed with its mentors, at the points rate.
2. **When the mentor is paid:** once the session is completed, in the next weekly payout. If it's cancelled in time or the mentor doesn't show, the points go back.
3. **Lead routing defaults:** first to claim with a 5-minute claim time, then round robin, then the team leader. Off hours are 8 pm to 8 am Central, when an AI reply names the team and offers a call.
4. **Coordinator access:** documents and dates on by default, parties and messages off. It always ends at closing.
5. **Communities:** led by Pro and Partner providers. Free events on Pro; paid expert sessions on Partner only.

## Sources

- [Follow Up Boss: how First to Claim lead assignment works](https://help.followupboss.com/hc/en-us/articles/360014656193)
- [Open to Close: agent portal view](https://intercom.help/open-to-close/en/articles/6041183-agent-portal-view)
- [Skool vs Circle (2026)](https://www.learningrevolution.net/skool-vs-circle/)
