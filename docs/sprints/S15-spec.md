# Sprint 15 spec: Broker setup and approvals · provider onboarding

- **Dates:** 21 June – 2 July 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 28 November 2026 under the standing approval to build the next sprints in order ("Proceed to next"). The decisions in section 6 are all as recommended.
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 15: A15.1–A15.3, B15.1–B15.3; Phase 4 opens).
  - The Broker and Team Leader Journey: setting up the brokerage (screens 1–6), approvals and escalations (screens 7–12), people and permissions (screens 31–36).
  - The Service Provider Journey: joining (screens 1–7).
  - The Web App Journey: broker web screens 32–41 and the admin's provider categories (screen 68).
  - The S14 wrap-up.
- **Benchmark first** (sprint plan): Brokermint, Lone Wolf Back Office and SkySlope Broker for brokers; Zillow co-marketing and Homebot for providers.
- **Gate (end of Phase 4, sprint 18):** a pilot brokerage runs a full week on REOS with its agents, mentors, coordinators, providers, school and association active.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 15 | Already in place | Sprint 15 now does |
| --- | --- | --- |
| A15.1 Brokerage setup | "Start your brokerage" on reos.com with a plan and checkout, which creates the tenant (A6.1b). TREC's licence holders, with sponsoring broker, imported nightly (B10.3a). Invitations and seats (A4.3). Brokerage integrations: allow or hide each tool, and the brokerage's own keys (B8.2). | **TREC broker verification** before a brokerage exists: the broker licence number is matched to TREC's records (name, entity, status, sponsored agents), and only a designated broker can create the brokerage. **Roster from TREC sponsorships**: every agent TREC lists under the brokerage, marked "on REOS" or "to invite", with Invite all (14-day invites with one reminder) and team leaders confirmed. **Who pays** (agents, brokerage, or split) and pooled AI credits per seat. **A setup checklist** that takes a broker from licence to live in 15 minutes |
| A15.2 Policies | The policy manual with versions, topics and TREC conflicts (A12.1). The advertising policy with banned phrases, approval rules and the brand kit (B12.1). Compliance log (B13.2). | **People and permissions**: designated broker, office admin and team leader, each with duties the designated broker switches on (approve posts, answer escalations, manage policies, sponsor agents). **Conflict check on every edit**: a brokerage rule typed or changed is checked against the TREC rules at once, with the TREC source. The policy screens get one home: Brokerage › Policies |
| A15.3 Approvals and escalations | Post approvals (B12.1), virtual broker escalations with answers added to the FAQ (A12.1), coordinator requests (B13). Each has its own list. | **One approvals queue** in a split view: posts, coordinator requests and escalated questions, oldest first, with the item on the right and the checks beside it. **Office admin fallback**: an item waiting past the brokerage's limit goes to the office admin, and both are told. **Answers become knowledge**, with the broker's choice of FAQ or manual note. **The broker's phone screens** (broker journey screens 7–12): Today's queue, review a post, answer a question |
| B15.1 Categories | Provider categories with licence check, verification type, profile fields, Pro areas and monthly prices per tier and state, managed by the super admin with no code (A6.1, screen 68). | **Categories drive onboarding**: the category grid, the licence step and the profile fields come from the admin's settings. **Request a new category** from onboarding into the admin's queue. **Field types** (text, list, yes/no) for profile fields |
| B15.2 Provider onboarding | Provider licences checked against TREC inspector and TDI data, NMLS by a reviewer (B10.3a). The Provider role, provider businesses as tenants, checkout (B5). | **Joining in 10 minutes** (provider screens 1–6, web and phone): welcome, category, licence (NMLS, TREC, TDI, or business details and ID for unlicensed categories), **service areas** (cities, ZIP codes and a radius, with the count of active agents and of providers in the category), **plan** bought on reos.com, **network rules** accepted and versioned. **Monthly re-checks**: an expired licence hides the profile |
| B15.3 Provider profile | Nothing yet. | **Profile builder** (provider screen 7): photo and logo, bio with "Write it for me" in English and Spanish, services, specialities, response-time goal, free resources, expert sessions. No phone or email on the profile; the text is checked by the compliance engine. **The agent's view**: a verified profile with the licence badge, areas and services |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| RESPA counsel review of provider plans and network rules | B15.2, B15.3 | Client | Flat monthly fees only, no payment per referral or closed loan, and the rules shown with "to be reviewed by counsel before launch". Providers stay off for production tenants until the review is signed off (a feature flag) |
| Mapbox access token | B15.2 (service areas on a map) | Mayura | Areas are picked as cities, ZIP codes and a radius with counts; the map appears when the token is set |
| A pilot brokerage's TREC licence and roster | A15.1 | Client | The demo brokerage (Lone Star Realty) uses records from TREC's public file |
| ID check vendor for unlicensed provider categories | B15.2 | Client | A reviewer checks the business details and an uploaded ID (no vendor this sprint) |

## 2. Market leaders: what they do, where they fall short, what we do

| Area | Leaders: what to adopt | Where they fall short | What REOS does better |
| --- | --- | --- | --- |
| Brokerage setup and roster | **Brokermint** (now BoldTrail BackOffice) runs agent onboarding from checklists the broker builds: tasks with a responsible role and documents, and the agent stays in an onboarding-only view until the admin finishes it. **Lone Wolf Back Office** sets up agents, plans and commission rules for accounting. | Setup is days of admin work: agents are typed in or imported from a spreadsheet, nothing is checked against the regulator, and a checklist can't be changed once it's in use. Nobody proves the person setting up is the real broker | **Verified against TREC first**: only the designated broker creates the brokerage. **The roster comes from TREC's sponsorship records**: every sponsored agent is found, marked "on REOS" or "to invite", and invited in one step. **Who pays and pooled credits** chosen in the same flow. **Live in 15 minutes** |
| Policies and permissions | Brokerage manuals live in PDFs and intranets; back-office tools have role permissions for money. | Policy text is never checked against the law; a manual that contradicts TREC is found in an audit or a lawsuit. Office staff permissions are all-or-nothing | **Every brokerage rule checked against TREC as it's written**, with the TREC source, and the TREC rule wins in every answer. **Duties per person** (approve posts, answer questions, manage policies, sponsor agents) set by the designated broker |
| Approvals and escalations | **SkySlope**: checklists per transaction, documents routed to a central inbox, and Smart Assist's AI pre-approving or flagging documents in a step-by-step audit. | Each kind of approval has its own inbox (files in one tool, posts in another, questions in email). Nothing happens when the broker is away. Answers to agents' questions are lost in email | **One queue** for posts, coordinator requests and questions, with the checks shown beside each item. **Fallback to the office admin** when an item waits too long. **Answers become the brokerage's knowledge**, so the next agent gets the answer at once. On the web and on the broker's phone |
| Provider network | **Zillow co-marketing**: a lender pays to appear next to a Premier Agent (up to five lenders per agent, shown by relative spend, active NMLS required), and leads go straight to the lender. **Homebot** has lenders sponsor agents' client reports. | Agents pay or get paid for the placement, which is the RESPA risk at the heart of current lawsuits. Lenders reach clients directly. Inspectors, title, coordinators and other trades have no place | **Flat monthly fees for listing and advertising only**, never per referral or closed loan, with the network rules accepted and versioned. **Every category the admin creates** (lenders, inspectors, title, coordinators, photographers, and more), each with its own licence check. **Verified profiles only**, re-checked monthly. **Agents stay in control**: chat first, contacts shared only when both agree, clients only with their consent |

## 3. Sub-sprints

### A15.1a TREC-verified brokerage setup (Team A)

- **Verify your brokerage (screen 2):** the broker licence number is matched to TREC's records: the name, the brokerage entity, the broker licence status and the number of sponsored agents. The person picks their role (designated broker, office admin or team leader).
  - Only a designated broker with an active broker licence can create the brokerage, and the name on the licence must match the account (or go to REOS review).
  - An office admin or team leader is invited by the designated broker instead.
- **Start your brokerage** (A6.1b) now starts with this check. The checkout comes after.
- **Setup checklist** (web and phone): verify, bring in agents, seats and plan, the policy manual, the advertising policy and the mentor program, each with its state and a link.
- **Done when:** a broker verifies their TREC licence, creates the brokerage and sees the checklist, within 15 minutes on the Simulator.
- **As built, A15.1a** (ADR 0069):
  - **Data:** `broker_verifications` (the licence, the brokerage, its kind, the sponsored agents, the role; used once, valid a day), `organisations.trec_number` (unique) and `tenant_signups.broker_verification_id`.
  - **Services:**
    - `App\Brokerage\BrokerVerifications`:
      - lookup: the BRK record, the name match, and the brokerages (entities whose related licence is the broker, plus their own licence) with sponsored counts and whether each is on REOS;
      - confirm: a designated broker gets a verification; other roles get none;
      - current.
    - `TenantSignups::start` requires a fresh, unused verification of the owner's for a brokerage not yet on REOS, with Texas among the states. `complete` stores the TREC number.
    - `App\Brokerage\BrokerageSetup`: six steps (verify, agents, seats, manual, advertising, mentors).
  - **Routes:**
    - Web: `POST start/brokerage/verify/lookup|confirm` (20 a minute).
    - API: `POST /api/v1/brokerage/verify/lookup|confirm` and `GET /api/v1/organisation/setup`.
  - **Web:**
    - "Start your brokerage" shows Verify your brokerage first (`components/brokerage/verify-brokerage`), then the plan with the brokerage name filled in from TREC.
    - The broker's dashboard shows the setup checklist (`setup-checklist`) until it's complete.
  - **Demo:** the Simulator's TREC file has Bayou City Homes LLC (designated broker Grace Hill, 9033333) with Maria Lopez and Omar Haddad. Lone Star Realty stands for 9012345.
  - **Done when:** a Pest test has Grace Hill look up 9033333, find Bayou City Homes LLC with two sponsored agents, confirm as designated broker, buy the plan, and get a brokerage with TREC number 0600123 and its checklist (verified done, agents to do). A second claim is refused. Agent licences, other people's licences and other brokerages are refused, and office admins are sent to their broker.

### A15.1b Roster, seats and who pays (Team A)

- **Bring in your agents (screen 3):** every agent TREC lists under the brokerage's licence: already on REOS (linked to the brokerage when they accept), to invite, and teams found in the CRM. Invite all (email and text with the brokerage's link) or one at a time. Invitations expire in 14 days with one reminder.
- **Seats and plan (screen 4):** who pays (agents, the brokerage, or split), pooled AI credits per seat (monthly, no roll-over), the candidate sponsorship pool and team seats. Changes are confirmed on reos.com; nothing is sold in the app.
- **Done when:** a broker invites every sponsored agent in one step and chooses who pays.
- **As built, A15.1b** (ADR 0070):
  - **Data:** `brokerage_roster_contacts` (an email or phone per TREC licence number, with row-level security), and the payer `split` in tenant limits.
  - **Services:**
    - `App\Brokerage\Roster`:
      - for: sponsored agents (SALE and BRK, active, sponsored by the brokerage's TREC number) with state and counts, never showing an agent's account details;
      - contacts: "licence, email or phone" lines, refusing agents not on the roster;
      - inviteAll: through `Invitations::invite`, in batches of 500.
    - `App\Brokerage\BrokerageSeats`: show (seats used and invited, payer, credits per seat, pooled, the pool, the candidate pool, teams) and choose (audited).
    - `App\Brokerage\BrokerageAccess`: the broker role at the brokerage itself.
  - **Routes:**
    - API: `GET /api/v1/organisation/roster`, `POST /api/v1/organisation/roster/contacts`, `POST /api/v1/organisation/roster/invite`, and `GET` and `PUT /api/v1/organisation/seats`.
    - Web: the same actions under `organisation/`.
  - **Web:**
    - Agents (screen 32) shows Bring in your agents (`trec-roster`) for a verified brokerage.
    - Brokerage settings (screen 41) shows Seats and who pays (`seats-card`).
  - **Phone:** Me → Your brokerage (`brokerage/index`: the checklist, roster and seats), `brokerage/roster` (Invite all), `brokerage/seats`, and `brokerage/verify` for anyone setting one up.
  - **Done when:** a Pest test has Grace's brokerage find Maria Lopez (needs a contact) and Omar Haddad (on REOS) on TREC. Maria's email is added, and James's line is refused because Bayou City Homes doesn't sponsor him. One Invite all sends both invitations, and a second sends none. When Omar accepts he's in the brokerage. Grace then chooses split with credits not pooled, which completes the seats step.

### A15.2 People, permissions and policies (Team A)

- **People and permissions:** designated broker, office admin and team leader. Each office admin or team leader has duties the designated broker turns on: approve posts, answer escalations, manage policies, sponsor and release agents. Only the designated broker changes roles and policies.
- **Policies:** Brokerage › Policies brings the manual (A12.1) and the advertising policy (B12.1) together. A brokerage rule written or changed in REOS (an advertising rule, a manual note) is checked against the TREC rules at once and a conflict is flagged with its TREC source.
- **Done when:** a conflicting rule is flagged with its TREC source.
- **As built, A15.2** (ADR 0071):
  - **Data:** `brokerage_staff` (title and duties per person, with row-level security; the first broker of each existing brokerage is backfilled as designated), `policy_passages.conflict`, and passages of kind `policy`.
  - **Services:**
    - `App\Brokerage\BrokerageStaff`: list (designated, office admins and team leaders with duties), setDuties (designated broker only), makeDesignated (at sign-up), can, allow and designatedOnly.
    - `App\Brokerage\BrokerageRules`: view, check, add, update and remove/restore (all audited).
    - `TexasConflicts::first`, used by `PolicyKnowledge::addFaq` too.
    - Policy search includes brokerage rules, and `withTexasFirst` puts TREC first for a conflicting rule or FAQ answer.
  - **Enforcement:** the advertising policy and approvals, the virtual broker's manual, settings, FAQ and replies, roster contacts and Invite all, invitations, and seats.
  - **Routes:**
    - API: `GET /api/v1/organisation/staff`, `PUT /api/v1/organisation/staff/{member}`, `GET /api/v1/organisation/policies`, `POST /api/v1/organisation/policies/check`, `POST /api/v1/organisation/policies/rules`, `PUT` and `DELETE /api/v1/organisation/policies/rules/{rule}`, and `POST /api/v1/organisation/policies/rules/{rule}/restore`.
    - Web: the same actions, and `/organisation/policies`.
  - **Web:** Brokerage › Policies (the manual, advertising and rules with the live TREC check, `brokerage-rules`). Brokerage settings shows People and permissions (`staff-permissions`). Policies in the broker sidebar now opens this page.
  - **Phone:** Your brokerage → Policies (`brokerage/policies`: the manual and advertising at a glance, the rules with conflicts, add a rule with the live check).
  - **Done when:** a Pest test checks "Agents may accept referral fees directly from title companies.", which is flagged as 22 TAC §535.3 with its source. Saved, the rule keeps the flag; rewritten, the flag goes. An agent's question about earnest money, with a conflicting rule in place, gets TREC's rule first. An office admin without duties is refused policies, the manual, advertising, Invite all, seats and duties; once given "manage policies" they can add rules, but still can't change seats.

### A15.3 Approvals and escalations (Team A)

- **One queue** (web and phone): posts waiting for approval, coordinator requests and escalated questions, oldest first, in a split view with the checks beside each item.
- **Office admin fallback:** an item waiting past the brokerage's limit (default 4 working hours) goes to the office admins with the duty, and the broker is told.
- **Answers become knowledge:** an answer can be added to the FAQ or as a manual note, with the topic.
- **Phone:** the broker's Approvals tab, Review a post, and Answer an agent (broker journey screens 7–12).
- **Done when:** a broker clears the queue on the web and on the phone.
- **As built, A15.3** (ADR 0072):
  - **Data:** `approval_fallbacks` (each item passed to the office admins once, with row-level security) and `organisations.approval_settings` (the limit in working hours).
  - **Services:**
    - `App\Brokerage\ApprovalsQueue`: for (the queue by duty and team, oldest first), act (approve, return with a note, or answer with "add to knowledge"), and setFallbackHours (designated broker only, audited).
    - `App\Brokerage\WorkingHours`: 8 AM to 6 PM Central, Monday to Friday.
    - `App\Brokerage\ApprovalFallbacks` with `reos:approvals:fallback` every 15 minutes, sending the `ApprovalOverdue` notification (kind `approval_overdue`, in the organization group).
    - `Memberships::holdersInTree`, so team agents' posts and pages reach the brokerage.
  - **Routes:**
    - API: `GET /api/v1/organisation/approvals`, `POST /api/v1/organisation/approvals/{kind}/{item}`, and `PUT /api/v1/organisation/approvals/settings`.
    - Web: the same actions, and `/organisation/approvals`.
  - **Web:** Brokerage › Approvals (`approvals-queue`): filters, the list and the item side by side, the overdue count, and the fallback setting. Approvals is now in the broker sidebar.
  - **Phone:** Your brokerage → Approvals (`brokerage/approvals`) and Review (`brokerage/review`: a post with its checks, a page, or a question with the AI's answer).
  - **Not yet:** coordinator requests, which arrive with transaction coordinators. Answers go to the FAQ; a manual note is written as a brokerage rule on Policies.
  - **Done when:** a Pest test has the designated broker clear a post, a team agent's page and an escalated question on the web, and a post through the phone's API, leaving the queue empty. Office admins see only their duties, a team leader only their team, and a post sent at noon on Friday is overdue at 9 AM on Monday (7 working hours). The fallback tells the designated broker and the office admin with the duty once. Jest covers the phone's queue calls.

### B15.1 Categories in onboarding (Team B)

- The provider category grid, licence step and profile fields come from the admin's settings (A6.1).
- Request a new category from onboarding into the admin's queue (the category is "requested", with a count).
- Profile field types: text, list and yes/no.
- **Done when:** the admin adds a new category without code and a provider can join it.
- **As built, B15.1** (ADR 0073):
  - **Data:**
    - `provider_categories.licence_kind`: nmls, trec_inspector, tdi, or empty for business details and an ID, set from the old text.
    - Typed `profile_fields` (`{key, label, type, options}`), converted from plain labels.
    - `provider_category_requests`: one per person, with a note.
    - `provider_profiles`: the chosen category and its answers.
  - **Services:**
    - `App\ProviderNetwork\ProviderJoining`: categories (the grid with prices in the state), request, profile, choose and answer (checked by type).
    - `ProviderCategories::fields`: keys from labels.
  - **Routes:**
    - API: `GET /api/v1/provider/categories`, `POST /api/v1/provider/categories/requests`, `GET /api/v1/provider/profile`, `PUT /api/v1/provider/profile/category` and `PUT /api/v1/provider/profile/fields`.
    - Web: the same actions, and `/provider/join`.
  - **Web:**
    - Screen 68: the category dialog edits fields as rows (label, type, values) and sets the licence step. The side panel shows each field's type and what requesting providers wrote, and the providers count is real.
    - Join the provider network (`category-step`): the grid, the fields and Request a category.
  - **Phone:** Me → Join the provider network (`provider/join`), with the same steps.
  - **Done when:** a Pest test has the super admin add "Home stager" with a text, list and yes/no field and Texas prices. Dana Brooks sees it with its prices and licence step, chooses it, and is refused a value outside the list, a word for yes/no and an empty text before her answers save. Requests count once per person and show in the admin's queue with the note.

### B15.2 Provider onboarding (Team B)

- **Joining (provider screens 1–6, web and phone):** welcome, category, licence (NMLS, TREC, TDI; or business details and an ID for unlicensed categories), service areas, plan, network rules.
- **Service areas:** cities, ZIP codes and a radius; the number of active agents in the area and of providers in the category; the plan sets how many areas.
- **Plan:** Listed, Pro or Partner at the admin's flat monthly price for the category and state, bought on reos.com.
- **Network rules:** five rules (flat fee only, no payments or gifts to agents for business, client contact only with consent, advertising follows Texas and licence rules, no spam), accepted with the date and version, asked again when they change.
- **Monthly re-checks:** an expired or inactive licence hides the profile until it's fixed.
- **Done when:** a loan officer goes live in 10 minutes.

### B15.3 Provider profile (Team B)

- **Build your profile (provider screen 7):** photo and logo, a short bio with "Write it for me" and a Spanish version, services, specialities, response-time goal, free resources, expert sessions offered. No phone or email on the profile. The text is checked by the compliance engine before it's published.
- **The agent's view:** the verified profile with the licence badge and when it was checked, the areas and the services.
- **Done when:** an agent sees a verified profile.

## 4. Definition of done (whole sprint)

- Every endpoint is in `/api/v1` with Pest feature tests, and `packages/api-client` is regenerated.
- Every string is in English and Spanish.
- Every shared component has a Storybook story that passes the accessibility check.
- **Security tests:**
  - only a verified designated broker creates a brokerage or changes roles and policies;
  - a team leader sees only their team;
  - providers see only agents in their areas who are in the network, never contacts, clients or deals;
  - an unverified or expired provider is never shown to agents.
- ADRs and the "As built" sections are written.

## 5. Risks

| Risk | Plan |
| --- | --- |
| Someone claims a brokerage that isn't theirs | Verified against TREC; only the designated broker on record creates it; a name mismatch goes to REOS review |
| The roster import invites people who left | Invitations go only to agents TREC lists as sponsored today, expire in 14 days, and leaving agents are released (A14.3) |
| The provider network looks like paying for referrals | Flat fees only, network rules accepted and versioned, counsel review before production, and providers off for production tenants until then |
| Office admins see too much | Duties are explicit and off by default; agents' contacts, clients and private messages are never visible to anyone |

## 6. Decisions (28 November 2026, all as recommended)

1. **Who creates a brokerage:** only a designated broker with an active TREC broker licence. Office admins and team leaders are invited by the designated broker.
2. **Roster source:** TREC's sponsorship records, refreshed nightly; REOS never imports agents from a spreadsheet the broker can't verify.
3. **Office admin fallback:** 4 working hours by default, set per brokerage.
4. **Provider plans:** Listed, Pro and Partner, flat monthly fees per category and state, set by the super admin; Listed 1 area, Pro the category's Pro areas, Partner 10.
5. **Providers in production:** off until the RESPA counsel review is signed off (a feature flag per tenant and state).
6. **Maps:** Mapbox when the token is set; cities, ZIP codes and a radius always work without it.

## Sources

- [Brokermint agent onboarding](https://intercom.help/brokermintsupport/en/articles/7126082-agent-onboarding)
- [SkySlope: can SkySlope track compliance automatically?](https://skyslope.com/general/can-skyslope-track-compliance-automatically/)
- [Zillow co-marketing FAQ](https://www.zillow.com/comarketing/faq/)
- [Lone Wolf Back Office](https://www.lwolf.com/operate/back-office)
