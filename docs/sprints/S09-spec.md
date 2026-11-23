# Sprint 9 spec: Test prep · transactions and e-sign

- **Dates:** 29 March – 9 April 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 23 November 2026 (decisions in section 6, all as recommended).
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 9: A9.1–A9.3, B9.1–B9.3);
  - the Candidate Complete Journey (screens 23, 31, 38–55);
  - the Candidate App Journey (screens 11, 12, 14, 15);
  - the Web App Journey (screen 14 Deals board, and the candidate practice screens on the web);
  - the Agent App Journey (screens 9 Deals pipeline and 10 Deal alerts);
  - the Connections and Sync Journey (screens 17 Transactions and 22 What goes back to your tools);
  - the S08 wrap-up.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 9 | Already in place | Sprint 9 now does |
| --- | --- | --- |
| A9.1 Tests and simulations | The diagnostic (50 questions, 45 minutes, server clock, answers saved on every tap); 10-question topic quizzes; the results report with topics and exam weight; the test screens on web and phone with flag, navigator and review before submit (A8.3) | **National portion test, Texas portion test and the full simulation** in the real Pearson VUE format and timing; **Exam mode and Practice mode**; the on-screen **calculator**; **answer review** with the lesson second, and Ask why or Similar question; the **simulation result** per portion |
| A9.2 Readiness | A readiness score (weighted topic averages of recent answers); the study plan rebuilt on every test and missed day; Today (A8.3) | **Predicted pass chance per portion** and the **readiness map** with the 90% booking line, recomputed **nightly** and after every test. **What you're good at**, **Fix a weak topic** plans, **smart drill**, **math lab**, **flashcards** with spaced repetition (with Save as flashcard from check-ins, Ask and answer review), **speed round**, **weekly progress report**, and the **Exam success course** |
| A9.3 Hands-free and groups | Voice capture with on-device speech recognition (B4.3b); Ask the instructor (S08 wrap-up) | **Hands-free voice quiz** that keeps going with the screen locked and works with headphones (never promoted for driving); **challenges** (the same 10 questions, by share link); private **study groups** with a weekly leaderboard, first names only |
| B9.1 Lone Wolf | Register entry (brokerage-owned, OAuth 2); the connection engine; calendar and to-dos | **Deals from Lone Wolf** (Transact): transactions, parties, key dates and documents. A **deadline becomes an alert and a to-do**, plus the **Deals pipeline** (phone screen 9), **Deal alerts** (phone screen 10) and **Deals board** (web screen 14) |
| B9.2 dotloop and SkySlope | Register entries | **dotloop** (the agent's own OAuth sign-in: loops, details, folders, documents, participants, task lists, signed webhooks) and **SkySlope** (brokerage keys: listings and sales, documents, checklist dates), into the same deals |
| B9.3 DocuSign | Register entry (OAuth 2, per agent) | **Send for signature from a deal**, track each signer, and the **signed document lands on the deal** (and in the transaction tool when it takes uploads) |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| A larger Texas question bank (national and state outline, English and Spanish) | A9.1 simulations | Nelle | A **starter bank written by REOS** (decision 2), marked "sample", reviewed in the question bank (screen 45) |
| Lone Wolf Transact partner access (Sales@lwolf.com) and a sandbox | B9.1 | Client with Mayura | Simulator; real driver against Lone Wolf's published Transact API docs |
| dotloop API client (client id and secret) | B9.2 | Client with Mayura | Simulator; real driver against dotloop Public API v2 |
| SkySlope order form, test account and per-user access key and secret (via their customer success manager) | B9.2 | Client | Simulator |
| DocuSign developer (demo) account and an integration key; go-live review before production | B9.3 | Mayura (self-serve) | Demo account, which is free |
| Exam success course scripts approved | A9.2 | Nelle | Lessons published as "draft for review" in the course builder |

## 2. Market leaders: what they do, where they fall short, what we do

| Leader | What they do well (we match it) | Where they fall short | REOS answer |
| --- | --- | --- | --- |
| **PrepAgent** | Practice questions for the national and state portions, flashcards, audio lessons, videos, daily webinars | Access as short as one week; flashcards are a flat deck with no spacing; no readiness signal; prep only | Prep is part of the candidate's package for as long as they study. **Spaced flashcards** fed by their own misses. A **readiness map** that says when to book, at 90% on both portions |
| **Real Estate Exam Scholar** | Very large bank, unlimited practice exams, an AI tutor, audio | Little interaction; no study plan; no live help; English only | **Simulations in the real Pearson VUE format** plus a study plan that changes after each one, **Ask the instructor** from the course itself, a **math lab** that teaches step by step, and everything in **Spanish** |
| **Kaplan** | Structured courses, diagnostics, a "pass guarantee" | Static plans, heavy desktop pages, no hands-free study | **Hands-free voice quiz** for walks and chores (never sold for driving), a 60-second **speed round**, and **challenges** with friends |
| **Lone Wolf, dotloop, SkySlope mobile** | Transaction files, documents, e-sign inside each tool | Each app shows only its own deals; deadlines live in the tool, not the agent's day; separate logins | **One deals pipeline across tools**: every deadline becomes a to-do and a calendar alert in REOS, with a 72-hour and a 24-hour heads-up, and the file opens in the tool in one tap |
| **DocuSign** (inside CRMs) | Reliable signing and audit trail | Sending is a separate app; the signed copy isn't filed on the deal | **Send from the deal**, see each signer's status, and the signed copy and certificate are filed on the deal automatically |

## 3. Sub-sprints

### A9.1 Tests and simulations (Team A)

- **The tests (screens 39–42, web and phone):**
  - **Diagnostic** (as now), **National portion**, **Texas portion**, **Full simulation** (both portions, back to back), and **Topic quiz**, each with the best score.
  - The recommended next test is highlighted (from readiness).
  - Every attempt draws new questions from the content outline set for the state in the back office: topics, weights and question counts per portion.
- **The real format (decision 1):** the Pearson VUE Texas sales agent exam:
  - **National:** 85 questions in 150 minutes, pass at 56 of 80 scored;
  - **Texas:** 50 questions in 90 minutes, pass at 28 of 40 scored.
  - The format is configured per state and license type in the back office (screen 62 rules), so the broker exam and other states follow without code.
- **Test intro (screen 40):** questions, time, pass line, when feedback comes, how flag, calculator and review work.
  - **Exam mode:** no feedback until the end. The timer keeps running if the app closes, and simulations can't be paused.
  - **Practice mode:** instant feedback with why, and pause allowed.
- **Test question (screen 41):** counter, countdown, **calculator** (four functions, memory, percent), flag, topic label, four options, Back and Next. Answers are saved on every tap; time is kept on the server.
- **Review before submit (screen 42):** the grid with answered, flagged, blank and current, then Go to first blank. Submitting with blanks asks once; time running out submits.
- **Results (screen 43) and Simulation result (screen 53):**
  - score per portion against its pass line, and the change since last time;
  - strongest and weakest topics, with topic bars and their trend;
  - a **timing insight** (seconds per question against the pace needed);
  - **Fix my weakest**, Review answers, and Share with a study buddy or school.
- **Answer review (screen 45):** the question, their answer and the right one, why with the source, and a **link to the lesson at the exact second**.
  - **Ask why** opens Ask the instructor with the question.
  - **Similar question** gives one more on the same topic.
  - **Add to flashcards**; Next wrong skips the correct ones.
  - Misses go into the next smart drill.
- **Done when:** a full simulation runs in the real format and timing and reports by topic and exam weight. Tests cover the server-side timer, the auto-submit, Exam mode against Practice mode, new questions every attempt, and the pass line per portion.

- **As built, A9.1** (ADR 0035):
  - **Format:** Texas exam format as state rules (`exam.national.*`, `exam.state.*`, screen 62), read by `ExamFormats`.
  - **Tests:** National, Texas and Full simulation on the practice hub (web and phone), with the test intro (Exam mode or Practice mode).
  - **Taking the test:**
    - one timed portion at a time ("National portion, part 1 of 2"), the next starting when the first is finished or its time ran out and the candidate is back;
    - pretest items unscored;
    - unseen questions first;
    - **Pause** in Practice mode;
    - the **calculator**.
  - **Results:** each portion against its pass mark with the change since last time, the timing insight against the real exam's pace, pretest items marked, **Watch the explanation** (the lesson) and **Similar question**.
  - **Moved to A9.2:** Ask why (it needs the study mate's answer from course knowledge) and Add to flashcards (the deck arrives there).
  - **Starter bank (#94):**
    - **Contents:** 300 Texas questions written by REOS in `database/data/starter_tx.php`: 200 national (agency 45, contracts 45, ownership 40, finance 35, math 35) and 100 Texas, each with four options, why and a source, in English and Spanish.
    - **Loading:** a migration loads them as samples. It only adds missing questions and never overwrites one already loaded.
    - **In tests:** samples count in tests and simulations, so a full simulation now has its pretest items, and a second one draws new questions.
    - **Review:** a **Starter bank** tab on the question bank (screen 45), for school admins only, lists the questions by topic with the right answer, why, source and the Spanish. Nelle can approve, edit or retire each question, or approve all shown. Each decision is audited and can be undone from its toast.
    - **Approved:** a question also counts in topic quizzes.
    - **Retired:** a question leaves every test.

### A9.2 Readiness and practice (Team A)

- **Readiness (screen 54, Readiness map):**
  - a **predicted pass chance for each portion**: the chance of reaching the pass line on a new draw, from recent answers per topic weighted by the outline, with older answers counting less;
  - every topic ranked weakest first;
  - the **90% booking line**: "Ready to book" unlocks at 90% on both portions (candidate screen 15).
  - It's recomputed **nightly** (in the candidate's time zone) and after every test, and **readiness drives tomorrow's plan**.
- **What you're good at (screen 44):** National, Texas or All. Each topic shows its % and trend once it has 10 or more answers, plus the pass-chance gain from fixing the weakest.
- **As built, A9.2a** (ADR 0035 §10–13):
  - **Readiness:** a predicted pass chance per portion, from decayed answers per topic. It is shown on Today (the lower of the two, and the ring opens the map), on the practice hub, and on the web's new **Readiness** sidebar page.
  - **Readiness map:** both portions against the 90% line, plus what booking still needs. Booking needs both portions at 90% **and** the last two simulations passed, per web screen 8. Once that's met, **Book the exam** goes to the testing vendor.
  - **What you're good at:** National, Texas or All, with each topic's share, answers and trend from 10 answers, and the gain from fixing the weakest. **Fix my weakest** and each topic start a topic quiz until the fix plan and smart drill arrive in A9.2b.
  - **Kept nightly and after every test:** the nightly job rebuilds plans whose weak topics changed.
- **Fix a weak topic (screen 46):**
  - a 4–6 step plan built from the candidate's actual misses: micro-lessons (the lesson at the right second), practice questions, flashcards, then a retest with new questions;
  - the topic leaves the weak list at 70%.
- **Smart drill (screen 47):** 15 questions from the weakest topics and recent misses, with a streak, a **Hint** (a nudge, not the answer) and Check with why.
- **As built, A9.2b** (ADR 0035 §14–16):
  - **Smart drill:** 15 questions from the three weakest topics with up to five recent misses, plus **Hint** (strikes out a wrong option), the streak, and why after each answer. Tapping a topic on the Readiness map starts a 10-question drill on it.
  - **Fix a weak topic:** reached from Fix my weakest, a topic in What you're good at, or the hub. It has 4–6 steps (micro-lessons at the right second, the misses with why, practice, a retest on new questions) with minutes and now against the goal. Continue opens the next step, and a done step can be reviewed.
  - **Hub ranking:** the smart drill card and every mode ranked with why, on the web hub and the phone.
  - **Flashcards:** join the plan's steps in A9.2d.
- **Math lab (screen 48):**
  - step-by-step problems for prorations, commissions, loan-to-value, area, capitalization, the net-to-seller sheet, and points and interest;
  - each problem is a generator with **new numbers every time**, and the server checks every step;
  - a variant follows each solve, and a missed math question opens its matching problem.
- **As built, A9.2c** (ADR 0035 §17):
  - **Math lab:** on the phone (screen 48) and a web page. It has seven problem kinds, each with new numbers every time.
  - **Checking:** the server checks every step. A wrong value brings the hint, and **Show the answer** is there when needed. The calculator is always to hand.
  - **After a solve:** a variant of the same kind follows.
  - **From the results:** a missed math question offers **Practise this in Math lab**, which opens its kind.
  - **Practice hub:** Math lab joins the ranking, right after the drill and fix plan when math is among the weakest topics.
- **Flashcards (screen 31):**
  - Again, Hard, Got it, scheduled by **spaced repetition (FSRS)**, in two-minute bursts with a counter;
  - **fully offline on the phone**, synced when back online;
  - cards come from the course's approved flashcards (A7.3), **Save as flashcard** (check-ins, Ask, answer review), and misses from the speed round.
- **Speed round (screen 50):** 60 seconds of true-or-false statements with two big buttons and the best and current score. It feeds readiness only lightly, and misses become flashcards.
- **Practice hub (screen 38):** the smart drill on the 3 weakest topics first, then flashcards due, speed round, math lab, hands-free, full simulation and challenge a friend, ranked by what helps most.
- **Weekly progress report (screen 55):**
  - time studied, questions answered, readiness change, minutes per day, lessons and course hours, weakest topic and streak;
  - a Sunday push and email;
  - Share with school or study buddy.
- **Exam success course (screen 23):**
  - 8 short lessons (2 hours) in English and Spanish: how the exam is built, timing, reading NOT/EXCEPT/BEST questions, eliminating wrong answers, math shortcuts, Texas portion traps, a last-7-days plan, and exam-day nerves;
  - included in every candidate package, and suggested when readiness passes 60%;
  - written through the AI lesson pipeline (A7.3) from REOS's scripts, then reviewed on screen 44 (decision 5).
- **Done when:** readiness drives tomorrow's plan. Tests cover the nightly job, the 90% booking line, flashcard scheduling and offline sync, and the math generators against worked answers.

### A9.3 Hands-free and groups (Team A)

- **Hands-free mode (screen 51, phone):**
  - **Experience:** a large microphone, each question read aloud (the phone's own speech), and the answer spoken back ("B", "true", or the option's words), then spoken feedback with why. Repeat and Stop by voice.
  - **Lock screen and headphones:** it keeps going with the screen locked and works with headphones and their buttons (play or pause is Repeat; next is Skip).
  - **Not for driving:** it's never offered or promoted as a driving feature (decision 4). There's no CarPlay or Android Auto, and the start screen says so.
  - **Shortcuts:** "Hey Siri, quiz me with REOS" (App Shortcut) and the Android App Action.
  - **Privacy:** speech recognition runs on the device; no audio leaves the phone.
- **Challenges (screen 52):**
  - Challenge a friend makes a share link. They accept and answer the **same 10 questions**; hints cost points here only.
  - Results go to both of them, with first names only.
- **Study groups:**
  - private groups of up to 20 (invite link, opt-in from Profile);
  - a weekly leaderboard of questions answered and accuracy, first names only;
  - a group member's challenge goes to the whole group;
  - a school can create a group for a class.
- **Done when:** the voice quiz works on the lock screen, and a challenge sent by link is answered by a second candidate with the same questions.

### B9.1 Lone Wolf (Team B)

- **Deals, the one model for every tool:**
  - **The deal:** property, side (buy, sell, lease or both), stage, price, commission, the parties with their roles, **key dates** (option period, financing, survey, title commitment, closing and so on) and documents;
  - **where it comes from:** the source tool and its id, kept per tool like contacts (ADR 0027);
  - **ownership:** the agent's own deals. A broker sees counts and deadlines due for their agents' deals in brokerage tools, never documents (same rule as contacts).
- **Lone Wolf Transact:**
  - **Connection:** brokerage-owned, connected once, with each transaction matched to the agent by email (as for Salesforce).
  - **Read:** transactions, parties, key dates and the document list, read by polling every 15 minutes and by its webhooks if the partner access allows them.
  - **Write:** document uploads (the signed copy from B9.3) and key date updates only where the partner terms allow; otherwise read-only.
- **Deadlines become alerts and to-dos:**
  - every key date creates a REOS to-do on that day, linked to the deal, and a calendar entry on the agent's calendar (B4.1);
  - pushes go out at 72 hours and 24 hours;
  - a date moved in the tool moves the to-do and the alert; one removed in the tool removes them.
  - Deadlines are checked **hourly** (web journey rule).
- **Screens:**
  - **web Deals board (screen 14):** columns by stage; cards with property, side, price and the next deadline; open a deal;
  - **phone Deals pipeline (Agent App Journey screen 9):** under contract, active clients and pending commission;
  - **phone Deal alerts (screen 10):** deadlines and missing documents;
  - **Connections › Transactions (Connections screen 17):** each deal's stage, documents complete, next deadline and commission. It's read-only: changes are made in the tool.
- **Done when:** a deadline in Lone Wolf creates an alert and a to-do, and moving it in Lone Wolf moves both.

### B9.2 dotloop and SkySlope (Team B)

- **dotloop:**
  - **Connection:** the agent's own sign-in (OAuth 2, about 12-hour tokens with refresh).
  - **Read:**
    - profiles, then loops with their details (property, financials, contract dates, which become the key dates);
    - participants, folders and documents (the list, opened in dotloop), and task lists;
    - each profile's loops can be chosen.
  - **Live updates:** dotloop's webhooks (loop and participant events, checked with the signing key, HMAC-SHA1 over timestamp and body, and handled once each), with a 15-minute poll as the backstop.
  - **Write:** uploads the signed document from B9.3 into the loop's folder.
- **SkySlope:**
  - **Connection:** the brokerage's keys. A broker or admin key reaches all brokerage files, and each file is matched to its agent by email.
  - **Read:** listings and sales, their key dates, checklist status and the document list, by polling every 15 minutes.
  - **Write:** the signed document, if the API allows it on the client's plan; otherwise read-only.
- **Done when:** the same as Lone Wolf, for both tools.

### B9.3 DocuSign (Team B)

- **Connection:** the agent's own DocuSign account (OAuth 2 authorization code). Their signing stays in their account, with their branding and billing.
- **Send from a deal:**
  - choose documents from the deal, or upload;
  - signers come from the deal's parties (name, email, routing order), and the agent places signature fields in DocuSign's own sender view, embedded;
  - nothing is sent without the agent pressing Send.
- **Track:** each signer's status (sent, delivered, signed, declined) on the deal through DocuSign Connect, checked with HMAC-SHA256, with a poll as the backstop.
- **Store:**
  - when the envelope completes, the combined signed PDF and the certificate of completion are filed on the deal;
  - they're also uploaded to the transaction tool when it takes uploads (dotloop; Lone Wolf and SkySlope where allowed);
  - the agent gets a push: "Signed: Seller's Disclosure, 2207 Bellaire".
- **Done when:** a signed document lands on the deal (and in dotloop).

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the journeys side by side, in English and Spanish:
  - web: Practice and exams, Readiness, Deals board (14);
  - phone: screens 31, 38–55 and 23 (candidate), and screens 9 and 10 (agent).
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - exam timers can't be extended from the device;
  - simulation questions and answers aren't sent to the device before they're needed;
  - challenge links expire and reveal first names only;
  - group leaderboards are opt-in;
  - a broker never sees deal documents, only counts and deadlines;
  - DocuSign and dotloop webhooks are signature-checked and idempotent;
  - transaction tokens are never shown;
  - signed documents are stored privately and virus-scanned.
- **ADRs:**
  - 0035 exam simulations and readiness;
  - 0036 flashcards, math lab and hands-free;
  - 0037 deals and deadlines across transaction tools;
  - 0038 DocuSign.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| The question bank is too small for new questions every attempt | Starter bank (decision 2) plus questions approved from lessons; the intro says when a test reuses questions |
| iOS stops speech recognition when the screen locks | The quiz runs in an audio session (background audio) with on-device recognition; if a device stops listening when locked, headphone buttons answer (decision 4) and the screen says so |
| Lone Wolf and SkySlope partner access takes weeks | Simulators on demo data; drivers built against their published docs; connected in Settings › Integrations when the keys arrive |
| Deal stages differ by tool | One small set of REOS stages (active, under contract, pending, closed, cancelled), with each tool's status mapped and shown beside it |
| Readiness promises a pass | It's worded "predicted pass chance", with how it's worked out one tap away; never a guarantee |

## 6. Decisions (23 November 2026, all as recommended)

1. **Simulation format:**
   - **Recommended:** the real Pearson VUE format: National 85 questions in 150 minutes, Texas 50 in 90, with the unscored pretest share counted as in the real exam.
   - **Alternative:** scored-only practice sets (80 and 40) with the same times.
2. **Question bank for simulations:**
   - **Recommended:** REOS writes a starter bank of 300 Texas questions (200 national, 100 Texas, in English and Spanish, each with why and a source), marked "sample" until Nelle approves them on screen 45.
   - **Alternative:** wait for Nelle's bank; simulations stay at the size of the approved questions until then.
3. **Deal stages on the web Deals board:**
   - **Recommended:** read-only for deals from a tool (changes are made in the tool, as the Connections journey says). Dragging is only for deals the agent tracks in REOS without a tool.
   - **Alternative:** dragging writes the stage back to the tool where its API allows it.
4. **Hands-free on the lock screen:**
   - **Recommended:** voice answers with the screen locked, plus headphone buttons as the fallback, and no driving features.
   - **Alternative:** a screen-on mode only.
5. **Exam success course content:**
   - **Recommended:** REOS drafts the 8 scripts from the Pearson VUE candidate handbook and the TREC rules, makes them through the AI lesson pipeline, and Nelle approves them on screen 44.
   - **Alternative:** Nelle supplies the scripts.

## Sources

- Texas exam format (85 and 50 items, 150 and 90 minutes, 56/80 and 28/40): https://open-exam-prep.com/study-guides/tx-real-estate/exam-orientation-and-strategy/exam-format-national-and-state (to confirm against the current Pearson VUE Texas candidate handbook)
- dotloop Public API v2 (OAuth, loops, documents, webhooks signing): https://dotloop.github.io/public-api
- Lone Wolf Transact API (partner access): https://apidocs.lwolf.com/doc/transact-api/topic/topic-overview
- SkySlope Transaction Management APIs (keys and permissions): https://skyslope.zendesk.com/hc/en-us/articles/36009561371035-SkySlope-Transaction-Management-APIs
- DocuSign Connect (HMAC-SHA256, events, retries): https://hookdeck.com/webhooks/platforms/guide-to-docusign-webhooks-features-and-best-practices
- PrepAgent review: https://theclose.com/prepagent-review/
- Real Estate Exam Scholar review: https://theclose.com/real-estate-exam-scholar
