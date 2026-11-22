# Sprint 5: States, content and payments (1 to 12 February 2027)

**Goal:** by the end of the sprint, REOS knows each state's rules and serves each person only the content for the states they hold or are studying for. Lessons and their videos are uploaded, tagged by state and searchable by meaning. Anyone can buy a plan on reos.com and the phone app unlocks within seconds. AI credits, sponsor pools and points run on one ledger that reconciles with the payment gateway.

**Status:** decisions taken 22 November 2026 (section 6). Source: REOS Sprint Plan v1.0 (Sprint 5: A5.1–A5.3, B5.1–B5.3), the Web App Journey (screens 3, 29, 61–63, 66, 67, 69 and the plan and billing screens per role), the candidate app journey (screen 6, Membership) and the agent journeys (CE wallet, licences in several states).

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 5 | Already in place | Sprint 5 now does |
| --- | --- | --- |
| State statuses and waitlist (A5.1) | `states` with status (live, in preparation, waitlist, not planned), regulator, exam vendor, hours; waitlist sign-up on the web and phone; super-admin States screen (61) with audited status changes and Undo (A3.3) | Adds licences per person and state, the state a person is working in, the state switcher, the launch checklist, and state filtering on every content query |
| Tenant states and plans | Tenants carry `states`, `plan` and `limits` (seats, AI credits, sponsor credits) (A4.1) | Plans become sellable products with prices per state; tenant limits feed the credit pools |
| AI credits | `AiCredits` stub: 500 credits a month per person, `ai_credit_uses` rows (B4.3) | Replaced by the ledger: pools per seat, sponsor pools, top-ups, points, refunds |
| Payment gateways | Stripe, Stripe Connect, PayPal and Authorize.net are in the integrations register with credentials fields (B3) | Each becomes a working driver behind one gateway interface, on the Simulator until accounts exist |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Stripe account in the client's Texas entity (test mode is enough to build) with Tax and Billing; Texas sales tax registration added in Stripe Tax | B5.1 | Client | Built and tested on the Simulator and Stripe test-mode fixtures; switched on in Settings › Integrations |
| Stripe Connect platform profile | B5.2 | Client | Payouts run on the Simulator |
| PayPal Business sandbox app; Authorize.net sandbox | B5.2 | Client (sandboxes are free and instant) | Simulator |
| Mux account (or the alternative chosen in decision 2) | A5.3 | Client with Mayura | Uploads go to S3 (SeaweedFS locally) and play the original file; Mux switches on later |
| Amazon Bedrock embeddings model access in us-east-2 (decision 1) | A5.3 | Client | The knowledge base searches by keyword (PostgreSQL full text) until embeddings are on |
| TREC rule texts (22 TAC chapter 535) | A5.2 | Public | — (seeded from the public rules with links) |

## 2. Market leaders: what they do, where they fall short, what we do

Benchmarks from the sprint plan: The CE Shop and Colibri state catalogues; Stripe Billing patterns. Added: Kajabi and Thinkific (course commerce), and how AI tools sell credits.

| Leader | What they do well (we match it) | Where they fall short | REOS answer in this sprint |
| --- | --- | --- | --- |
| **The CE Shop** | A catalogue per state and licence type; packages that bundle the mandatory TREC courses (Legal Update I and II, contracts, Broker Responsibility) | You pick your state and licence type every visit; nothing tells you what *you* still owe or by when; licensed in two states means two separate shopping trips | **Requirement from rules, personal to you:** "You need 7 more hours by 31 March 2027, including Legal Update II." One account holds licences in several states; the state switcher changes everything at once |
| **Colibri Real Estate** | Clear state pages: 18 hours every 24 months, 11 mandatory and 7 elective; memberships; filters by credit type and licence level | Figures on the same page disagree (course fee $129 and $139; first renewal 90–98 hours); course titles out of date; no personal due date ("Varies"); multi-state membership only linked, not explained | **One source of truth with versions:** every rule carries its regulator source and effective date, so pages, the CE wallet and checkout read the same number. Old course versions retire automatically on the rule's end date |
| **TREC itself** | The authority: 22 TAC §535.92 (18 hours; 4 + 4 + 3 mandatory, 6-hour Broker Responsibility for sponsoring brokers and delegated supervisors; no credit for repeating a course in the period) | Rules are legal text; licensees check posted hours by searching the TREC site | **Rules as data with the legal text linked**: the engine applies the repeat-course rule, the broker responsibility condition and the deferral option ($200, 60 days), and shows the paragraph each comes from |
| **Kajabi / Thinkific** | Web checkout, subscriptions, coupons, instant access after paying; course video hosting | One gateway per school; no prices per state; access for a whole team needs workarounds; their apps push in-app purchases or hide buying | **Gateway per state and product** (Stripe in Texas), **sponsor seats** (a brokerage or association pays for its people), and **web-only buying** with the app unlocking in seconds |
| **Stripe Billing patterns** | Products, prices, Checkout, customer portal, Tax, webhooks, Entitlements (feature access per subscription) | Entitlements cover subscriptions only, not one-time course purchases; webhooks can arrive late or twice | **REOS keeps its own entitlements**, fed by every gateway (subscriptions *and* one-time purchases), confirmed the moment the buyer returns from Checkout and reconciled by webhooks and a nightly check |
| **AI tools that sell credits (ChatGPT Team, HubSpot, Jasper)** | Monthly allowances, top-ups | Costs are opaque until you run out; admins can't see who used what; no way to sponsor credits for someone else | **Cost shown before every AI action**, a live balance, pools per seat that the brokerage can top up, a sponsor pool for candidates (candidates are never charged credits) and a ledger admins can export |

**Our own additions (recommended):**

1. **"What do I still need?"** A personal requirement card for each licence: hours done, hours left, mandatory courses missing, due date and the deferral option, computed from the rules engine and the person's completions.
2. **Rule change alerts.** When a rule version changes (for example a new Legal Update edition), everyone affected is told what changed and what they need to do, and course packages built on the old version are flagged on Content and approvals (screen 63).
3. **Launch checklist per state** on screen 61: rules seeded, regulator and exam vendor, at least one qualifying and one CE course, plans and prices, a gateway, the waitlist message. A state can't go live until the checklist is complete, and going live emails its waitlist.
4. **Search inside lessons.** The knowledge base indexes lesson text, slide text and video transcripts in English and Spanish; results jump to the exact paragraph or moment in the video. Ask (Sprint 10) answers from it with citations.
5. **Unlock in seconds, without waiting for the webhook.** When the buyer returns from Checkout, REOS confirms the payment with the gateway directly and opens access at once; the webhook and a nightly reconciliation make sure nothing is missed or doubled.
6. **Sponsor codes and seats.** A brokerage, association or school buys seats or credits and shares a code; the person redeems it in the app (screen 6) or on the web, and the sponsor sees who used it.
7. **One ledger for money, credits and points.** Every purchase, refund, credit grant, credit use and point is a ledger entry with a balance; a daily job reconciles it with the gateway's balance transactions and flags any difference on screen 66.
8. **Kill switch per gateway** (screen 66): pause a gateway and checkout moves to the next allowed one for that state and product, or shows "Purchases are paused" if none.

## 3. Sub-sprints

### A5.1 States, licences and the state switcher (Team A)

- **Licences:** a person can hold licences in several states: state, type (sales agent, broker, designated broker, delegated supervisor), number, status, original licence date, expiry, sponsoring broker (name, as entered), and whether it's their first renewal. Candidates have a *target* state instead.
- **Working state:** each person has a current state (from their licence or target, or chosen). The **state switcher** (web top bar beside the state chip; phone Me screen) appears only when a person has more than one state; switching reloads content, plans and the CE card for that state.
- **Visibility:** courses, lessons, test prep, knowledge base articles, plans and integrations carry `states`. A global state scope filters every content query to the person's states; super admins can preview any state. Matching between brokers and agents or candidates is limited to shared states (already decided).
- **Statuses and waitlist:** live, in preparation, waitlist, not planned (as today). Not-live states show the waitlist; going live notifies the waitlist by email.
- **Launch checklist** (addition 3) on the States screen (61), with "Go live" disabled until it is complete.
- **Screens:** web 61 (checklist, courses count), the top-bar switcher, the person's licences on Profile; phone Me › Licences and the switcher sheet.
- **Done when:** a Texas user sees only Texas content; a person licensed in Texas and Florida (Florida set to live in a test) switches and sees Florida content only.
- **As built:** in two parts. **A5.1a** (server and web): licences with the regulator check (Simulator records outside production; Texas goes live with the TREC data feed in B10.3) or a reviewer's approval on People; the state switcher in the top bar; the `StateTagged` scope (integrations use it now, content from A5.3); screen 61 with courses, the launch checklist and Add state; going live emails the waitlist once. Screen 61 lists live, preparing and waited-for states first, with "Show more states". **A5.1b** (phone): My licences (148), Add a licence (149: `POST /me/licences/check` shows what the regulator holds before adding) and the switcher in Me.

### A5.2 State rules engine (Team A)

- **Rules as data:** each rule has a state, a topic (licensing, exam, CE, advertising, brokerage), a key, a value (structured JSON: hours, courses, conditions), the regulator source (citation and link), a version, an effective date and an optional end date, and a status (draft, published, retired).
- **Editing:** super admins edit drafts and publish (screen 62: tabs per topic, CE and renewal rules, where used, source, version history). Publishing a new version never changes the old one; the rule in force on a date is the one whose dates contain it.
- **Texas seeded from TREC** (22 TAC §535.92 and related): 18 hours per two-year period; Legal Update I (4), Legal Update II (4), contracts (3); Broker Responsibility (6) for brokers who sponsor agents, designated brokers of sponsoring entities and delegated supervisors; electives make up the rest; no credit for the same course twice in the period; first renewal adds the SAE qualifying hours; deferral fee $200 for 60 more days; up to 4 hours for attending a commission meeting. Plus qualifying-education hours (180 for a sales agent), exam vendor and advertising basics (name of the broker, TREC information about brokerage services link) for later sprints.
- **Engine:** `StateRules::for($state, $date)` and `CeRequirement::for($licence, $date)` return the requirement with each part's source. The CE card (addition 1) and rule change alerts (addition 2) read from it.
- **Done when:** a Texas agent's CE requirement (and a sponsoring broker's, with Broker Responsibility) comes from the rules, and changing a rule version changes the requirement from its effective date only.
- **As built (ADR 0019):** 17 rule keys across six topics; Texas seeded from 22 TAC §535.92 and §535.155 and TREC's pages. The CE card shows on the agent dashboard (screen 12 tile), under each licence on the web (screen 23, "What you still need") and in the phone's licence sheet (screen 148), from `ce_completions`. Adding outside completions and certificates is the CE wallet (Sprint 11).

### A5.3 Content and knowledge base (Team A)

- **Courses and lessons:** course (title, type: qualifying, CE, post-licence, test prep, elective; states; hours; approval number per state, from Sprint 7; status) › modules › lessons (text, slides, video, attachments, language). Tagged by state; filtered by A5.1.
- **Media:** uploads go straight from the browser to S3 with presigned URLs (never through the app server), are virus-scanned, and video goes to the video host (decision 2) for streaming with signed playback, captions in English and Spanish, and thumbnails. Files live under `tenants/<id>/` or `platform/`.
- **Knowledge base ingestion:** lesson text, slide text (PDF and PowerPoint), and video transcripts are split into passages with their position (heading, page, time), embedded (decision 1) and stored with pgvector; keyword search uses PostgreSQL full text. Search mixes both (hybrid) and filters by state, language and the person's access. Ingestion is a queued pipeline with status per lesson and re-runs when content changes.
- **Screens:** web 63 (Content and approvals: list, upload, status, preview, search); a search box on Learn for candidates and agents (web and phone) returning passages that open the lesson at the right place.
- **Done when:** a lesson uploaded with a video and slides is searchable by its content (keyword and meaning), and the result opens at the right page or moment.
- **As built (ADR 0020), A5.3a:** the content library (super admin, from screen 63's "Content library"): courses with states, approval numbers and status, modules and lessons, lesson text, and files uploaded straight to storage with signed links, checked by size and first bytes, virus-scanned (ClamAV), then processed: video on Mux (signed playback, captions in the lesson's language, signed webhook), PDF and PowerPoint read page by page, Word with headings, captions files (.vtt) read with their timings. The knowledge base rebuilds a lesson's passages on the `content` queue, keeping unchanged passages and their embeddings; search mixes PostgreSQL full text (English or Spanish) with Titan embeddings in pgvector, filtered to live courses in the person's states and language. The library's search shows results as learners in a state would see them, each opening at its heading, page or moment. Captions in a second language need translation (Sprint 7). **A5.3b (as built):** screen 63, Content and approvals: the review queue (item, tenant, state, checks, status; "Review queue · N" shows only what waits), the content rules card, and the content library behind its button. Each course's checks: an approval number in every state for CE and qualifying courses, and legal flags in any lesson's text, slides or transcript (guarantees, pass-rate claims, "risk-free", approval claims not on record, unprovable "best"; English and Spanish). An open flag blocks going live; a reviewer clears a line that's fine in context (Undo, audited), and an edited line is flagged again. Reviewers Approve and publish or Send back with a note. **Learn** for candidates (Courses) and agents (Learn and CE): live courses for the person's state, search from the top bar or the page, and a lesson that opens at the moment (video with synced transcript, HLS from Mux through hls.js), the page (slides page by page) or the heading. Phone: Learn (screen 8 shape: search and courses) and the lesson screen (expo-video starting at the moment; tap a transcript line to play from it; "From your search" first). API: `GET /learn/courses`, `/learn/search`, `/learn/lessons/{lesson}`. Access by purchase joins with B5.1.

### B5.1 Stripe: plans, checkout and entitlements (Team B)

- **Catalogue:** products (plan, course, package, credit pack, seat pack) with features (entitlement keys such as `learn.qualifying`, `ce.tx`, `agent.core`), and prices per state, buyer type (candidate, agent, broker, school, provider) and interval (month, year, once). Screen 67 (Plans and pricing: tabs by buyer; state, type, price, includes, AI help, status; new, edit, price history). Price changes keep history; existing subscribers keep their price until renewal.
- **Checkout on reos.com** (screen 3): Stripe Checkout (hosted, PCI SAQ A, card data never touches REOS) with Stripe Tax; sponsor discounts and codes apply automatically. On return, REOS confirms the session with Stripe and unlocks at once (addition 5).
- **Billing:** subscriptions, the Stripe customer portal for cards and cancellation, invoices listed and downloadable on each role's Plan and billing screen (web 29 and the equivalents), failed-payment emails, a grace period before access closes.
- **Entitlements:** REOS's own table (person or tenant, feature key, source, starts, ends) fed by checkout, subscription and refund events; read by the API and pushed to the app. **The phone app is access-only:** with no active entitlement it shows the Membership screen (candidate screen 6: no price in the app; iPhone links to reos.com or follows decision 4; Android shows the text without a link; codes unlock sponsored seats).
- **Webhooks:** signed, idempotent (stored by event id), queued, and safe to arrive late or twice.
- **Done when:** a purchase on the web unlocks the app within seconds (test: Checkout session completed on the Simulator → the app's `/me/entitlements` shows the feature without restarting).
- **As built (ADR 0021), B5.1a (server and web):** screen 67 (tabs by buyer, one row per priced state, New plan, Edit, Set a price, Price history; prices never edited), sponsor discounts applied automatically down to the floor, checkout (screen 3) with Stripe embedded Checkout and Stripe Tax (or the Simulator's test panel), unlock on return plus signed, de-duplicated, queued webhooks and a 10-minute reconcile, REOS's own entitlements (`GET /me/entitlements`), Plan and billing in Settings (plans, access, subscriptions with the Stripe portal, receipts), failed-payment emails with a 7-day grace period, and course access by plan (each course's first lesson free). **B5.1b (as built):** the phone's Membership screen (6): what a plan includes, no price; iPhone "Email me the link" or, with the super-admin switch on screen 67, Continue on reos.com; Android the same words without a link; Enter a code. Locked lessons open it, and it unlocks itself when the app comes back to the front after a purchase. Sponsor codes issued on the tenant's Plan tab (seats, access months, expiry, who used each), redeemed in the app or on Plan and billing.

### B5.2 More gateways and payouts (Team B)

- **One gateway interface:** create checkout, confirm, refund, subscription change, webhook parse, payout. Drivers: Stripe, PayPal (Orders and Subscriptions APIs, hosted approval), Authorize.net (Accept Hosted, so card data never touches REOS), and Stripe Connect for payouts.
- **Routing:** screen 66 (Payment gateways) sets which gateway serves each state and product, with status, currency, fees and tax, payouts, tenant accounts, health, and the kill switch (addition 8). Tenants that collect into their own account connect it (Stripe Connect or their own Authorize.net) from their settings.
- **Payouts:** schools, instructors and mentors receive their share through Stripe Connect (Express accounts), with the split per product and a payout statement on Sales and payouts (school screen) — the money side only; sessions and courses that earn it arrive in later sprints.
- **Tax:** Stripe Tax on Stripe; for PayPal and Authorize.net, tax is calculated with the Stripe Tax calculation API so every gateway charges the same tax.
- **Done when:** each gateway can be switched per state and product, and a purchase through each one (on the Simulator and recorded sandbox calls) grants the same entitlement.

### B5.3 Credits, points and the ledger (Team B)

- **Ledger:** double-entry style accounts (person, seat pool, sponsor pool, tenant, platform revenue, gateway clearing) and entries with amount, currency or unit (USD, credit, point), reason, reference (order, refund, AI use, reward) and running balances. Entries are never edited; corrections are new entries.
- **AI credit pools per seat:** each seat gets its plan's monthly credits; unused credits expire at month end (decision on rollover in section 6 defaults to no rollover); top-up packs never expire. The `AiCredits` stub becomes a ledger reader; every AI feature shows its cost first (screen 69 sets costs and limits). **Candidates are never charged credits** (screen 67): their AI use draws on the sponsor or platform pool.
- **Sponsor pools:** brokerages, associations and schools fund credits and seats for their people; codes and seat invites draw on them (addition 6).
- **Points:** earned for learning milestones (rules in Sprint 13); redeemable later for sessions, credits, tickets or seats. This sprint builds the points accounts and entries only.
- **Invoices, refunds and sponsor discounts:** full or partial refunds from screen 66 or the order, reversing entitlements and ledger entries; sponsor discounts recorded as their own entries.
- **Reconciliation:** a daily job matches ledger money entries to the gateway's payouts and balance transactions and lists any difference (addition 7).
- **Done when:** after purchases, a refund and AI use, ledger balances reconcile with Stripe (test against recorded Stripe balance transactions), and credit balances match what each person sees.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the locked journey screens side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:** webhook signatures are checked and replays ignored; card data never reaches REOS (only hosted payment pages); presigned uploads are limited by type, size and path; content outside a person's states or entitlements is never returned, including by search; refunds and price changes are audited.
- **ADRs:** state rules engine (0019); content and knowledge base (0020); payments, entitlements and the ledger (0021).

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Rule texts misread | Each rule links the paragraph it comes from; a second person reviews the Texas seed; rule versions can be corrected without losing history |
| Webhook delays or duplicates | Direct confirmation on return, idempotent event store, nightly reconciliation |
| Apple fees on links to web purchases (the US court is setting a commission on linked-out purchases; Apple proposed up to 15% in August 2026) | Decision 4: the app can avoid in-app links entirely ("Email me the link"), switchable from super admin without an app release |
| Embedding cost and quality in Spanish | A multilingual model (decision 1); passages re-embedded only when text changes; keyword search as fallback |
| Large video uploads | Direct-to-storage resumable uploads; the app server never handles the file |
| Tax on digital education differs by state | Stripe Tax decides per state and product tax code; Texas registration first |

## 6. Decisions (22 November 2026)

1. **Embeddings:** Amazon Titan Text Embeddings V2 on Bedrock (us-east-2, 1,024 dimensions, English and Spanish).
2. **Video hosting:** Mux (direct uploads, signed playback, automatic captions).
3. **Prices:** REOS sets every list price per state; schools and brokerages may discount their own products down to a floor REOS sets.
4. **Buying from the iPhone app:** no purchase link in the app; the Membership screen offers "Email me the link". A super-admin switch turns the direct reos.com link on later without an app release. Android shows the same text without a link (as in the journey).

Defaults (agreed with the decisions): unused monthly AI credits expire at month end (top-ups never expire); refunds are full within 7 days if less than 25% of a course is completed, otherwise at the admin's discretion; a 7-day grace period after a failed renewal payment before access closes; prices in USD only.

## Sources

- TREC: renewal CE requirements: https://trec.texas.gov/i-am-renewing-my-license-how-do-i-know-how-many-classes-i-need-take-renewal
- 22 Tex. Admin. Code §535.92: https://law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-92
- Colibri Texas CE: https://www.colibrirealestate.com/real-estate/texas/continuing-education
- The CE Shop Texas CE catalogue: https://www.theceshop.com/online-education/texas/real-estate/broker-and-sales-license/continuing-education/courses.html
- Stripe Entitlements: https://docs.stripe.com/billing/entitlements
- Amazon Titan Text Embeddings V2: https://docs.aws.amazon.com/bedrock/latest/userguide/titan-embedding-models.html
- Mux pricing: https://www.mux.com/pricing
- Apple external purchase links (appeals court, December 2025): https://www.macrumors.com/2025/12/11/apple-app-store-fees-external-payment-links/
- Apple proposes up to 15% on external purchases (August 2026): https://mjtsai.com/blog/2026/08/17/apple-proposes-15-external-purchase-fee/
