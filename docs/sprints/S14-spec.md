# Sprint 14 spec: Practice and learning · win business

- **Dates:** 7 – 18 June 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 27 November 2026 under the standing approval to build the next sprints in order; the decisions in section 6 are all as recommended.
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 14: A14.1–A14.3, B14.1–B14.3).
  - The Licensed Agent Complete Journey: the skills assessment (screen 7, moved here from Sprint 11), role-play and video practice, CE and skills learning, the digital card and public page, the client portal, open houses, lead forms and referrals.
  - The Web App Journey: practice, learning and the public page for agents.
  - The S13 wrap-up.
- **Gate (end of Phase 3):** an agent completes CE and publishes a checked post to every channel; usability check 3.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 14 | Already in place | Sprint 14 now does |
| --- | --- | --- |
| A14.1 AI role-play | Candidates' hands-free practice with spoken answers (A9.3, on-device speech); the assistant's Claude drafts (A12.3); live video rooms (A10.2) | **The skills assessment** (screen 7, from Sprint 11) that seeds the 90-day plan. **Voice role-play** for listing presentations, objections, prospecting calls and appraisal gaps, with a persona that pushes back. **Scored feedback**: a score and **three fixes** every time. **Video practice with AI review** (pace, filler words, structure) |
| A14.2 Agent learning | Courses, the lesson player with seat time and check-ins (A7, A8), the CE wallet and renewals (A11.2), AI lesson review (A8.1) | **CE and skills courses recommended by mode** (Launch, Grow, Lead, Own) and by what the role-play found. **Audio-only lessons**: a CE lesson plays hands-free (text to speech, spoken check-ins) and still counts seat time |
| A14.3 Several licences and organisations | One license per state per person, and states to switch between (A3, A11); brokerage memberships | **Several state licenses** in one account. **Switching brokerage or team**: the agent's own data moves with them (CE, certificates, their contacts, posts, goals and points); the old brokerage keeps its records (compliance log, approvals, its campaigns) |
| B14.1 Card and public page | The public agent page with MLS listings and TREC notices (B10.2b), its compliance (B13.1) | **A digital card** (vCard, QR code, share link). **The public page server-rendered** for search and answer engines (schema.org data, FAQs, sitemap), with the TREC notices; **90+ on Lighthouse** |
| B14.2 Client portal | Deals with parties, dates and documents (B9); e-signature requests (B9.2); listings (B10) | **What clients see from one link**: their listing pages, offer and deal status with key dates, documents to sign, and a review request after closing |
| B14.3 Lead capture and referrals | Contacts in the CRM and write-back (B7, B8); MLS open houses (B10) | **Open house sign-in** (QR code or tablet, works offline on the phone) straight into the CRM with a follow-up. **Lead forms** on the public page. **Agent-to-agent referrals** through the brokers. **Invite and earn.** **Farming market updates** for an area, checked by the compliance engine |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Approval that audio-only CE counts as seat time (with spoken check-ins) | A14.2 | Client and the school (TREC rules for online CE) | Audio-only is offered for skills courses; CE keeps the screen player |
| Speech for role-play (on-device first; Amazon Transcribe and Polly in the register for server-side) | A14.1 | Client | On-device speech on the phone and the browser's speech on the web; typing always works |
| Referral fee rules confirmed with the brokerage | B14.3 | Client and counsel | Referrals are tracked between brokers; REOS never takes or pays a referral fee |

## 2. Market leaders: what they do, where they fall short, what we do

| Area | Leaders: what to adopt | Where they fall short | What REOS does better |
| --- | --- | --- | --- |
| Role-play | **Maverick RE's AI coach** (with Follow Up Boss) lets agents phone an AI prospect from 10+ lead types (portal, expired, cash offer) with rotating objections; a call is won when an appointment is set, with ask rate, objection handling and rapport scores. **PitchMonster** and coaching companies offer scripted practice. | Prospecting calls only, no listing presentations or appraisal gaps. Calls must come from a personal phone. Scores without a plan; nothing feeds the agent's learning or their 90-day plan. English only | **Four practice types**: listing presentation, objections, prospecting calls and appraisal gaps. **Voice in the app** (phone and web), or typing. **A score and exactly three fixes** every time, each linked to a lesson. Results **feed the 90-day plan and course picks**. **Video practice** reviewed for pace, filler words and structure. **English and Spanish** |
| CE and skills learning | **The CE Shop**, **McKissock** and **Colibri** sell Texas CE packages (the 18-hour bundle, Legal Update I and II, broker responsibility), including new AI-practice courses. | Video or text courses at a desk. The same catalogue for everyone, unrelated to the agent's stage or weak spots. CE tracking stops at the certificate | **Courses picked by mode** (Launch, Grow, Lead, Own) **and by what role-play found**. **Audio-only mode** for the car, with spoken check-ins, still counting seat time. Completions go **straight into the CE wallet** and the renewal |
| Agent mobility | Big brokerages (KW Command, eXp) give agents a CRM and site while they're there. | When an agent moves, their contacts, CE history and marketing stay behind or are exported by hand. Several state licenses mean several accounts | **One account for every state license.** **Switch brokerage or team in minutes**: CE, certificates, the agent's own contacts, posts, goals and points move with them, and the old brokerage keeps its compliance records |
| Card and public page | **Blinq** and **HiHello** make digital cards with QR codes. **Placester** and **Agent Image** build agent sites. | Cards don't carry TREC notices. Sites are templated and slow, often missing the IABS and Consumer Protection Notice, and invisible to answer engines | **A digital card** (vCard, QR, share link) with the broker's name. **A server-rendered public page** with schema.org data and FAQs for search and answer engines, **90+ on Lighthouse**, the TREC notices always present, and every word checked by the compliance engine |
| Client portal | **Lofty** launched a client transaction portal in June 2026 (stages the agent chooses, shared documents, e-signature, uploads, through the Closely app). **Rechat** and **ListedKit** show deadlines and status to clients. | A separate app or login for the client. Review requests and listing pages live elsewhere | **One link per client, no app or password**: listing pages, offer and deal status with key dates, documents to sign, and a **review request after closing**, in English or Spanish |
| Open houses and leads | **Curb Hero** (QR and tablet, offline, texts the listing), **Spacio**, **Open Home Pro**; **Homebot** sends monthly home-value updates; referral networks match agents. | Sign-ins sync to a CRM only through integrations; follow-ups are generic. Referral networks charge 25–35% fees. Market emails aren't checked for compliance | **Sign-ins land in the agent's REOS CRM at once** (and write back to their own CRM), with **a follow-up drafted for them**. **Lead forms** on the public page. **Agent-to-agent referrals between brokers**, with no REOS fee. **Invite and earn.** **Farming market updates** from MLS data, **checked by the compliance engine** |

## 3. Sub-sprints

### A14.1a Skills assessment and voice role-play (Team A)

- **Skills assessment (screen 7):** five short role-play rounds (listing, objection, prospecting call, appraisal gap, follow-up), scored; the weakest skills seed the 90-day plan and course picks.
- **Role-play:** pick a type and a persona (seller who interviews three agents, buyer worried about rates, FSBO, expired, appraisal came in low). The persona answers in voice or text and pushes back.
  - Claude plays the persona when it's configured; otherwise REOS's scripted personas.
  - Voice: on-device speech on the phone, the browser's speech on the web; typing always works.
- **Scored feedback:** a score out of 100 on structure, questions, objection handling, clarity and the ask, and **three fixes**, each with an example line and a lesson.
- **Done when:** an agent finishes a role-play and gets a score and three fixes.
- **As built, A14.1a** (ADR 0060):
  - **Data:** `role_plays` (turns, score, five part scores, three fixes, engine) and `skill_assessments` (results, weakest). Neither has an organisation: practice is the agent's own.
  - **Services:**
    - `App\RolePlay\Scenarios` holds the five skills and eight personas, Claude's briefs, and the topic words.
    - `RolePlayScorer` is REOS's scoring: five parts of 20, and the three fixes from the weakest parts.
    - `ClaudeRolePlayer` and `ClaudeRolePlayCoach` use forced tools and fall back to REOS.
    - `RolePlays` covers overview, start, assess, say, finish and view.
  - **The plan:** `AgentPlans` adds a weekly `practice_{skill}` task for the weakest skill. REOS ticks it from a scored practice that week; assessment rounds don't count.
  - **API:**
    - `GET /api/v1/role-play`
    - `POST /api/v1/role-play`
    - `POST /api/v1/role-play/assessment`
    - `GET /api/v1/role-play/{id}`
    - `POST /api/v1/role-play/{id}/turns` (40 a minute)
    - `POST /api/v1/role-play/{id}/finish`
  - **Web:**
    - `/role-play` (Role-play in the agent sidebar): the assessment card, the five skills with their people and best score, and recent practice (with an EmptyState).
    - `/role-play/{id}`: the conversation, then the answer box. The agent can speak (browser speech fills the box) or type, and can have the client's lines read aloud. After the last answer or Finish, the score card shows the five parts and three fixes, each with a line to try and a lesson link.
  - **Phone:**
    - Role-play on Home.
    - `role-play/index` (assessment, skills, recent) and `role-play/[id]`. On-device speech fills the answer box; the client's lines and the result are read aloud (on by default). An assessment round leads to the next round.
  - **Strings:** `roleplay.*` in English and Spanish, eight personas each with six lines.
  - **Done when:** a Pest test plays a listing presentation through four answers. The seller agrees, and the practice scores 80 or more with three fixes. The assessment test plays five rounds, finds prospecting as the weakest skill, and ticks the plan task after a prospecting practice.

### A14.1b Video practice with AI review (Team A)

- Record a listing pitch or a video intro on the phone (up to 2 minutes).
- **AI review:** the transcript, pace (words per minute), filler words, structure, and three fixes; private to the agent.
- **Done when:** a recorded practice comes back with a review.
- **As built, A14.1b** (ADR 0061):
  - **Data:** `video_practices` (words, length, wpm, fillers, score, five parts, three fixes, compliance issues, engine). There's no organisation, and no video is stored.
  - **Services:**
    - `App\VideoPractice\VideoReviewer` measures the words and wpm, finds the fillers, and gives five parts of 20 and three fixes.
    - `ClaudeVideoCoach` uses a forced tool and falls back to REOS.
    - `VideoPractices` (overview, review, show) also runs the compliance engine on the `video` channel.
  - **API:**
    - `GET /api/v1/video-practice`
    - `POST /api/v1/video-practice` (10 a minute; kind, transcript and duration up to 130 seconds)
    - `GET /api/v1/video-practice/{id}`
  - **Web:**
    - `/video-practice` (Video practice in the agent sidebar): pick a listing pitch or a video intro, then record with the camera and MediaRecorder while browser speech fills the words. Watch it back, correct the words, and get the review. Recent reviews are listed, with an EmptyState when there are none.
    - `/video-practice/{id}`: the score, the pace, the filler words, the five parts, three fixes with lessons, "Before you post", and the transcript.
  - **Phone:**
    - Video practice on Home.
    - `video-practice/index`: CameraView with the front camera and a two-minute limit, playback with expo-video, on-device recognition from the file, and editable words.
    - `video-practice/[id]`: the review.
    - `expo-camera` is added, with camera and microphone permissions.
  - **Strings:** `videopractice.*` in English and Spanish.
  - **Housekeeping:** A14.1a's phone files are back in the repo's style: single quotes and long lines.
  - **Done when:** a Pest test sends a 30-second, 78-word pitch and gets back 156 wpm, no fillers, 100 points, three fixes and no broker issue. A rushed intro shows six "um"s, a fast pace, no close, and the claims and broker issues.

### A14.2 Agent learning (Team A)

- **Recommendations by mode** and from role-play results, with CE first when renewal is near.
- **Audio-only lessons:** the lesson is read aloud, check-ins are asked and answered by voice, and seat time counts while it plays.
- **Done when:** a CE lesson plays in audio-only mode.
- **As built, A14.2** (ADR 0062):
  - **Data:**
    - `course_approvals.audio_allowed`: the school confirms audio-only study counts; off by default.
    - `courses.skills` and `courses.modes`: what a course teaches (role-play lesson codes) and who it suits.
  - **Services:**
    - `App\Content\Learning\CoursePicks`: up to six picks. CE comes first when renewal is within 120 days and hours are owed, then the latest practice's fixes and the assessment's weakest skills (a `topic` first), then the agent's mode.
    - `Learning::listen`: sections, words and seconds at 150 wpm.
    - `SeatTime::audioAllowed`, and `beat` with `mode: audio`: the "in front" rule doesn't apply, and stretches are logged as `app-audio`. The reason is `audio_not_allowed` when the course doesn't allow it.
    - `RolePlays::latestFixes`.
  - **API:**
    - `GET /api/v1/learn/picks?topic=`
    - `GET /api/v1/learn/lessons/{id}/listen`
    - The heartbeat takes `mode` (`screen` or `audio`).
  - **Web:**
    - Learn shows "Picked for you": why each course was chosen, and "Can listen on your phone". `?topic=` comes from fix links.
    - The course builder has "What it teaches" and "Who it suits".
    - The approval form has "Audio-only study counts as seat time".
  - **Phone:**
    - Learn shows "Picked for you" with Start or Continue and Listen.
    - The lesson screen has "Listen (audio-only)".
    - `learn/listen/[id]` reads the sections aloud (expo-speech) and sends heartbeats every 15 s with `mode: audio`. A check-in is asked aloud and answered by voice (on-device, `choiceFromSpeech`) or a tap, and REOS says whether the answer was right.
  - **Done when:** a Pest test plays a CE lesson whose approval allows audio-only. Five audio beats with the screen off count 60 seconds, logged as `app-audio`. Without the school's confirmation they count nothing (`audio_not_allowed`).

### A14.3 Several licences and organisations (Team A)

- Licenses in several states in one account, each verified.
- **Switch brokerage or team:** leave one and join another; what moves with the agent and what stays is shown before confirming.
- **Done when:** an agent switches brokerage without losing CE.
- **As built, A14.3** (ADR 0063):
  - **Several licenses:** these already worked. An account has one license per state (`POST /api/v1/me/licences`), each with its own CE wallet. Nothing new was needed.
  - **Services:** `App\Agents\BrokerageMoves`:
    - `from` returns the agent role the invite would move away from.
    - `preview` lists what moves, stays and ends, counting the old brokerage's rows inside its tenant.
    - `accept` requires confirmation, then joins and leaves in one transaction.
    - `complete`:
      - revokes the old role;
      - ends the old brokerage's mentor assignments and pods;
      - sends posts waiting for approval back to drafts and withdraws the page's approval request;
      - audits `agent.moved` in both tenants.
    - A team change only swaps the team role.
  - **Web:** the invite page (`auth/invite`) shows the move summary for a matching account; Join sends `move=1`. The component is `components/moves/move-summary`, with a story and a test.
  - **API:**
    - `POST /api/v1/me/moves/preview` (code)
    - `POST /api/v1/me/moves` (code, confirm)
    - Both are limited to 10 a minute.
  - **Phone:** Me has "Join another brokerage or team" (`me/move`): enter the code, see the preview, then join. Typed codes are cleaned up.
  - **Strings:** `moves.*`.
  - **Done when:** a Pest test moves Maria from Lone Star Realty to Gulf Coast Homes. Before she confirms, the page lists:
    - moves: one license, 7 CE hours, 2 certificates, one contact, one post;
    - stays: one deal, one compliance record;
    - ends: her mentor, her pod, and two items waiting for approval.

    Without confirmation the join is refused. After it, she's an agent only at Gulf Coast and still has 7 CE hours and her contacts. Lone Star keeps the deal and the compliance record, her mentor assignment and pod end, and both tenants audit the move. The phone and team-change tests pass too.

### B14.1 Card and public page (Team B)

- **Digital card:** name, photo, license, broker, contact buttons, vCard download, QR code, share link.
- **Public page:** server-rendered, schema.org (RealEstateAgent), FAQs, sitemap, fast images; TREC notices.
- **Done when:** the page scores 90+ on Lighthouse.
- **As built, B14.1** (ADR 0064):
  - **Data:** `agent_pages.phone`, `email` and `areas`, opt-in.
  - **Services:**
    - `AgentPages::update` takes the card details; its settings return `card_url`, `vcard_url` and `qr_url`; `public()` returns the handle, contact details, areas (or listing cities), language and `updated_at`.
    - `App\Mls\AgentCards` covers the vCard 3.0, the QR SVG, the FAQs and the schema.org data (RealEstateAgent and FAQPage).
  - **Routes:**
    - Public: `/agent/{handle}` (Blade), `/agent/{handle}/card`, `/agent/{handle}/contact.vcf` and `/agent/{handle}/qr.svg`, at 60 requests a minute.
    - Search engines: `/sitemap.xml` and `/robots.txt`.
    - API: `PUT /api/v1/listings/page` takes `phone`, `email` and `areas`.
  - **Views:** `layouts/public`, `public/agent`, `public/card` and `public/sitemap`. The React `public/agent` page and its type are removed.
  - **Web:** the page settings card has Phone, Email and Areas, plus a "Your digital card" block (QR, Open my card, Download contact file, link).
  - **Phone:** the listings page card has a "Your digital card" button that opens `card.tsx`: the QR, share, open, and the contact fields.
  - **Done when:** Lighthouse (mobile, run locally) scores 99, 100, 100 and 100 on the page and the card. Pest checks:
    - the server HTML: title, description, canonical, `lang`, no app script, the JSON-LD and the FAQs;
    - opt-in contact details, and validation;
    - the vCard, the QR and the card;
    - Spanish;
    - the sitemap and robots;
    - and the updated public page tests.

### B14.2 Client portal (Team B)

- One link per client: listing pages, offer and deal status with key dates, documents to sign, and a review request after closing.
- **Done when:** a client signs and reviews from one link.
- **As built, B14.2** (ADR 0065):
  - **Data:** `client_portals` and `client_reviews`, both with row-level security (personal allowed).
  - **Services:**
    - `App\Deals\ClientPortals`:
      - for the agent: list, create (reusing an open link), send (email) and revoke;
      - for the client: find (by hash, open), view, sign, review and publicReviews.
    - `Signatures::start` gives signers with an open link a `clientUserId`; `signingUrl` returns their signing link.
    - `SendsForSignature::recipientView` (DocuSign `views/recipient`, plus the simulator's `signAs` and its signing page).
  - **Routes:**
    - Client: `/portal/{token}` (Blade), `POST /portal/{token}/sign/{request}` and `POST /portal/{token}/review`, with rate limits.
    - Agent:
      - API: `GET` and `POST /api/v1/deals/{deal}/portals`, `POST /api/v1/portals/{id}/send`, `DELETE /api/v1/portals/{id}`.
      - Web (same controller): `deals/{deal}/portals` and `deals/portals/{id}`.
  - **Public page:** "What clients say", with the average and the latest three reviews clients allowed. The schema.org `aggregateRating` and `review` come from those.
  - **Web:** the deal panel has "Share with your client" (`components/deals/deal-portals`): parties one tap away, language, copy, email, turn off, and the review the client left.
  - **Phone:** the deal screen has "Share with your client": share sheet, email, turn off, create.
  - **Mail:** `ClientPortalLink`, in the client's language.
  - **Done when:** a Pest test takes Priya from start to finish:
    - James creates her link and emails it;
    - he sends the disclosure, and Priya, who has a link, gets a `clientUserId`;
    - she opens the portal (stage, dates, agent, `noindex`), signs through the simulator's signing view, and comes back signed, with the envelope completed;
    - after closing she reviews 5 stars with publishing allowed, and James's page shows "Priya S." and the average.

### B14.3 Lead capture and referrals (Team B)

- Open house sign-in (QR or tablet; offline on the phone) into the CRM, with a drafted follow-up.
- Lead forms on the public page into the CRM.
- Agent-to-agent referrals between brokers; invite and earn; farming market updates.
- **Done when:** an open house sign-in lands in the CRM.
- **As built, B14.3a** (ADR 0066):
  - **Data:** `open_houses` (an 8-character code, start and end) and `open_house_visits` (unique `client_id` per open house), both with row-level security.
  - **Services:**
    - `App\Leads\Leads::add`: the contact (through `CrmWriteBack::addPerson`, tagged), the visit note, the suggested message, a to-do for tomorrow and the `new_lead` notification. Someone with an agent gets "send what they asked for" only.
    - `App\Leads\OpenHouses`: list, start, visits, sync (idempotent by `client_id`), byCode (open from an hour before the start to six hours after the end), signIn and qr.
    - `App\Leads\PageLeads`.
  - **Routes:**
    - Public:
      - `/open-house/{code}`: the Blade form;
      - `qr.svg`;
      - `POST` sign-in, 20 a minute;
      - `POST /agent/{handle}/contact`, 5 a minute;
      - all with a honeypot.
    - API: `GET` and `POST /api/v1/open-houses`, plus `GET` and `POST /api/v1/open-houses/{id}/visits` (kiosk sync).
    - Web: `/open-houses`.
  - **Web:** "Open houses" in the agent sidebar (`components/open-houses/open-houses-view`): start sign-in, QR code and link, open houses, visitors with links to their contacts.
  - **Agent page:** "Ask {name} a question" form.
  - **Phone:**
    - Me → Open houses: start at the door (from now, for three hours), the QR code and share sheet, visitors.
    - Kiosk sign-in: saved on the phone (MMKV) and sent when online, with "Send now" for any still waiting.
  - **Done when:** a Pest test signs Rosa in by QR and finds her contact in Maria's CRM, with the visit note, the suggested message, the "Follow up with Rosa" to-do and the notification. Kiosk sign-ins sent twice are added once.
- **As built, B14.3b** (ADR 0067):
  - **Data:**
    - `referrals`: from, to (on REOS or by email), both brokers, client, kind, area, note, fee percent and status. Exempt from row-level security because it spans tenants; access is checked in `Referrals`.
    - `users.invite_code`, `invited_by_id` and `invited_at`.
  - **Services:**
    - `App\Leads\Referrals`: list, send (in-app notification, or `ReferralEmail` with reply-to), accept (into the CRM through `Leads::add`, tag "Referral"), decline, close.
    - `App\Leads\Invites`:
      - summary;
      - claim (from the join link at web sign-up, or the code typed within 30 days);
      - licensed (2,500 points once, through `Points::award`, at most 25 a year), called from `Licences::add` (verified) and `Licences::approve`.
  - **Routes:**
    - API: `GET` and `POST /api/v1/referrals`, `POST /api/v1/referrals/{id}/accept|decline|close`, `GET /api/v1/me/invite`, `POST /api/v1/me/invite/claim`.
    - Web: `/referrals` (with the same actions) and the public `/join/{code}`.
  - **Web:** "Referrals" in the agent sidebar, with the refer form (client agreement required), received and sent lists, and "Invite and earn".
  - **Phone:**
    - Me → Referrals and invites: answer and close referrals, share the invite link, enter who invited you.
    - Refer a client.
  - **Done when:** a Pest test has Maria refer Elena to Dev at another brokerage. Dev accepts, and Elena is in his CRM with the referral note (fee 25%, broker to broker) and a follow-up. An agent outside REOS gets the email. Ana joins through Maria's link and gets licensed, and Maria earns 2,500 points once.
- **As built, B14.3c** (ADR 0068):
  - **Data:** `farm_areas` (city or ZIP, contacts tag, mailing address), `farm_sends` and `farm_opt_outs` (SHA-256 of the address), all with row-level security.
  - **Services:** `App\Leads\FarmUpdates`:
    - areas, add and remove;
    - draft: the figures from the agent's confirmed MLS over three months, the median sale price only when `soldPublic`, and the compliance check on the new `newsletter` channel;
    - send: re-checked and logged, at most weekly, to tagged contacts less opt-outs;
    - unsubscribe.
  - **Mail:** `FarmUpdateMail`, with the attribution, the sender's address, a signed hash unsubscribe link and RFC 8058 headers.
  - **Routes:**
    - API: `GET` and `POST /api/v1/farm-areas`, `DELETE /api/v1/farm-areas/{id}`, `GET /api/v1/farm-areas/{id}/draft`, `POST /api/v1/farm-areas/{id}/send`.
    - Web: `/farming`, with the same actions.
    - Public: `farm/unsubscribe` (signed, exempt from CSRF for the one-click POST).
  - **Web:** "Farming" in the agent sidebar: add an area, the areas, this month's figures, edit, check, send.
  - **Phone:** Me → Farming (areas and adding one), and an area's update (figures, edit, check, send).
  - **Done when:** a Pest test has James add Katy and gets figures from the HAR sample, with no sale price (Texas) and the broker named, passing the check. The update goes to the two tagged contacts with an unsubscribe link that holds no address, and is logged. A second send in the same week is refused. Ana unsubscribes in one click and doesn't get the next update. A send the compliance engine blocks never goes out.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the journeys side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:** role-play and video practice are private to the agent; a client link shows only that client's deal; switching brokerage never carries the old brokerage's records; lead forms and sign-ins are rate-limited.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Audio-only CE not accepted as seat time | Spoken check-ins and seat time as in the screen player; the school confirms; skills courses first |
| Role-play gives legal advice | The persona and the review stay on skills; legal questions go to the broker |
| A client link is shared | Signed, expiring links; only that deal; nothing about other clients |
| Referral fees and RESPA | Referrals between licensed brokers only; REOS never takes a fee |

## 6. Decisions (27 November 2026, all as recommended)

1. **Role-play** is in the app (voice or text), scored with three fixes, and feeds the plan; Claude plays the persona when configured.
2. **Audio-only** lessons count seat time with spoken check-ins; CE in audio-only mode is on once the school confirms.
3. **Switching brokerage**: the agent's own data moves; brokerage records stay.
4. **The public page** is server-rendered and must score 90+ on Lighthouse.
5. **Client links** are signed and expire; no client account is needed.
6. **Referrals** go between brokers; REOS takes no fee.

## Sources

- [Maverick RE: AI role-playing coaching overview](https://intercom.help/maverickre/en/articles/9396864-ai-role-playing-coaching-overview)
- [PitchMonster for real estate](https://www.pitchmonster.io/real-estate-sales-training)
- [Follow Up Boss: real estate role-play scenarios](https://www.followupboss.com/blog/real-estate-roleplay-scenarios)
- [The CE Shop: Texas continuing education courses](https://www.theceshop.com/online-education/texas/real-estate/broker-and-sales-license/continuing-education/courses.html)
- [HousingWire: Lofty launches client transaction portal](https://www.housingwire.com/articles/lofty-launches-client-transaction-portal/)
- [ListedKit: client portal](https://listedkit.com/?p=414)
- [The Close: open house apps](https://theclose.com/open-house-apps/)
- [Homebot: Q2 2026 product releases](https://homebot.ai/blog/q2-2026-product-releases)
- [Blinq: digital business cards for real estate agents](https://blinq.me/blog/best-digital-business-cards-for-real-estate-agents)
