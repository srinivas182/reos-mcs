# Sprint 10 spec: Ask, sessions, licence journey · MLS and lookups

- **Dates:** 12 – 23 April 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 23 November 2026 under the standing approval to build the next sprints in order (decisions in section 6, all as recommended).
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 10: A10.1–A10.3, B10.1–B10.3);
  - the Candidate Complete Journey (screens 13 Ask, f02 answer from course content, k10 package and AI help, k11 when the content can't answer, L12 live class, 15 ready to book, 14 exam day, 15 result entry, f05 share your news, f06 someone passed, 16 licence journey, 17 broker offers, 18 book a live tutor, tutor live, tutor rate, f03 licence number, 20 licensed, f04 your mentors);
  - the Instructor and Mentor Journey (i09 student questions, i10 answer a student, i11 live classes, i12 host a live class, i13 class report, i14 1-on-1 sessions, i15 session room, i16 session notes);
  - the Licensed Agent Journey (MLS-backed listing post, CMA, agent page and open house rules; "sample data until an MLS feed is licensed");
  - the Web App Journey (Ask, sessions and live classes on the web; brokerage recruiting for broker offers);
  - the S09 wrap-up.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 10 | Already in place | Sprint 10 now does |
| --- | --- | --- |
| A10.1 Ask | Knowledge base over lessons, slides and transcripts with citations (A5.3); **Ask the instructor inside a lesson**, free, with simpler, Spanish and quiz me (S08 wrap-up); AI credits ledger with an hourly limit (A6); math lab and Save as flashcard (A9.2) | The **Ask tab** (candidate screen 13) on phone and web: a conversation across every course the person has, typed or spoken, with **Watch at 3:20**, **Quiz me**, **Explain simpler**, **Save as flashcard** and step-by-step math. The **fallback order** from screen k11 (course content → general AI only when a sponsor pays → best content answer plus **Ask my instructor** or **Book an instructor**). The **instructor question queue** (i09, i10) with **Add to knowledge** |
| A10.2 Sessions and live classes | LiveKit rooms for 1-on-1 sessions and live classes, scheduling, join, shared notes, whiteboard, shares and recordings (B6.3); Stripe checkout and payouts (B5.1, B5.2); course approvals with seat-time and attendance rules (A7) | **Booking**: instructor hours and price, slots, **sponsored or paid** (Stripe), 24-hour free cancellation, automatic end, **automatic refund** if the instructor doesn't join within 10 minutes, **rate the session**, and **notes into the study plan**. **Live classes**: registration, reminders, polls, raise hand, questions with likes, **attendance checks** timed to the approval, and the **CE class report** the instructor confirms for the school |
| A10.3 Licence journey | Licences with a regulator lookup on the Simulator (A5.1); readiness and simulations (A9) | **Licence journey** tracker from the state's rules (application, fingerprints, education, exam, sponsor, active) with the one-year window; **Ready to book** and the **exam-day kit**; **result entry** with the retake plan; opt-in **pass announcements** and congrats; **broker offers** to opted-in candidates; **switch to agent by licence number** on the same account; **your mentors** from the sponsoring brokerage |
| B10.1 MLS feeds | Connection engine, sync streams and simulators (B3); integrations register | One **RESO Web API** driver for **Bridge, Trestle and MLS Grid**, with **HAR, NTREIS, Unlock MLS (ACTRIS) and SABOR** as register entries; listings replicated every 15 minutes; each agent linked to their **MLS member** record (matched by TREC licence); **My listings** on web and phone |
| B10.2 Listing services | Deals (B9) | Listing data for **posts, CMAs, public agent pages and open houses**, with each MLS's **display rules** (photo count, attribution, refresh, sold data) enforced in one place |
| B10.3 Lookups and OCR | Licence lookup interface with a Simulator (A5.1) | **TREC** licence and education records from TREC's daily public files; **TDI** insurance licences from data.texas.gov; **NMLS** by reviewer with the consumer-access link; **certificate OCR** with AWS Textract |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Instructor availability and prices for the pilot school | A10.2 | Nelle | Demo instructors with sample hours and prices |
| Brokerages willing to make broker offers | A10.3 | Client | Lone Star Realty demo offers |
| MLS data licences (REOS as a technology vendor) for HAR, NTREIS, ACTRIS and SABOR, through their vendors (MLS Grid, Bridge or Trestle) | B10.1 | Client with Mayura | Simulator with demo listings; the agent screens say "sample data" |
| AWS Textract in us-east-2 (same account as Bedrock) | B10.3 | Mayura | Simulator OCR that reads demo certificates |
| TREC public data files (no key needed) | B10.3 | — | Simulator records |

## 2. Market leaders: what they do, where they fall short, what we do

| Leader | What they do well (we match it) | Where they fall short | REOS answer |
| --- | --- | --- | --- |
| **Real Estate Exam Scholar, PrepAgent (AI tutor)** | Instant AI answers to exam questions | Generic AI with no link to the student's own course; no source; candidates pay per use or by tier | **Answers from the candidate's own course first, with the lesson and second to watch, always free**; 1,000 free credits a month for questions beyond the course, then a sponsor or their own credits; a real instructor when AI isn't enough |
| **Kaplan, The CE Shop (live review)** | Scheduled live review classes | Watch-only; attendance proved by the honour system; no CE report for the instructor | **Live classes with polls, raise hand and questions**, **attendance checks timed to the course approval**, and a **class report** that confirms who earned CE |
| **Wyzant, Varsity Tutors** | Tutor marketplace, booking, payment | Not real estate specific; sessions run in a separate tool; notes stay in the call | **Book an instructor from the answer itself**, the session runs in REOS and **ends on time**, an **automatic refund on a no-show**, and the **notes become study-plan tasks** |
| **Brokerage recruiting (eXp, Keller Williams career nights)** | Recruit new licensees | Candidates are contacted cold after licensing, with no idea of fit | **Broker offers before the licence**, only to opted-in candidates, ranked by fit, rating and terms; contact details shared only on accept |
| **HAR.com, Homes.com agent tools** | MLS listings on agent pages | One MLS each; the agent re-types listings into marketing tools | **One RESO feed layer** for every Texas MLS; listings reach posts, CMAs, pages and open houses within 15 minutes, with each MLS's display rules applied automatically |
| **TREC license search** | The official record | Manual lookups one at a time | **Licence verified in seconds** from TREC's own daily files; candidates move to agent the day the licence is active |

## 3. Sub-sprints

### A10.1 Ask (Team A)

- **A10.1a Ask tab** (candidate screen 13, f02, k10, k11; agent Ask uses the same screen with its own credits):
  - **Conversation:** typed or spoken (on-device speech, as voice to-dos); kept as threads the person can reopen; a photo of a question goes through OCR (B10.3) once available.
  - **Answer order** (decision 1):
    1. **Course content:** the knowledge base across every course the person can open, in their state, best passages first; the answer cites course, lesson and second or page, marked "From your course content · Texas"; free.
    2. **General AI** (Claude on Bedrock), paid for in this order: the candidate's **free monthly credits** (1,000 by default), then their **sponsor's pool** (school, brokerage or association), then **credits they bought**; marked "Beyond your course".
    3. **Otherwise:** the best content answer with its source, plus **Ask my instructor** (A10.1b) and **Book an instructor** (A10.2).
  - **Actions on an answer:** Watch at 3:20, Quiz me (3 questions), Explain simpler, In Spanish, Save as flashcard, Try a similar one (math), Ask my instructor, Book an instructor.
  - **Math:** questions with numbers go through the math lab's step-by-step solver (A9.2).
  - **Agents:** the same Ask, with AI credits from their seat as today.
  - **My package and AI help** (k10): what's included, who sponsors extra AI help, and what happens without one.
  - **Screens:** phone `/ask` (from the Ask bar on Today and the Ask tab), web Ask page in the candidate and agent sidebar.
- **A10.1b Instructor questions** (i09, i10, live-class Ask):
  - **Ask my instructor** from any answer sends the question, with the content answer that wasn't enough, to the course's instructors.
  - **Queue** (web and phone): waiting and answered, with student, course, reason and age; how many were answered from content.
  - **Answer:** text (dictation on the phone), source, Spanish; **Add to course knowledge** (a passage tagged to the course and state, searchable at once); **Make a flashcard** for the student.
  - The student is notified, and the answer appears in their Ask thread.
- **Done when:** a candidate is never charged credits, and an instructor's answer added to knowledge answers the next student's question.
- **As built, A10.1a** (ADR 0039):
  - **Ask** on the phone (`/ask`, from the Ask bar on Today) and the web (candidate sidebar): conversations, typed or spoken, kept per person.
  - **Order:** course content (free, cited, Watch at) → beyond the course from the candidate's free monthly credits (1,000, screen 69), then the sponsor's pool (labelled with the sponsor), then credits they bought; an agent's own credits → otherwise "not covered in your course" with the closest passage and the instructor.
  - **On answers:** Quiz me, Explain simpler, In Spanish, Save as flashcard, Try a similar one (math lab).
  - **Package** (k10): free credits left this month for questions beyond the course, who sponsors more, and credits bought.
- **As built, A10.1b** (ADR 0039 §6):
  - **Credits beyond the course** (the client's revision): 1,000 free a month per candidate by default (screen 69), then the sponsor's pool, then credits bought; 2 credits per general answer.
  - **Ask my instructor** from a conversation; the course's instructors are notified; one waiting question per conversation.
  - **Student questions** on the web (instructor sidebar) and the phone (from Today): waiting and answered, what the content said, the answer typed or dictated, **Add to course knowledge** (on by default) and **Make a flashcard**; how many Ask answered from their content in 30 days.
  - The student sees the answer in the conversation, with a push.

### A10.2 Sessions and live classes (Team A)

- **A10.2a Booking 1-on-1 sessions** (18, tutor live, tutor rate, i14–i16):
  - **Instructor hours:** weekly availability, session lengths (30 or 60 minutes), price; the school may set a maximum (decision 3).
  - **Book:** instructor profiles with rating, rate, languages and next slots; pick a slot; **included** (price 0, for example a school's own students) or **paid** through checkout (decision 2), where a sponsor's discount on the session applies as for any product.
  - **Rules:** free cancellation until 24 hours before; the session ends at the booked time; if the instructor doesn't join within 10 minutes, the student is refunded automatically; reminders at 1 hour and 10 minutes.
  - **After:** rate the session (stars, tags, comment; shown on the profile after 24 hours); the instructor's notes (AI summary from the shared notes) become **tasks on the student's study plan and Today**.
  - **Payouts:** the instructor's share through the existing payouts (B5.2).
- **A10.2b Live classes** (L12, i11–i13):
  - **Registration** from the course's Live tab; reminders at 1 hour and 10 minutes; join from Today.
  - **In class:** polls, raise hand, questions with likes (to the instructor's queue), chat.
  - **Attendance checks** at the times the course approval sets (screen 62 rules: interval and response window); one reminder for a missed check.
  - **Class report** after a CE class: joined, earned CE, didn't; borderline cases accepted or rejected with a reason; **Confirm and send to school**; every decision is audited.
  - **Recording** goes to the course only when the approval allows it.
- **Done when:** a session ends on time and its notes reach the plan.
- **As built, A10.2a** (ADR 0040):
  - **Hours and prices** for instructors and mentors (web and phone): weekly hours in their time zone, 30- and 60-minute prices (0 = included), languages, topics, bio. Paid lengths are session products their school sells; the instructor's share is paid out after REOS's fee.
  - **Book an instructor** (web sidebar; phone from Today and from Ask): rating (after 24 hours), price, languages, next free time; free half-hour slots in the next 14 days, 2 hours ahead.
  - **Paying:** included sessions are confirmed at once; paid ones are held 30 minutes while the student pays at checkout, then confirmed into a LiveKit session.
  - **Rules:** free cancellation until 24 hours before (refunded), the instructor may cancel any time (refunded); an instructor who hasn't joined 10 minutes in means an automatic full refund; the room ends at the booked time.
  - **After:** rate once; the instructor sends follow-up tasks (suggested from the notes) to the student's to-dos.
- **As built, A10.2d** (decision 3, ADR 0040 §1): **1-on-1 session prices** on the school's Instructors screen (52):
  - The school sets the most its instructors and mentors may charge for 30 and 60 minutes; empty means no limit.
  - Prices above a new limit come down at once (audited).
  - The instructor's Hours and prices shows the limit and won't save above it.
- **As built, A10.2b** (ADR 0040 §8–13):
  - **Live classes** (web sidebar for candidates and instructors; phone from Today and from each course in Learn):
    - Instructors see this week, next week, later and past, with registrations, seats and CE hours, and schedule a class on the web: course, time, length and seats.
    - A course with a live approval makes it a CE class with that approval's check interval and seat-time share.
  - **Register** up to the capacity; cancel until the start with Undo; reminders an hour and 10 minutes before.
  - **In class:**
    - viewers, Raise hand (the host brings a student on, so LiveKit lets them speak), questions with likes to the host's queue (Mark answered), polls (results after answering), and chat;
    - for the host, hands and attendance.
  - **Attendance checks:**
    - In a CE class a check opens on the approval's interval; the host can also send one.
    - Each stays open 3 minutes, and a student who joined and hasn't answered gets one reminder.
    - Seat time is counted from joins and leaves inside the booked time.
- **As built, A10.2c** (ADR 0040 §14–16):
  - **Class report** (instructor i13 on the phone; on the web from Live classes):
    - It's ready automatically when a CE class ends, and the instructor is told.
    - It shows joined, earned CE, didn't, and to review, against the approval's rules (seat-time share and all checks).
    - Borderline cases come first. They are within 90% of the seat time needed with at most one check missed, and are accepted or rejected with a reason.
    - **Confirm and send to school** unlocks once every case is decided. The school's admins are told and can open the report read-only.
    - Every decision and the sending are audited.
  - **Recording:**
    - School approvals for live delivery get "Live classes may be recorded" (screen 50).
    - A CE class records only when that's on.
    - Afterward the recording is offered to its students from Live classes.
- **Done when (A10.2):** a session ends on time and its notes reach the plan (A10.2a); a CE class's attendance is confirmed and sent to the school (A10.2c).

### A10.3 Licence journey (Team A)

- **A10.3a Tracker, booking and result:**
  - **Licence journey** (16): the state's steps from the back office (Texas: application, fingerprints, qualifying education, exam, sponsoring broker, active), each with what to do and a TREC link, the candidate's own check-offs and dates, and the **one-year window** countdown from the application.
  - **Ready to book** (15) at 90% readiness on both portions, with the Pearson VUE link (hand-off) and **exam date** that sets reminders; **exam-day kit** (14) with checklist and calm-down audio.
  - **Result entry** after the exam date: passed or didn't, per portion; a missed portion gets the **retake plan** (drills on its weak topics; the passed portion is kept).
- **A10.3b Offers, announcements and switching:**
  - **Pass announcements** (f05, f06): opt-in; who hears (study group, school, Texas candidates with first name and city); never scores; congrats messages; mute in notification settings.
  - **Broker offers** (17): candidates opt in from the tracker; brokerages with Texas offices make offers (split, fees, training, mentoring) on the web; offers ranked by fit, rating and terms; **Accept** shares the candidate's contact details with that brokerage; Decline hides it.
  - **Switch to agent** (f03, 20): enter the TREC licence number; checked against TREC's record (B10.3); the name must match; if it's not active yet, REOS checks daily and notifies; **same account**, progress and certificates kept; three ways in (broker seat, association code, continue alone).
  - **Your mentors** (f04): mentors from the sponsoring brokerage, bookable through A10.2.
- **As built, A10.3a** (ADR 0041):
  - **Licence journey** (web sidebar; phone from Today):
    - The state's steps from screen 62. Texas, from TREC: qualifying education, application, fingerprints, exam, sponsoring broker, active.
    - Each step has what to do, the official page, and the candidate's check-off with its date.
    - Checking off the application starts the one-year window countdown.
  - **Ready to book:**
    - Both portions against the 90% line, plus the simulations passed.
    - "Book with Pearson VUE" (hand-off) once ready.
    - The exam date, time and test centre from the confirmation, which the study plan follows.
    - Reminders a week and a day before, then "Enter your result".
  - **Exam-day kit:**
    - When and where, with Directions.
    - A checklist (two IDs, confirmation, final simulation, flashcards, sleep).
    - Pearson VUE's rules from the back office, and readiness.
    - A two-minute calm-down read aloud by the device.
  - **Result:**
    - Passed, didn't pass, or not taken, per portion; a passed portion is kept.
    - A missed portion gets the **retake plan**: weak areas ticked from the score report, then readiness, each opening Fix a weak topic.
    - It shows the attempts left (Texas: 3, then more education).
    - Both portions passed checks the exam step off.
  - **Screen 62:**
    - New rules: licence steps (with their own editor), application window, exam attempts and exam-day rules.
    - The exam-format rules now have labels.
- **As built, A10.3b** (ADR 0041 §7–9):
  - **Share your news** after passing (web and phone):
    - The candidate chooses study group, school (both told now) and state candidates (in Passes).
    - It shows a first name, city and optional tip, never scores. **Not now** shares nothing.
  - **Passes** (f06): shared passes from the state and study group, this week's count, Send congrats (once each, the passer is told), a simulation and the candidate's readiness. They can be muted in notification settings.
  - **Broker offers:**
    - Candidates opt in from the tracker with a city.
    - **Recruiting** (broker web sidebar) lists them by first name, city and stage. A brokerage makes, changes or withdraws an offer: office, split, fees, training, mentoring, message (audited).
    - Offers are ranked by fit (office in their city), mentors' rating and terms.
    - **Accept** shares the candidate's name, email and phone with that brokerage only; **Decline** hides the offer.
- **Done when:** a candidate becomes an agent on the same account.

### B10.1 MLS feeds (Team B)

- **One driver** for the RESO Web API (OData): `Property`, `Member`, `Office`, `Media`, `OpenHouse`; replication by `ModificationTimestamp`; each vendor's flavour (Bridge, Trestle, MLS Grid) handled in one adapter.
- **Register entries:** `har-mls`, `ntreis-mls`, `actris-mls`, `sabor-mls`, each naming its vendor and fields; REOS's vendor credentials in Settings › Integrations (decision 6).
- **Sync:** every 15 minutes per MLS, platform-wide (one feed serves every agent); deleted or off-market listings removed.
- **Agent link:** the agent's **MLS member** record is found by their TREC licence (`MemberStateLicense`) and confirmed by the agent; listings where they're the list or co-list agent become **My listings**.
- **Screens:** web My listings and phone listings in Deals; listings beside deals; demo data on the Simulator says "sample data".
- **Done when:** an agent's active listings appear within 15 minutes.

### B10.2 Listing services (Team B)

- **One listing service** that every feature uses: posts (B11), CMAs, the agent's public page, open houses.
- **Display rules per MLS** (decision 7): photo count, required attribution (listing brokerage, MLS name and logo), refresh age, whether sold data may show, whether the agent's own listings only; enforced in the service so no screen can break them.
- **CMA:** sold and active comps within a radius and date range, adjusted price per square foot, a range; export as a page.
- **Open houses** from the MLS on the agent's calendar.
- **Public agent page:** active listings with the rules applied.
- **Done when:** a listing post uses MLS photos within the licence terms.

### B10.3 Lookups and OCR (Team B)

- **TREC:** the daily public files (licence holders: type, number, name, status, dates, education and MCE status, sponsoring broker) imported nightly into a lookup table; the real `trec-licence` lookup reads it (decision 8).
- **TDI** insurance licences (for providers) from data.texas.gov; **NMLS** by a reviewer with the consumer-access link (no public API).
- **Certificate OCR:** AWS Textract reads uploaded certificates (CE, course completion) into name, course, hours, date and provider for review.
- **Done when:** a licence number is verified in seconds.

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the journeys side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - answers from course content are never charged, and beyond the course credits are taken in order (free monthly, sponsor, bought);
  - a student sees only their own questions and sessions; instructors only their courses' students;
  - paid sessions can't be joined unpaid, and refunds can't be claimed twice;
  - broker offers never reveal a candidate's contact before Accept;
  - MLS display rules can't be bypassed by any screen;
  - lookup imports and OCR uploads are validated and virus-scanned.
- **ADRs:** 0039 Ask and instructor questions; 0040 sessions and live classes; 0041 licence journey; 0042 MLS feeds and listing rules; 0043 lookups and OCR.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| MLS licences take weeks and differ by MLS | Simulator with demo listings; one driver for every vendor; rules per MLS in the register, not in code |
| General AI says something wrong about Texas law | Content first; the AI fallback is labelled "beyond the course", cites when it can, and offers an instructor |
| Paid sessions on the phone and Apple's rules | Person-to-person live services may use outside payment (App Review guideline 3.1.3(d)); the checkout opens in the browser |
| Attendance checks feel intrusive | Timed only to the approval's rules, one reminder, and a reason shown |
| TREC file format changes | The importer checks the header and stops with an alert rather than loading bad data |

## 6. Decisions (23 November 2026, all as recommended)

1. **Ask fallback** (revised 23 November 2026 at the client's request): course content first and always free; beyond the course, general AI from the candidate's **free monthly credits** (1,000 by default, set by the super admin on screen 69), then the **sponsor's pool**, then **credits the candidate bought** on reos.com; with none left, the closest content answer plus their instructor.
2. **Paying for sessions:** Stripe Checkout on the web and in the phone's browser; saved cards through Stripe.
3. **Session prices:** the instructor sets the price; the school can set a maximum; REOS's fee comes off through payouts as today.
4. **Live-class attendance:** checks timed to the course approval's rules; class recordings only when the approval allows.
5. **Broker offers:** only to candidates who opt in; contact details only on Accept; brokerages need a Texas office.
6. **MLS access:** REOS licences each MLS's feed as a technology vendor through its data vendor; agents link by their member record.
7. **Display rules:** held per MLS in the register and applied in the listing service.
8. **Lookups:** TREC from its daily public files; TDI from data.texas.gov; NMLS by reviewer; OCR with Textract.

## Sources

- TREC public data files (licence holder file format): https://trec.texas.gov/public/trec-public-information-files-instructions and https://www.trec.texas.gov/public/high-value-data-sets
- TDI licensees on data.gov: https://catalog.data.gov/dataset/insurance-agents-adjusters-and-people-approved-to-manage-insurance-related-products-or-cla
- RESO Web API: https://reso.org/reso-web-api/
- Unlock MLS data licensing: https://www.unlockmls.com/data-licensing
- HAR MLS agreements: https://cms.har.com/mlsagreements/
