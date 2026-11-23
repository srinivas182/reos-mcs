# Sprint 8 spec: Review and player · CRM II

- **Dates:** 15–26 March 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 23 November 2026 (decisions in section 6).
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 8: A8.1–A8.3, B8.1–B8.3);
  - the Web App Journey (instructor screens 44–45, agent screen 13 Contacts);
  - the Candidate App Journey (screens 5, 7, 8, 9) and the Candidate Complete Journey (player, check-ins, seat time, offline, diagnostic);
  - the Connections and Sync Journey (screens 2, 14, 23, 24) and the Agent App Journey (screens 5 and 6);
  - the S07 wrap-up (carried items).

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 8 | Already in place | Sprint 8 now does |
| --- | --- | --- |
| A8.1 Instructor review | AI lesson versions with lines in English and Spanish, sources and legal flags; videos per language with captions; `review_items` (check-ins, questions, flashcards) "to review"; the View draft dialog (A7.3) | **Screen 44 AI lesson review**: line-by-line approve, suggestions for flagged lines, comments, send back, approve and publish. **Screen 45 Question bank**: to review, approved, rejected and disputed; edit; resolve learner disputes |
| A8.2 Course player | The lesson screen on web and phone: video (Mux HLS or file), synced transcript, slides and documents, "open at the moment" (A5.3b); seat-time rules per approval (A7.1) | **The real player**: check-ins that pause the video, speed, captions in either language, AI lesson in the learner's language, **seat time counted only when the rules are met**, course progress and completion (also refund policy), **offline downloads** on the phone |
| A8.3 Candidate start | Onboarding: free quiz, score, 60-second setup and "Plan built" (A3.3); checkout and plans (B5.1); calendar sync (B4.1); the home screen is a placeholder | **Packages on reos.com** for candidates, the **diagnostic test**, a **study plan** placed on the calendar, and the real **Today** (screen 7): readiness ring, streak, Continue and exactly 20 minutes of tasks |
| B8.1 Lofty | Register entry (accidentally overwritten with kvCORE's details in B7.3, fixed here); CRM engine, key connect, write-back (B7.1–B7.3) | **Lofty two-way**: leads, notes, tasks and stages with the agent's own API key |
| B8.2 Salesforce | Register entry (OAuth 2, brokerage-owned) | **Salesforce two-way** for brokerages: Contacts and Leads, Tasks, Notes, with field mapping for real-estate setups |
| B8.3 CRM hub | Contacts API, Sync activity, phone contact screen (B7.1–B7.2) | **Contacts (web screen 13)** and the phone's contact list: every contact in one list with segments, stage, last touch, source and AI next step; AI summary; CSV import as the last resort; **default CRM for new contacts (screen 24)** |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Lofty test account and API key (Settings › Integrations › API) | B8.1 | Client with Mayura | Simulator; real driver against Lofty's public docs |
| Salesforce Developer Edition org and a Connected App (client id and secret) | B8.2 | Mayura (self-serve) | Simulator |
| Mux static renditions (decision 1) on the client's Mux plan | A8.2 offline | Client | Offline download of the original file for library lessons only |
| The real Texas question bank (Nelle) for the diagnostic | A8.3 | Nelle | The sample bank, marked "sample", as in A3.3 |
| Usability check 1 (carried) | All | Nelle, client | Runs as soon as pilot users and the preview server exist |

## 2. Market leaders: what they do, where they fall short, what we do

| Leader | What they do well (we match it) | Where they fall short | REOS answer |
| --- | --- | --- | --- |
| **AceableAgent, The CE Shop** (players) | Mobile-first lessons, built-in quizzes, timers that enforce seat time, progress across devices | Timers feel like a punishment (locked "Next" buttons); no real offline; check-ins interrupt with no help; English-first | Seat time counted **quietly** from real watching and check-ins, with a clear "1 h 12 m of 2 h counted" meter. **Downloads that still count seat time** (synced when back online). **Check-ins that teach**: a wrong answer explains why, with a source, and saves a flashcard. Spanish video and captions are equal |
| **Synthesia/HeyGen review tools** | Script editing, re-render a scene | No legal review, no line-level approval trail | **Line-by-line approval** with the legal rule and source beside each flagged line, a suggested rewrite, and a full trail of who approved what, kept with each version |
| **Kaplan, PrepAgent** (study plans) | Diagnostic, then a plan | Plans are static PDFs; no calendar; no daily focus | A plan **placed on the person's calendar**, rebuilt as they learn, and a Today that is exactly 20 minutes |
| **Lofty, Salesforce mobile** | Rich CRM, AI assistants inside the CRM | Each CRM's AI only sees itself; agents with two CRMs juggle two apps | **One list across every CRM** with an AI next step that reads all sources, notes written once and sent everywhere, and the default CRM for new contacts |

## 3. Sub-sprints

### A8.1 Instructor review (Team A)

- **Screen 44, AI lesson review** (web, instructor and school admin; opened from Course builder › AI lesson):
  - **Content:**
    - the avatar video in English or Spanish, with captions;
    - the check-ins at their points;
    - the script line by line in both languages, each line with its source (file and page) and, where one applies, the **state rule** from screen 62;
    - flagged lines with the legal rule, and a **suggestion**, a compliant rewrite of the line in both languages;
    - the version and its history.
  - **Actions per line:** Approve, Edit (both languages), **Use suggestion**, **Re-record line**, Comment.
    - An edited line is checked again.
    - A line with an open legal flag can't be approved.
    - **Re-record line** marks the line changed. When the reviewer sends the version for a new render, the vendor makes the video again with the approved script (a new version, so earlier ones are kept).
  - **Whole lesson:** **Approve and publish** needs every line approved and no open flag; the lesson then reaches learners. **Send back** returns it to the course author with a comment.
  - Suggestions come from Claude (Bedrock), from the line, its source passage and the broken rule. Without Claude, they come from the rule's plain-English replacement wording. Each suggestion is charged to the school as `ai_suggestion` (screen 69).
- **Screen 45, Question bank** (web):
  - **Tabs:** To review, Approved, Rejected, Disputed.
  - **Table:** the question, topic, difficulty, source and the exam outline item it maps to.
  - **Actions:** Approve, Edit, Reject; **Resolve dispute**, by keeping the question or correcting it, which tells the learner.
  - **Every question needs a source and an outline mapping** before it can be approved.
  - **Where approved questions go:** questions from AI lessons go into the school's bank (`quiz_questions` with the school and course), check-ins into the lesson, and flashcards into the lesson's deck.
- **Disputes:** a learner taps "Report this question" on any question (player check-in, practice), with a reason. The question's owner (the school or REOS) sees it under Disputed.
- **Audit:** every approve, edit, reject, use-suggestion and publish.
- **Done when:** nothing publishes without approval. An AI lesson reaches learners only after every line is approved by an instructor of that course, and an approved question needs a source and an outline mapping.
- **As built, A8.1** (ADR 0031):
  - **AI lessons** (instructor nav) lists every AI lesson, the waiting ones first.
  - **Screen 44, AI lesson review:**
    - the video in English or Spanish;
    - check-ins and flashcards, each with Approve and Reject;
    - each line in both languages with its source and page;
    - flags with the rule and its legal source;
    - per line: **Suggest a rewrite** (Claude, or the rule's plain wording), Use suggestion, Edit (checked again), Re-record and Comment;
    - for the lesson: **Record again**, which makes a new version with the reviewed script; **Approve and publish**, only when nothing blocks it, with the blockers listed; and **Send back** with a note to the author.
  - **Screen 45, Question bank:**
    - tabs with counts;
    - question, topic, difficulty, source and exam portion;
    - Approve (needs a source and mapping), Edit in both languages, Reject;
    - Disputed: learner reports with Keep or Corrected and a note to the learner.
  - **Learners report a question** through the API; the player and practice screens use it from A8.2.
  - **Lofty:** the register entry B7.3 overwrote is restored.

### A8.2 Course player (Team A)

- **Web and phone player** (Candidate App Journey screens 8–9; the web lesson page grows into the same player):
  - **Video:** the learner's language, picked by their language (English or Spanish), with a switch; captions in either language.
  - **Controls:** back 10 seconds, pause, speed (0.75× to 2×), quality, audio only on the phone.
  - **Transcript:** synced, and tapping a line plays from it (kept from A5.3b).
- **Check-ins** come at the lesson's check-in points (from A8.1's approved check-ins, or every N minutes from the approval's seat-time rule):
  - the video pauses for the question;
  - a wrong answer shows why, with its source, and **Save as flashcard**;
  - check-ins can't be skipped in qualifying courses.
- **Seat time** (server-side, ADR 0030):
  - **Heartbeats:** the player sends one every 15 seconds while the video plays in the foreground at a speed the approval allows.
  - **What counts:** heartbeats count only while the learner is present (check-ins answered on time, the tab visible, the app in the foreground).
  - **Limits:** the approval's maximum hours per day is enforced (Texas: 12).
  - **Completion:** a lesson completes when watch time reaches the approval's required share and every check-in is answered. A course completes when every lesson completes and the course's hours are reached.
  - **Shown to the learner:** a quiet meter, "1 h 12 m of 2 h counted", with why time didn't count (for example "paused for more than 5 minutes").
- **Progress and completion:** course progress on Learn (screen 8) and the dashboard; completion writes a CE or qualifying completion record (A5.1's table).
- **Refunds (carried from Sprint 5):** `Refunds::policy` reads course progress against the 25% limit.
- **Offline downloads (phone):**
  - **Saving:** "Save offline" stores the lesson in the app's private storage (decision 1), encrypted, on Wi-Fi only by default.
  - **Limits:** about 180 MB an hour of video. Downloads stop working when access ends, and expire after 30 days.
  - **Seat time offline:** recorded on the phone and sent when back online, then checked against the same rules (timestamps signed with a per-device key, never trusted alone).
- **Done when:** seat time counts only when the rules are met. Tests cover paused, hidden, too fast, check-in missed, over the daily maximum, and offline replayed out of order.
- **As built, A8.2a** (server and web player, ADR 0030):
  - **Seat time** on the server: heartbeats every 15 seconds; only real time between beats of one viewing counts, at most 20 seconds a beat, so speed never adds time; reasons `gap`, `paused`, `hidden`, `checkin_due`, `daily_max`; every stretch logged.
  - **Rules** from the approval in force (check-in minutes, presence share, maximum hours a day, on the learner's own day).
  - **Check-ins:** approved AI check-ins, else every N minutes; a question when the lesson has approved ones, else "Are you still here?". The dialog can't be dismissed; a wrong answer shows why, with its source.
  - **Completion** of lessons and courses; CE and qualifying courses write the CE completion.
  - **Web player (screen 5):** Back 10 seconds, speed 0.75×–2×, captions in English or Spanish for AI lessons, the meter ("1 h 12 m of 2 h counted") with the reason in plain words. Quality is automatic (adaptive streaming).
  - **Learn** shows each course's progress and each lesson's done mark; **refunds** read progress against the limit.
  - **Report this question** is on the web too (`learn.questions.report`).
  - **Moved:** the phone player, audio only and offline downloads are A8.2b; **Save as flashcard** moves to A9.2 with the deck.
- **As built, A8.2b** (phone player and offline, ADR 0030 §9–10):
  - **Phone player (candidate screen 25):** seat meter, check-in sheet that can't be closed (with Report this question), Back 10 seconds, speed 0.75×–2×, captions in English or Spanish, **Audio only** (keeps playing with the screen locked; seat time counts only while REOS is on screen), **Save offline**. Heartbeats every 15 seconds online. Quality is automatic (adaptive streaming).
  - **Offline:** Mux makes a 720p download of every video; a saved lesson plays from the phone's own storage with its key in the Keychain or Keystore, on Wi-Fi only by default, within the storage limit. It works 30 days between online checks and only while the learner has the course.
  - **Offline seat time** is recorded on the phone, signed (HMAC-SHA256) and replayed when online: in time order, once each, no overlaps, the same check-ins and daily maximum.
  - **Downloads (candidate screen 34)** from Me and Learn: storage used of the limit (1, 2 or 5 GB), Smart downloads (next two lessons on Wi-Fi, removed once completed), Wi-Fi only, each lesson with size and days left, Remove.
  - **Web:** Report this question on the check-in.
  - **Not built yet (no sub-sprint named for them, to schedule at the Sprint 8 wrap-up):** the playback settings sheet (screen 26: quality and data saver per connection) and the player's "Interrupt and ask" bar. Smart downloads remove a lesson once it is completed, until lesson quizzes exist (tests arrive in A9.1).

### A8.3 Candidate start (Team A)

- **Packages on reos.com** (web, public):
  - the candidate's packages for their state (qualifying courses, exam prep, both) from screen 67's plans and prices, with sponsor discounts;
  - checkout as in B5.1;
  - "Have a code?" (sponsor seats).
  - The phone app never shows a price (as decided).
- **Diagnostic test** (phone and web; Candidate Complete Journey week 1): 50 questions across the Texas exam outline, timed at 45 minutes (decision 4). Then a report by topic with the weak topics first.
- **Study plan:**
  - **Built from:** the exam month, minutes a day (from setup), the diagnostic's weak topics and the courses owned.
  - **Content:** lessons and practice per day up to the exam, including review days.
  - **Calendar:** placed on the person's calendar as study blocks (B4.1, written to the calendar they chose).
  - **Rebuilt** when the person misses a day, changes minutes a day, or finishes a diagnostic or practice test.
- **Today (screen 7) for candidates:**
  - a readiness ring (from practice so far) and days to the exam;
  - the streak;
  - **Continue** (the next lesson at its moment);
  - **exactly 20 minutes of tasks**, or the person's own minutes a day;
  - the Ask bar;
  - the reminder at their time.
  - Agents keep their Today (B4); the screen is shared but per role.
- **Done when:** a candidate gets a plan from the diagnostic. They take it, see the plan on Today and on their calendar, and the first lesson opens from Continue.

### B8.1 Lofty (Team B)

- **Connect:** with the agent's own Lofty API key (Settings › Integrations › API in Lofty; `Authorization: token …`), through the key flow from B7.2.
- **Two-way:**
  - leads (people), notes and tasks;
  - stage and tags written back;
  - polling every 15 minutes, plus Lofty's webhooks if the account allows them (confirmed against the test account).
- **Simulator** on demo data. The register entry is rewritten (restoring what B7.3 overwrote).
- **Done when:** the same as Follow Up Boss. A note written in REOS appears in Lofty, and one written in Lofty appears in REOS.

### B8.2 Salesforce (Team B)

- **Connect:** the brokerage connects its org with OAuth 2 (web server flow with PKCE) as a **brokerage-owned** connection (accounts: tenant), from Brokerage integrations (screen 40).
- **Two-way:**
  - Contacts and Leads (decision 3);
  - Tasks (two-way with to-dos);
  - Notes (ContentNote);
  - status or stage.
- **Polling:** on `SystemModstamp` every 15 minutes. Change Data Capture, which needs a paid edition, is listed as later.
- **Owner mapping:**
  - each Salesforce record's owner is matched to a REOS agent by email;
  - an agent sees only the records they own;
  - **brokers never see agents' contacts** (journey rule), only counts and sync health.
- **Field mapping on screen 40:** common real-estate setups (standard objects plus custom fields such as `Stage__c`), saved per brokerage.
- **Simulator** on demo data.
- **Done when:** the same as Follow Up Boss.

### B8.3 CRM hub (Team B)

- **Web screen 13, Contacts (CRM hub):**
  - **Tabs by segment:** All, New leads, Active, Past clients, Sphere, the agent's own tags.
  - **Table:** name, stage, last touch, source CRMs, AI next step.
  - **Contact panel:** details, synced notes, tasks, activity, sources, the smart campaign.
  - **Actions:** Call, Text (on the person's own phone, from the web as a QR or `tel:`), **AI summary**, add note or task (written back to the source CRMs).
- **Phone:** a contact list (search, segments), opening the contact screen (14); Today's "do this now" insight (Agent App Journey screen 6) from the AI next step.
- **AI summary and next step:**
  - **Model:** Claude (Bedrock).
  - **Reads:** the contact's notes, tasks and activity from every source.
  - **Output:** a summary and one next step with why ("No touch in 21 days; they asked about Katy schools").
  - **Cost and caching:** free for agents (decision 2), within the hourly limit, and cached until the contact changes.
- **Default CRM for new contacts (Connections screen 24):** where a contact made in REOS goes (Add contact, or from Messages or a showing). The same person in two CRMs stays merged.
- **CSV import, the last resort:**
  - upload, map the columns, preview the matches (merged on email or phone like any source), import;
  - the CSV is kept as a source named "CSV import, date", and can be undone.
- **Done when:** an agent works all contacts in one list. James Miller sees Follow Up Boss, kvCORE, Lofty and phone contacts together, each person once, and adds a note that reaches every CRM that knows them.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the journeys side by side, in English and Spanish:
  - web: screens 13, 44, 45 and the packages page;
  - phone: player, Today and contacts.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - only instructors of the course review its lessons, and only approved lessons reach learners;
  - seat time can't be inflated (replayed, sped up or hidden tab);
  - offline seat time is signed and checked;
  - downloads stop working when access ends;
  - brokers never see agents' contacts in Salesforce or anywhere;
  - CRM keys and Salesforce tokens are never shown;
  - CSV files are virus-scanned and deleted after import.
- **ADRs:**
  - 0030 seat time and the player;
  - 0031 lesson review and question bank;
  - 0032 Lofty and Salesforce;
  - 0033 CRM hub and AI next steps.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| Seat-time rules read differently by TREC | Rules come from each approval (screen 50), not code; the meter explains every gap; Nelle reviews the rules before the pilot |
| Offline video copied off the phone | Private app storage, per-device encryption key, expiry, no access after the subscription ends |
| Salesforce setups vary widely | Field mapping per brokerage on screen 40, standard objects first |
| AI suggestions change meaning | Suggestions are only offered, never applied without the instructor; the source passage is shown beside them |
| Lofty webhooks unavailable to partners | Polling every 15 minutes, as for kvCORE |

## 6. Decisions (23 November 2026)

1. **Offline downloads:** Mux 720p static renditions, encrypted in the app's private storage, on Wi-Fi by default, expiring after 30 days and when access ends.
2. **AI summaries and next steps in the CRM hub are free for agents.** They aren't charged to the agent's credits; REOS carries the cost.
   - Abuse is held back by screen 69's hourly limit and by caching until the contact changes.
   - Every use is logged in `ai_credit_uses` with 0 credits, so the cost stays visible.
3. **Salesforce:** standard Contacts, Leads, Tasks and Notes, with per-brokerage field mapping for real-estate setups.
4. **Diagnostic:** 50 questions in 45 minutes.

## Sources

- Lofty API keys: https://developer.lofty.com/authentication/api-keys
- Mux static MP4 renditions: https://www.mux.com/docs/guides/enable-static-mp4-renditions
- TREC qualifying education and seat time: 22 TAC §535.65 (as used in A7.1)
