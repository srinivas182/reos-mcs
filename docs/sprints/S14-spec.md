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

### B14.1 Card and public page (Team B)

- **Digital card:** name, photo, license, broker, contact buttons, vCard download, QR code, share link.
- **Public page:** server-rendered, schema.org (RealEstateAgent), FAQs, sitemap, fast images; TREC notices.
- **Done when:** the page scores 90+ on Lighthouse.

### B14.2 Client portal (Team B)

- One link per client: listing pages, offer and deal status with key dates, documents to sign, and a review request after closing.
- **Done when:** a client signs and reviews from one link.

### B14.3 Lead capture and referrals (Team B)

- Open house sign-in (QR or tablet; offline on the phone) into the CRM, with a drafted follow-up.
- Lead forms on the public page into the CRM.
- Agent-to-agent referrals between brokers; invite and earn; farming market updates.
- **Done when:** an open house sign-in lands in the CRM.

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
