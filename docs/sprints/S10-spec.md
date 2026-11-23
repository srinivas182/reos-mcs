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
| **Real Estate Exam Scholar, PrepAgent (AI tutor)** | Instant AI answers to exam questions | Generic AI with no link to the student's own course; no source; candidates pay per use or by tier | **Answers from the candidate's own course first, with the lesson and second to watch**; never a credit balance for candidates; a sponsor can fund broader AI; a real instructor when AI isn't enough |
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
    2. **General AI** (Claude on Bedrock) only when a **sponsor** pays: the brokerage or association that sponsors the candidate, from its own credit pool; marked "Beyond the Texas course".
    3. **Otherwise:** the best content answer with its source, plus **Ask my instructor** (A10.1b) and **Book an instructor** (A10.2); never a credit balance or a purchase.
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
  - **Order:** course content (free, cited, Watch at) → beyond the course only with a sponsor's pool or an agent's credits, labelled with the sponsor → otherwise "not covered in your course" with the closest passage.
  - **On answers:** Quiz me, Explain simpler, In Spanish, Save as flashcard, Try a similar one (math lab).
  - **Package** (k10): whether help beyond the course is available and who sponsors it; candidates never see credits.

### A10.2 Sessions and live classes (Team A)

- **A10.2a Booking 1-on-1 sessions** (18, tutor live, tutor rate, i14–i16):
  - **Instructor hours:** weekly availability, session lengths (30 or 60 minutes), price; the school may set a maximum (decision 3).
  - **Book:** instructor profiles with rating, rate, languages and next slots; pick a slot; **sponsored** (the sponsor's session allowance) or **paid** through Stripe Checkout (decision 2).
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
  - candidates can't be charged AI credits by any path;
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

1. **Ask fallback:** content first and free; general AI only when a sponsor pays from its pool; otherwise the content answer plus instructor options. Candidates never see credits.
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
