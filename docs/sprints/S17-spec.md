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
- **As built, A17.3** (ADR 0084):
  - **Data:** `organisations.lead_routing` and `team_agreement`, plus `lead_routing_members`, `routed_leads` and `split_sheets`, all with row-level security.
  - **Services:**
    - `App\Teams\LeadRouting`: board, setRules, setMember, receive (first to claim by language, round robin, off-hours reply), claim (under a lock, in time), sweep (`reos:teams:leads` every minute: fallback in turn or to the team leader) and offeredTo.
    - `App\Teams\SplitSheets`: agreement, create, sign and question (a chat with the team leader).
    - Notifications: `LeadOffered` (kind `new_lead`) and `SplitSheetSent` (kind `split_sheet`).
  - **Routes:**
    - API: `/api/v1/team/routing` (GET and PUT), `routing/members/{agent}`, `POST leads`, `POST leads/{id}/claim`, `GET mine`, `GET splits`, `PUT agreement`, `POST splits`, and `splits/{id}/sign` and `question`.
    - Web: `/team` (team leader) and `/team/leads` (agent), with the same actions.
  - **Web:**
    - Team and routing (`team/routing`, team leader sidebar): rules, rotation switches, Add a lead, recent leads, the team agreement, and New split sheet.
    - Team leads (`team/leads`, agent sidebar): leads to claim, and sheets to sign or ask about.
  - **Phone:**
    - Your brokerage → Lead routing (`team/index`: rotation switches and recent leads).
    - Me → Team leads (`team/leads`): claim, sign. Pushes open it.
  - **Done when:** a Pest test has Okoye Team on first to claim within 5 minutes. A Zillow lead is offered to Priya, Maria and Tom. Priya claims it in 2 minutes and it's in her CRM, and Tom is told it was taken. A second lead nobody claims is refused at 6 minutes, and `reos:teams:leads` reassigns it in turn (audited as fallback). Other tests cover:
    - Spanish leads going to Spanish speakers, and agents out of rotation being skipped;
    - the off-hours reply, round robin and the team-leader fallback;
    - scope;
    - the split sheet from 20/30/50 ($2,400 / $3,600 / $6,000), signed or questioned.
  - **Carried forward:** bonus campaigns scoped to a team.

### B17.1 Coordinators (Team B)

- An agent shares a deal with a coordinator, with access switches for dates, documents, parties and messages.
- Access ends at closing.
- A file board, checklists, missing documents, and drafted messages sent with the agent copied.
- **Done when:** coordinator access ends at closing.
- **As built, B17.1** (ADR 0085):
  - **Data:** a `deal_shares` table: the deal, agent and coordinator, the four switches, the coordinator's checklist, and the status, with when and why access ended.
  - **Service:** `App\Coordinators\DealShares`:
    - only a connected transaction coordinator can be chosen, and only the deal's agent can share, change or remove;
    - documents and dates are on by default; a part that's off comes back as null, and parties show name and role only;
    - access ends when the deal is saved as closed or cancelled, on every coordinator read of a closed deal, and in a daily sweep (`reos:coordinators:sweep`);
    - My files puts the nearest open deadline first and counts missing documents. Ticks are saved on the share and never change the agent's deal;
    - a message goes into the coordinator's chat with the agent, and needs the messages switch;
    - every action is audited for the deal's brokerage.
  - **API:** `GET|POST /api/v1/deals/{deal}/coordinators`, `PUT|DELETE /api/v1/deal-shares/{share}`, `GET /api/v1/coordinator/files`, `GET /api/v1/coordinator/files/{share}`, `PUT …/checklist`, `POST …/messages`. A new notification, `DealShared`, tells the coordinator.
  - **Web:**
    - a Transaction coordinator section in the deal's panel (agent web screen 14): choose a coordinator, set the switches, remove; ended shares say why;
    - My files at `/provider/files` (provider screens 14–15): counts, file cards, and one file with the checklist and a drafted message. There's a new provider nav item, My files.
  - **Phone:** My files and one file (`/provider/files`), with a Me row for providers.
  - **Done when:** a Pest test has James share 1302 Live Oak St with Tina Brooks. She sees the file, the option period this week and the missing inspection report. James moves the deal to Closed; Tina's access ends at once, the file is gone from her board, and opening it gives 404. Other tests:
    - only the shared parts are visible;
    - only a connected coordinator can be chosen;
    - removal, cancelling and the sweep each end access.

### B17.2 Communities (Team B)

- Communities with posts, events, members and resources.
- Expert sessions with RSVP and a CE note.
- Invites by link and QR.
- Results for the provider.
- **Done when:** a provider runs an expert session for 18 agents.
- **As built, B17.2** (ADR 0086):
  - **Data:** `communities` (owner, name, about, invite code), `community_members` (role, via link or QR, joined and left), `community_posts`, `community_resources`, `community_events` (format, time, place or join link, capacity, price, CE course number, status) and `community_rsvps` (going, held or cancelled; attended).
  - **Services:**
    - `App\Communities\Communities`: Pro and Partner providers lead; only verified agents and providers join. Members are told about new sessions. RSVP respects the capacity. Paid sessions are Partner only and go through checkout, and the RSVP is held until paid. The host saves attendance. Results.
    - `PostCheck`: advertising wording, no pay for referrals, no contacts or links.
  - **API:** `/api/v1/communities`, `/api/v1/community` (owner: settings, code, resources, sessions, roster, attendance, results), `/api/v1/communities/join/{code}`, `/api/v1/communities/{community}` (view, leave, posts), `/api/v1/community-posts/{post}` (remove, pin), `/api/v1/community-sessions/{session}/rsvp`.
  - **Web:**
    - Community at `/community` (provider screens 16–17): the invite link with a QR code (`/community/qr.svg`), schedule a session, the roster with attendance, posts, resources and results;
    - Results at `/community/results` (screen 18);
    - Communities at `/communities` for agents, and a community at `/communities/{id}`;
    - the invite page at `/join/community/{code}`;
    - provider nav items Community and Results (were planned), and an agent nav item Communities.
  - **Phone:** Communities, a community (sessions with RSVP and the CE note, posts, resources), and the invite screen. There's a Me row for agents and providers.
  - **Done when:** a Pest test has Maya Chen (Pro):
    - invite 18 Lone Star Realty agents, 6 by QR code and 12 by link;
    - schedule "Reading a loan estimate with your buyers"; all 18 are told and RSVP;
    - tick all 18 as attended after the session.
    Results show 18 members, 18 RSVPs, 18 came and a 100% show rate. Other tests:
    - only verified people join, and a new link turns the old one off;
    - Listed providers can't lead;
    - posts with referral fees, phone numbers or fair housing wording are turned down;
    - paid sessions need Partner, are capped, and go from held to going when paid.

### B17.3 QuickBooks (Team B)

- QuickBooks Online for brokerages: split sheets and payouts posted as journal entries, with the accounts mapped once.
- **Done when:** a split sheet posts to QuickBooks.
- **As built, B17.3** (ADR 0087):
  - **Data:**
    - `accounting_connections`: one company per brokerage, tokens encrypted, the mapped accounts;
    - `accounting_exports`: one row per split sheet or payout, posted or failed, with the QuickBooks id. Both tables have RLS.
  - **Services:**
    - `App\Accounting\QuickBooksExports`: connect, with the state tied to the broker; map the six accounts from the company's chart; post a signed sheet at signing or from Export now; post payouts for the brokerage's own sales; never post twice; failures are kept and tried again; daily `reos:accounting:export`;
    - `JournalEntry`: balanced, in cents, with QuickBooks's body;
    - `SimulatedQuickBooks` and `IntuitQuickBooks`: OAuth, token refresh, chart of accounts, journal entries.
  - **API:** `GET|DELETE /api/v1/organisation/accounting`, `POST …/connect`, `PUT …/accounts`, `POST …/export`.
  - **Web:** QuickBooks at `/organisation/accounting`, a broker nav item beside Integrations:
    - connect (and "Simulated" in development);
    - map the accounts;
    - what's waiting, with Export now;
    - posted entries, and why any didn't post.
    Intuit returns to `/organisation/accounting/quickbooks/callback`. There's no phone screen: this is brokerage admin, like Integrations.
  - **Done when:** a Pest test has Linda Park connect Lone Star Realty's company (simulator) and map the accounts. Priya signs her 1302 Live Oak St split sheet, and the journal entry posts at once:
    - $12,750 debited to clearing;
    - $2,550 to the brokerage, $3,825 to the team and $6,375 to Priya;
    - the sheet keeps the QuickBooks id;
    - Export now doesn't post it twice.
    Other tests:
    - a sheet signed before connecting waits for the mapping, then posts;
    - another brokerage's broker can't finish someone else's sign-in;
    - the Intuit client refreshes the token and posts the journal entry.

## 7. Sprint 17 wrap-up

- **Built:**
  - points sessions paid properly (A17.1, #166);
  - sponsored AI help in offers, and agents open to a move (A17.2, #167);
  - team lead routing and split sheets (A17.3, #168);
  - coordinators by part until closing (B17.1, #169);
  - provider communities and expert sessions (B17.2);
  - QuickBooks Online journal entries (B17.3).
- **Definition of done:**
  - every endpoint is in `/api/v1` with Pest feature tests, and the api-client is regenerated;
  - English and Spanish are complete (GLOSSARY: Expediente, Sesión con expertos, Asiento contable);
  - every new shared component has stories passing axe;
  - ADRs 0082–0087 and every As built are written.
- **Security tests:**
  - coordinators see only shared parts, and nothing after closing or removal (B17.1);
  - agents open to a move are never shown to their own brokerage (A17.2);
  - a team leader routes only their team (A17.3);
  - only verified agents and providers join communities (B17.2);
  - a QuickBooks sign-in can't be finished by another brokerage (B17.3).
- **Carried forward:**
  - bonus campaigns scoped to a team (campaigns stay brokerage-wide);
  - billing brokerages for points redeemed with their mentors (the ledger records it; invoicing comes with B18 billing);
  - coordinators who aren't on REOS (email-only access);
  - sharing a deal with a coordinator from the phone (web for now);
  - community points and leaderboards;
  - universal links for invite QR codes in store builds;
  - Intuit's app assessment for production QuickBooks keys (client).

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
