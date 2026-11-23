# Sprint 7: Schools and AI course builder · CRM I (1 to 12 March 2027)

**Goal:** by the end of the sprint a Texas school runs its own courses in REOS:
- every CE and qualifying course carries a recorded state approval;
- instructors join with their avatar consent on record;
- an instructor turns source files into a module;
- the AI drafts a lesson and renders it as an avatar video with English and Spanish captions, with its check-ins, questions and flashcards waiting for review.

On the agent side, REOS gets one contact model that several CRMs feed without duplicates, and it syncs two ways with Follow Up Boss and kvCORE/BoldTrail.

**Status:** spec agreed 23 November 2026 (decisions in section 6). Source: REOS Sprint Plan v1.0 (Sprint 7: A7.1–A7.3, B7.1–B7.3), the Web App Journey (instructor screens 42–45, school admin screens 49–52), the Instructor and Mentor Journey (avatar consent, course builder), the Connections and Sync Journey (screens 13–15, 22–24 and 31) and the PRD (SCH-01, LRN-01).

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 7 | Already in place | Sprint 7 now does |
| --- | --- | --- |
| A7.1 School tenant | School tenants with school admins, instructors and candidates (S2–S3), self-serve "Start your school" with payment (A6.1b), Sales and payouts (53) | **Course approvals per state** as their own records (number, hours, delivery, approval and expiry dates, seat-time and attendance rules), screen 50, expiring approvals on the school dashboard (49), **Instructors (52)** with invitations and **avatar consent** |
| A7.1 Approvals | One approval number per state on each course (`courses.approvals`), checked before Live (A5.3) | Moves those numbers into `course_approvals` with the full record; the "no label without approval" check also covers expiry and delivery method |
| A7.2 Course builder | The REOS content library for super admins (A5.3a): courses › modules › lessons, uploads to storage, virus scan, Mux video, slides, PDF, Word and captions read into the knowledge base | **School-owned courses** (content under `tenants/<id>/`) and the **instructor's Course builder (43)**: modules with their source material, upload scripts, slides, forms and recorded classes, and the lessons with status |
| A7.3 AI lesson pipeline | Claude on Bedrock (voice to-dos), Titan embeddings and course search, AI and credits settings (69), legal-line checks on lesson text (A5.3b), the review queue on screen 63 | **Script drafting** from the module's sources (each line cites its source), **avatar render** through a video driver, **captions in English and Spanish**, and **check-ins, questions and flashcards** drafted into review. Review screens 44–45 are Sprint 8 (A8.1) |
| B7.1 CRM data model | Connections framework (B3.2): connected accounts, sync runs, cursors, webhooks, a simulator and two-way calendar sync with Google and Outlook; to-dos (B4.3) | **Contacts, notes, tasks, tags and activities** with a link to each source CRM, matching and merging without duplicates, and the conflict rule (newest change wins, logged in Sync activity, screen 23) |
| B7.2 Follow Up Boss | Register entry (system key, user API key); listed on Connections | **Two-way people, notes, tasks and stages**, with Follow Up Boss webhooks for live updates |
| B7.3 kvCORE / BoldTrail | Register entry; listed on Connections | **Two-way contacts, notes and tasks**, plus smart campaign status (read) |
| Carried from Sprint 6 | Usability check kit (`docs/usability/check-1.md`) | **Usability check 1 in week 1** (Nelle), with findings triaged into this sprint |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| HeyGen API credits and a Synthesia plan with API access, and the instructor avatar consent template signed off by counsel | A7.3 | Client | The **simulator** renders a placeholder video with the script as captions; human-recorded video stays the fallback for approved courses |
| A real instructor's 2-minute sample and signed consent, for the first avatar | A7.3 done-when | Nelle | A stock avatar for the end-to-end render; the instructor's twin follows |
| Follow Up Boss system registration (free) and a trial account | B7.2 | Client with Mayura | Built and tested against the documented API with the simulator; switched on once the key exists |
| kvCORE/BoldTrail API token from a test account (generated in Lead Engine › Lead Dropbox) and Developer Portal access | B7.3 | Client with Mayura | Simulator; notes and tasks confirmed against the V2 API when the token arrives |
| TREC approval of REOS-delivered courses (16 weeks; needed by Sprint 19) | Labels | Nelle | Approval records hold Nelle's existing approved courses; avatar courses stay unlabelled until approved |
| 40 pilot users (Nelle); a small server, `preview.reos.com` DNS and a Cloudflare account for the preview (client) | Usability check 1, P7.0 | Nelle, client | Moderated sessions over screen share on a Mayura laptop |

## 2. Market leaders: what they do, where they fall short, what we do

| Leader | What they do well (we match it) | Where they fall short | REOS answer in this sprint |
| --- | --- | --- | --- |
| **Colibri, Kaplan, The CE Shop** | Large approved catalogues, seat-time rules, CE reporting | Schools can't build their own courses; slow to update; approvals managed outside the product | Schools **build and own** courses in REOS. Each course's approvals per state live **next to the course**, and expiry blocks the label automatically |
| **Synthesia, HeyGen** | Realistic avatars, many languages, an API | No idea what's legally true for Texas real estate; consent is a checkbox at the vendor | REOS drafts the script **from the school's own sources with a citation on every line**, runs the legal-line checks before rendering, and keeps the **avatar consent record in REOS**. Withdrawal flags lessons within 30 days |
| **Articulate, Teachable course builders** | Easy module and lesson building | Building is manual; no grounding in state rules | Upload sources once. **"Generate module"** drafts lessons, check-ins, questions and flashcards, and nothing publishes without review |
| **Follow Up Boss, BoldTrail** | Great CRMs with mobile apps and smart campaigns | Each is an island; agents with two CRMs see duplicates; integrations are one-way | **One person, all sources** (Connections screen 14): contacts merged across CRMs, two-way notes and tasks, and conflicts resolved by **newest change wins**, every change logged in plain English |
| **Zapier-style syncs** | Connect anything | Loops, duplicates, silent failures | Echo suppression (REOS never re-imports its own write), idempotent webhooks, and a sync activity log with reconnect prompts |

## 3. Sub-sprints

### P7.0 Preview environment for usability check 1 (week 1)

- **One small private server**, not the AWS build: Docker Compose with the app (Octane), queue worker, scheduler, Reverb, PostgreSQL with pgvector, Redis and Meilisearch.
  - Seeded with the journey personas, one account per pilot user.
  - Every integration on its simulator, so nothing reaches a real phone or inbox.
  - Nightly backup; wiped after Sprint 7.
- **Private:** behind Cloudflare Access with email one-time codes for the pilot users, Nelle, Mayura and the client. REOS's own sign-in still applies.
- **Phones:** an EAS development build pointed at the preview (iOS internal distribution and an Android APK).
- `docs/preview.md` covers setup, adding pilot users, resetting and removal; deployed from `main` with one command.
- **Needs from the client:** the server (2 vCPU, 4 GB is enough for 40 people), a subdomain such as `preview.reos.com`, and a Cloudflare account. **Done when:** a pilot user signs in on the web and the phone and completes a candidate task.
- **As built, P7.0:**
  - **Files:** `deploy/preview/` holds one image (FrankenPHP with Octane; the same image runs the worker, scheduler and Reverb), Compose with PostgreSQL and pgvector, Redis, Meilisearch, Mailpit, Caddy and cloudflared, plus `bin/deploy.sh`, `reset.sh`, `backup.sh` and `pilots.sh`.
  - **`REOS_PREVIEW=true`** (never in production) adds a banner on every web and phone screen. It also makes every simulator verification code `123456`, so pilot users can verify a phone number.
  - **`reos:preview:pilots`** creates pilot accounts from a CSV next to each role's persona (same organisation and roles) and prints temporary passwords.
  - **Reverb split:** the app publishes to Reverb inside the network, while phones and browsers get the public host.
  - **Phone app:** an EAS `preview` profile points the app at the preview.
  - **Access:** the phone can't answer Cloudflare Access's email code, so `/api` and `/app` bypass Access and still need a REOS sign-in.
  - **Checks:** the CI **Preview image** job builds the image and checks it. Setup is in `docs/preview.md`.


### A7.1 Schools and approvals (Team A)

- **Course approvals (`course_approvals`):** one row per course and state, with:
  - type (qualifying, CE, post-licence) and the regulator's approval number;
  - approved hours;
  - delivery (classroom, live online, self-paced online, AI avatar);
  - approved on, expires on, status (pending, approved, expired, withdrawn);
  - **seat-time rules**: minimum minutes per credit hour, maximum hours per day, check-in frequency;
  - **attendance rules** for live delivery: joined for at least N% and answered the check-ins.

  Defaults come from the state's rules (screen 62), so Texas values are set once. The A5.3 approval numbers move here.
- **No label without approval:** the CE or qualifying label, price "for credit" and the CE certificate need an approved, unexpired approval for that state and that delivery method. An AI avatar course needs an approval whose delivery is AI avatar.
- **Expiry:** the school admin is told 60 and 30 days before an approval expires. On expiry the course stays visible but loses its label and can't be sold for credit. Audited.
- **Screens:**
  - **Course catalogue and state approvals (50):** table of type, state, approval number, hours, expiry, delivery, status. Actions: Add approval, Add state, Renew.
  - **School dashboard (49):** students, courses, CE completions, sales, and a "Needs attention" list (expiring approvals, lessons waiting for review).
  - **Instructors (52):** approvals, courses, avatar consent, rating, price, status. Actions: Invite, Edit, Revoke.
- **Avatar consent (new consent purpose `avatar_likeness`):**
  - The instructor reads the signed template (versioned like Terms), agrees, and uploads or records a 2-minute sample.
  - The consent says the avatar is used only by their school and only for lessons they approve.
  - Withdrawal flags every lesson that uses the avatar; within 30 days the lessons are unpublished or re-rendered with another presenter.
  - In "Download my data" and the data map.
- **Done when:** no CE or qualifying label appears without a recorded, unexpired approval for that state and delivery (Pest, plus a browser check on Learn).
- **As built, A7.1a** (ADR 0025):
  - **Approval records:** `course_approvals`, one per course, state and delivery, holding the number, hours, dates, status, and the seat-time and attendance rules. The A5.3 numbers moved in.
  - **Texas defaults:** the new state rule `courses.seat_time` comes from 22 TAC §535.65(g): 12 hours a day, present for the hours credited, makeup after two-thirds within 90 days. Check-ins every 15 minutes are REOS's own default.
  - **Labels:** shown on Learn (web and phone) and in `credit` only with an approval in force for the course's delivery.
  - **Expiry:** `reos:approvals:check` reminds at 60 and 30 days, and on expiry removes the label and audits it.
  - **Owners:** courses have an owner school and a delivery. Texas Realty School owns the demo's Principles of Real Estate II and Contracts for new agents.
  - **Screen 50** offers Add approval, Renew, Edit, Add state, and Withdraw with Undo. **Screen 49** has the approvals card.
  - Instructors and avatar consent follow in A7.1b.
- **As built, A7.1b** (ADR 0025 items 8–10):
  - **Screen 52, Instructors:** courses taught, approvals in force, avatar consent, rating, price, status and pending invitations, with Invite, Edit (the courses they teach) and Revoke with Undo.
  - **Settings › AI avatar** for instructors: the terms with their version; agree with a 2-minute sample; withdraw.
  - **When consent ends:** withdrawal (here or from the privacy history) deletes the sample, audits it and tells the school's admins they have 30 days. Revoke keeps the sample for a day so Undo works.
  - **Privacy:** samples are deleted with the account, and avatar consents are in "Download my data".

### A7.2 Course builder (Team A)

- **School-owned courses:** courses get an owner (REOS or a school). A school's courses, files and knowledge-base passages live under `tenants/<id>/`, are sold only by that school, and are seen only by its admins and assigned instructors. The REOS library is unchanged.
- **Course builder (43), for instructors and school admins:**
  - courses the person can edit;
  - a course's modules with status;
  - each module's **source material**: scripts (Word, PDF), slides (PowerPoint, PDF), forms (PDF), recorded classes (video, including B6.3c session recordings the people in them agreed to);
  - **what AI will make** (lessons, check-ins, questions, flashcards, with counts and credit cost);
  - settings (language, target minutes per lesson, presenter);
  - lessons with status (draft, generating, in review, approved, live).
- Uploads reuse the A5.3 pipeline: signed upload, size and first-byte checks, virus scan, extraction, captions.
- **Seat-time changes go to the school for TREC:** changing minutes or hours on an approved course creates a change request for the school admin instead of editing the approval.
- **Instructor dashboard (42):** reviews, questions, sessions, pass rate, and today's list. "Start live class" links to B6.3.
- **Done when:** an instructor builds a module from source files (uploads, sees them read and indexed, arranges lessons), on the web.
- **As built, A7.2** (ADR 0026):
  - **Course builder** (`/course-builder`) for school admins (as their course catalog) and instructors (the courses they teach): a course list, and New course, which the school owns.
  - **Screen 43** has the module list with status; each module's source material (scripts, slides, forms, recorded classes, captions) with a signed upload, virus scan and reading; "What AI will make" (minutes, lessons, check-ins, questions, flashcards, credits); the settings (language, minutes per lesson, a presenter with avatar consent); and the lessons with status (add, edit, order, approve hand-written ones, remove with Undo).
  - **Rules:** a school's lessons reach learners only once approved. Seat-time changes on an approved course go to the school. Only the school admin publishes.
  - **Screen 42, the instructor dashboard:** reviews, drafts, sessions this week, practice pass rate, today, and the most-missed topics.

### A7.3 AI lesson pipeline (Team A)

- **Script drafting:** Claude (on Bedrock, the model set on screen 69) writes each lesson's script from the module's sources only.
  - The script is a list of lines; each line cites its source (file, page or timestamp) and the state rule where one applies.
  - The legal-line checks (A5.3b) run on every line before anything renders.
  - The draft's credit cost is shown before it runs and charged to the school.
- **Avatar render (`AvatarVideo` driver, HeyGen and Synthesia, decision 1):** the approved presenter (a consented instructor's twin or a stock avatar) and the script go to the school's chosen vendor, once in English and once in Spanish (decision 4). The finished video goes into REOS's video pipeline (Mux, signed playback), and the vendor's copy is deleted. Signed webhook or polling, retries, a cost record per render. The simulator renders a placeholder.
- **Both languages:**
  - The script is translated per line by Claude, using the GLOSSARY for Spanish (formal "usted"), and the instructor sees both side by side.
  - Each video gets captions in its spoken language from the script with the vendor's timings, plus the other language's captions.
  - Learners get the video in their language. Both versions are indexed for search.
- **Check-ins, questions and flashcards:** check-ins every few minutes (from the seat-time rule), multiple-choice questions with a source and topic, and flashcards, all drafted into a review queue with status "to review". Approving, editing and rejecting is A8.1.
- **Versions:** each generation is a version of the lesson, and earlier versions are kept.
- **Done when:** one lesson renders end to end: sources → script with citations → legal check → English and Spanish avatar videos with captions → check-ins, questions and flashcards in review.
- **As built, A7.3** (ADR 0026, items 10–18):
  - **Screen 43 additions:**
    - **Generate module** shows the credits the school has, and says when videos will be placeholders because no vendor is connected.
    - The lesson list shows each AI lesson's version.
    - **View draft** shows English and Spanish side by side, each line's source file and page, flagged lines, and the two videos. **Generate again** makes a new version; earlier versions are kept.
    - A new setting picks the avatar vendor: **HeyGen** or **Synthesia**.
  - **Screen 52 addition:** **Avatar ids** records an instructor's twin at each vendor, once their consent is given.
  - **Pipeline:**
    - Claude writes from the sources only and translates line by line.
    - Legal checks run in both languages; a flagged line stops rendering.
    - Each lesson is rendered once in English and once in Spanish. REOS checks the vendor every two minutes, then copies the video into storage and Mux and deletes the vendor's copy.
    - Each video gets captions in both languages. Learners get the video in their language.
    - Check-ins, questions and flashcards go into `review_items` as "to review".
  - **Credits** come from the school's pool and are logged per version.
  - **Simulators:** without keys, REOS uses a sentence-based writer and a placeholder video. The demo school has 5,000 credits and one AI lesson made end to end.
  - **Differences from the plan:**
    - Polling rather than webhooks.
    - Caption timings are proportional to the video's length, because the vendors report the length but not each line's timing.
    - Lines cite their source file and page. Citing the state rule (screen 62) on each line comes with the line-by-line review (A8.1).

### B7.1 CRM data model (Team B)

- **Model:**
  - `contacts`: a person in the agent's world, with names, emails, phones in E.164, addresses, birthday, stage, tags and source;
  - `contact_links`: the same contact in each connected CRM, with the external id, its version or updated-at, and its last sync;
  - `contact_notes`, `contact_tasks` (linked to REOS to-dos, so done in one is done in the other), `contact_activities` (calls, emails, showings, stage changes), `tags`.

  Contacts belong to the agent (personal tools) or the brokerage (brokerage CRM), as in the Connections journey's ownership rules.
- **Matching:** a contact from a second source joins an existing one on a verified email or phone. Name alone suggests a merge but never merges on its own. Merges keep both links, a merged contact can be split again, and both are audited.
- **Conflicts:** newest change wins, field by field. The losing value is kept in the activity log ("Follow Up Boss changed the phone to …; REOS's earlier value …"). REOS never echoes its own writes back as changes.
- **Sync engine:** contact drivers on the B3.2 connections framework (cursors, webhooks, rate limits, reconnect), the default CRM for new contacts (screen 24), and **Sync activity (23)** in plain English.
- **Phone contacts** as the first source: read on the phone with permission, matched like any other source, and never written back.
- **Done when:** two sources merge without duplicates (the same people from the simulator CRM and phone contacts end up once each, with both links).
- **As built, B7.1** (ADR 0027):
  - **Model:** contacts with links per source, notes, tasks linked to to-dos, tags, activities and merge suggestions, owned by the agent or the brokerage under row-level security.
  - **Matching** on a well-formed email or E.164 phone. A name only suggests a merge.
  - **Conflicts:** the newest change wins field by field, and the losing value is logged in plain English. REOS's own writes are fingerprinted, so they never come back as changes.
  - **Merge and split** are audited.
  - **Sources:**
    - CRMs save their people through the connections engine's `ContactStore`.
    - **Phone contacts** (phone app › Connections › Phone contacts) are read only when the person taps Add, and never written back.
  - **Sync activity (screen 23)** on the phone shows what changed in plain words, with Merge and "Not the same" for same-name contacts. Merge has Undo.
  - **Privacy:** contacts are in "Download my data", deleted with the account, and recorded in the data map.
  - **Demo data:** James Miller's CRM and phone contacts. Maria Lopez and David Chen end up once each with both links, the two Mike Johnsons are suggested as a merge, and Maria's newer stage from the CRM is logged.
  - **Moved to later sub-sprints:**
    - The default CRM for new contacts (screen 24) and brokerage contact screens come with Follow Up Boss (B7.2).
    - The CRM simulator's sign-in comes with each CRM driver.

### B7.2 Follow Up Boss (Team B)

- Connect with the agent's Follow Up Boss API key under REOS's registered system name and key.
- Two-way: people, notes, tasks, stages (and tags).
- Follow Up Boss webhooks (`peopleCreated`, `peopleUpdated`, `notesCreated`, and others) for live updates, checked against the system key's signature, with polling as a backstop.
- Rate limits respected, deletes handled as "removed in Follow Up Boss".
- Connections screens 13–15 for Follow Up Boss: what's synced, counts, direction, Sync now, activity.
- **Done when:** a note written in REOS appears in Follow Up Boss (and one written there appears in REOS).
- **As built, B7.2** (ADR 0028):
  - **Connecting:**
    - The agent connects with their own Follow Up Boss API key; REOS's system name and key go with every request.
    - The phone's consent screen takes the key, because the catalogue says `connects_with: api_key`.
  - **Sync:**
    - People, notes and tasks are read in pages, newest change first.
    - Notes written in REOS are sent to every connected CRM, and are never read back twice.
    - Follow Up Boss tasks are REOS to-dos, so done in one means done in the other.
    - Stages are sent both ways.
    - A person deleted in Follow Up Boss stays in REOS as "removed in Follow Up Boss".
  - **Webhooks:** checked against `FUB-Signature` and the signed connection in the address, and they only queue a sync.
  - **Phone:** a new contact screen (screen 14) shows the sources and notes, with Add a note.
  - **Simulator:** Follow Up Boss demo data, used until the client registers the system key.
  - **Still to come:** the default CRM for new contacts (screen 24) waits for REOS-created contacts.

### B7.3 kvCORE / BoldTrail (Team B)

- Connect with the agent's kvCORE API token (Contacts scope; V2 API).
- Two-way contacts, notes and tasks where the V2 API allows; smart campaign status is read-only on the contact.
- Polling on a cursor, since there are no webhooks for partners until approved.
- **Done when:** the same as Follow Up Boss: a note written in REOS appears in BoldTrail.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the locked journey screens side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - a school sees only its own courses, files, students and approvals;
  - instructors edit only courses they're assigned to;
  - nothing renders without the presenter's avatar consent, and withdrawal flags lessons;
  - AI drafts use only the course's sources and never another tenant's;
  - contacts are visible only to their owner (agent or brokerage rules);
  - CRM keys are stored in the credential vault, never shown again;
  - webhooks are signed and idempotent.
- **Gate (from the plan):** none this sprint. Usability check 1 runs in week 1, and its severity 3–4 findings are fixed in this sprint.
- **ADRs:**
  - 0025 schools, approvals and avatar consent;
  - 0026 AI lesson pipeline;
  - 0027 CRM model and sync.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| TREC hasn't approved AI avatar delivery | Approvals record delivery per course; avatar lessons stay unlabelled until approved; human-recorded video for approved courses |
| Avatar vendor cost or quality | One driver interface (HeyGen, Synthesia, simulator); cost per render recorded and shown before rendering; switch vendor without changing lessons |
| AI script says something legally wrong | Script only from the school's sources with citations; legal-line checks; nothing publishes without instructor approval (A8.1) |
| Duplicate contacts across CRMs | Merge only on a verified email or phone; suggested merges for names; split again with Undo |
| Sync loops between REOS and a CRM | Echo suppression by remembering our own writes (version and hash); idempotent webhooks |
| CRM partner access slow (kvCORE) | Personal API tokens for the build; partner application in parallel; simulator in CI |

## 6. Decisions (23 November 2026)

1. **Avatar vendors:** both from the start. **HeyGen** (API credits, digital twins through the API) and **Synthesia** (API plan) are drivers behind one `AvatarVideo` interface, chosen per school in Settings › Integrations, with the simulator everywhere else. The cost per render is recorded per vendor so the two can be compared on real lessons.
2. **Usability check 1 environment:** a **small private preview environment** (P7.0 below), seeded with the journey personas, behind email one-time codes (Cloudflare Access), on the simulators. AWS staging stays at the end.
3. **Course ownership:** **the school** owns what it builds (content, files, passages under `tenants/<id>/`), and only it sells those courses. The REOS library stays separate.
4. **Spanish in AI lessons:** **always both voices.** Every lesson is rendered twice, an English-voiced and a Spanish-voiced video from the line-by-line translated script, each with captions in both languages. The credit cost shown before rendering covers both.

## Sources

- REOS Sprint Plan v1.0, Sprint 7; Web App Journey screens 42–45 and 49–52; Instructor and Mentor Journey (avatar consent); Connections and Sync Journey; PRD SCH-01 and LRN-01.
- HeyGen API pricing (help.heygen.com), Synthesia plans (synthesia.io/pricing), Follow Up Boss API (docs.followupboss.com), kvCORE API tokens (help.insiderealestate.com).
