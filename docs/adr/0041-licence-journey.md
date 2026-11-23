# ADR 0041: The licence journey

- Status: Accepted (tracker, booking and result, A10.3a)
- Date: 2026-11-23 (Sprint 10; spec in docs/sprints/S10-spec.md)

## Context

Candidates leave REOS's help at the moment it matters most. Today they have nothing for:
- the regulator's steps (application, fingerprints, exam, sponsor);
- the one-year application window;
- booking the exam;
- the exam day itself;
- a missed portion.

The journeys add the licence journey (16), Ready to book (15), the exam-day kit (14), result entry and the retake plan (19). Later come pass announcements, broker offers and switching to agent (A10.3b).

## Decision

1. **Steps come from the state's rules** (screen 62), not code:
   - `licensing.steps` is a new rule type, `steps`: each step has a code, a title, what to do, and the official page. Super admins edit it like any rule.
   - `licensing.application_months` is the window (Texas: 12, "Your application expires one year from the date it is filed").
   - `exam.attempts` is the number of attempts before more education (Texas: 3; then 30 more hours of qualifying education, or 60 if both portions were failed).
   - `exam.day_rules` holds the testing vendor's ID, arrival and personal-item rules.
   - Texas is seeded from TREC's sales agent page and the Pearson VUE Texas candidate handbook. Screen 62 now also labels the exam-format rules from A9.1.
2. **The candidate's own record** (`licence_journeys`, one per candidate) holds:
   - check-offs with dates; checking off the application starts the window;
   - the booked exam: date, time and test centre;
   - the exam-day checklist;
   - every attempt per portion;
   - weak areas from the score report;
   - which reminders were sent.

   REOS doesn't read the regulator's records here; that is the switch to agent in A10.3b.
3. **Ready to book** is the readiness line from A9.2: 90% on both portions and two recent passed simulations. Booking itself is a hand-off to the vendor's site, as there is no booking API. The exam date the candidate enters replaces the month chosen at sign-up:
   - the study plan is rebuilt toward it;
   - reminders go out a week before and the day before (with the kit);
   - from the day after, a nudge to enter the result, until it's entered (`exam_reminder`, daily at 8:15 Texas time).
4. **Exam-day kit:**
   - when and where, with Directions (a maps search);
   - a checklist: two IDs, confirmation, a final simulation, flashcards, sleep;
   - the vendor's rules from the back office;
   - readiness;
   - a two-minute calm-down: paced breathing read aloud by the device's own voice (the browser's speech or the phone's), with no recording and no vendor.
5. **Result:**
   - The candidate enters passed or didn't, per portion.
   - A passed portion is kept and can't be entered again.
   - A missed portion clears the exam date (it is retaken on a new one) and rebuilds the plan. It gets a **retake plan**: the weak areas the candidate ticks from the score report (used for nothing else), then readiness's weakest topics of that portion, each opening its Fix a weak topic plan, plus the attempts left.
   - Once both portions are passed, the exam step checks itself off.
6. Dates are Texas calendar days, the same days the study plan counts.

## Consequences

- Adding a state means adding its steps and rules on screen 62; no code changes.
- The score report isn't uploaded or read, so nothing sensitive is stored. If OCR (B10.3) can later read the vendor's report, it could fill the weak areas.

## Alternatives considered

- **Steps in code per state:** every regulator change would need a release.
- **Uploading the score report:** a scan to keep and protect for a few ticks the candidate can make themselves.
