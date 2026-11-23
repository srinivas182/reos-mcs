# ADR 0032: The diagnostic, readiness and the study plan

- Status: Accepted
- Date: 2026-11-23 (Sprint 8, A8.3; spec in docs/sprints/S08-spec.md)

## Context

A candidate's first week should end with a plan they believe in. They find out where they stand, study in the minutes they chose, and see the plan on Today and on their calendar. The plan has to keep working when life gets in the way: a missed day, a change of minutes, a better result.

## Decision

1. **REOS's Texas diagnostic bank** (`database/data/diagnostic_tx.php`, loaded by a data migration and updated by key): 50 questions across the exam outline (agency 9, contracts 9, property ownership 8, finance 7, real estate math 7, Texas rules 10; decision 4). Each question has four options, the right answer, why and its source (TREC rules, the Texas Occupations, Property and Family Codes, the Texas Constitution, federal rules, or the licensing exam content outline), in English and Spanish. It belongs to REOS (`organisation_id` null); schools add their own through the question bank (screen 45, ADR 0031).
2. **Tests** (`App\Practice\Tests`, `test_attempts`):
   - The **diagnostic** has 45 minutes on the server's clock and runs in exam mode, with no feedback until the report.
   - **Topic quizzes** have 10 questions and run in practice mode: once a question is answered, its explanation and source show and the answer is locked.
   - Every tap saves the answer and flag at once, so closing the app loses nothing.
   - A timed test that runs out is scored as it stood at the end. This is checked whenever the test is read or answered, so no worker is needed.
   - Starting a test of the same kind that is still open returns the open one.
   - The report (screen 43, web 8) shows the score against the 70% pass line and the change since last time. It lists topics weakest first, ties broken by exam weight and then outline order, and gives every answer with its explanation and source.
3. **Readiness** (`App\Practice\Readiness`) is each topic's share right over the last five tests, weighted by the outline. It shows "not known yet" before the first test, never 0.
   - **Days to exam** count to the 15th of the chosen exam month.
   - **The streak** counts days in a row with seat time or a test, and keeps going until today is over.
4. **The study plan** (`App\Practice\StudyPlans`, `study_plans` and `study_tasks`) covers each day from today to the exam, on Texas time:
   - **Day one** is the diagnostic until it's done.
   - **Every other day holds exactly the person's minutes.** It has a 10-minute quiz on a weak topic two days in three, and the next lessons in order otherwise; a long lesson spreads over several days. A few minutes left over go to the day's quiz.
   - **Review days:** every seventh day, and the last three before the exam, are weak-topic quizzes only.
   - **Lessons** are only those the person can open (owned, or each course's free first lesson): qualifying courses first, then exam prep.
   - **Catch-up:** when a day is missed, its unfinished quizzes are carried into the next three days' quiz slots, still within the minutes; lessons simply continue from where they are. The exam date never moves. When the lessons no longer fit, Today says by how many hours, gently, and suggests 10 more minutes a day.
   - **Rebuilt** at setup, after a missed day (found when Today is opened), when the exam month, minutes or reminder time change, and when any test is submitted. The old plan is kept, inactive, with what was done.
   - **Done** is worked out from what actually happened: the diagnostic submitted, a topic quiz submitted that day, or a lesson completed or with that day's seat time.
   - **Moving a task** (web screen 6, by drag or a menu) keeps it between today and the day before the exam.
5. **The calendar:** the next 14 days are placed as study blocks (`kind` `study`) at the person's reminder time, 7 pm by default, through `CalendarEvents`. They go to the calendar chosen in Connections (B4.1), or REOS's own calendar without one. On a rebuild, blocks are moved or retitled in place, days without study lose theirs, and past days keep theirs. A block the person has changed in their own tool is left alone.
6. **Today for candidates** (`App\Practice\CandidateToday`; phone screen 12, web screen 4) shows:
   - readiness, days to the exam and the streak;
   - **Continue**: the lesson in progress at its second, or else the plan's next lesson;
   - today's tasks, each with why;
   - how far behind the plan is.

   Agents keep their own Today.
7. **Candidates only:** practice, the plan and Today return 404 for other roles (`PracticeAccess`). These are the learner's own actions, not admin actions, so they aren't audited as admin changes.

## Consequences

- The plan is a schedule of tasks rather than a forecast; readiness drives which topics come back, and A9.2's nightly readiness can rebuild it the same way.
- Portion tests and the full Pearson VUE simulation (A9.1) are new kinds of the same attempt model.
- The AI study mate behind the Ask bar is not built yet; on Today the bar opens search inside the person's lessons, which answers with where each passage comes from.

## Alternatives considered

- **A fixed calendar of lessons from enrolment:** simple, but it breaks the first time someone misses a day, and it ignores what they already know. Rejected.
- **Timing tests on the device:** easy to pause by closing the app. Rejected: the server's clock decides.
