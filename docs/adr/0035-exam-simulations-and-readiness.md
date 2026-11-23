# ADR 0035: Exam simulations and readiness

- Status: Accepted (simulations and the starter bank, A9.1; readiness, A9.2)
- Date: 2026-11-23 (Sprint 9; spec in docs/sprints/S09-spec.md)

## Context

Candidates need to rehearse the real licensing exam, not just quizzes. In Texas, Pearson VUE gives the sales agent exam in two portions, each scored on its own:
- **National:** 85 items, 80 scored, 150 minutes, pass at 56;
- **Texas:** 50 items, 40 scored, 90 minutes, pass at 28.

A candidate who passes one portion retakes only the other. Sprint 9 decision 1 says to simulate this format exactly.

## Decision

1. **The format is a state rule, not code.** `exam.national.*` and `exam.state.*` (questions, scored, minutes, pass) are Number rules on screen 62, seeded for Texas with the Pearson VUE source, and read by `ExamFormats`. Another state or a changed format is a new rule version.
2. **Three new tests:**
   - the National portion, the state portion, and the full simulation (both portions back to back);
   - each in **Exam mode** (feedback at the end, no pause) or **Practice mode** (why after each answer, pause allowed).
3. **Timed sections.** Each portion is a section with its own clock on the server, and an attempt's `ends_at` is the current section's.
   - A simulation's second portion starts when the first is finished, or when its time ran out *and the candidate comes back*. The next clock never runs while nobody is there.
   - Only the current section's questions are sent, and a question from another section can't be answered.
4. **Pretest items.** A full set leaves the real unscored share (5 of 85, 10 of 50), chosen at random, out of the score, and the candidate isn't told which.
   - A bank too small for a full set scores every question, with the pass mark scaled in proportion, and the intro says so.
5. **New questions every attempt.** Questions are drawn per topic by the outline's weights within the portion, ones the candidate hasn't had first, then any in the portion to make up the count. The starter bank's sample questions count until approved (decision 2).
6. **Pause** (Practice mode only) stores `paused_at`. Resume adds the time away to the current section, and a paused test never runs out.
7. **Results** report:
   - each portion against its pass mark, the change since the last attempt of the same kind, and seconds used;
   - the pace the real exam allows (seconds per question), and a timing insight;
   - topics weakest first, and every answer with why, its source, whether it was scored, and the lesson it came from.
   - **Similar question** gives one more on the same topic, not from the test.
8. **The calculator** works like the test centre's: four operations, percent completing the sum ("a × b %" is b% of a), memory, and clear. It's the same on web and phone.

9. **The starter bank** (decision 2) is reference data loaded by a migration, like the diagnostic.
   - **Review:** each deployment reviews it once, by its school admin, on the Starter bank tab. Approving or retiring a question changes `is_sample` or `retired_at` on REOS's own row; edits change its content in place.
   - **Loading:** never overwrites a row already loaded, so a school's decisions and edits survive later releases. A corrected question ships as a new key, and the old one is retired.

## Readiness (A9.2)

10. **Readiness is a predicted pass chance**, not a share right. For each portion it is the chance of reaching the pass mark over the portion's scored questions on a new draw (80 at 56 and 40 at 28 in Texas, from the state rules).
    - **Topic ability:** each topic's share right is a Beta estimate (prior: one right, one wrong) from its answers over the last 120 days. Each answer's weight halves every 21 days.
    - **Portion share:** the outline-weighted mix of its topics.
    - **The chance:** a normal approximation of the binomial with a continuity correction, widened by the estimate's own variance. A few lucky answers therefore can't show 90%. As a guide, about 76% right on solid data gives 90% on the National portion.
    - **The headline figure:** readiness on Today and the ring is the lower of the two portions, because both must pass.
11. **Booking line (screens 54 and 56):** "Ready to book" unlocks when:
    - both portions are at 90% or better;
    - the last two full simulations passed both portions. This is the web journey's "readiness needs two simulations above the line".

    The Book link is the source URL of the state's exam format rule, so no vendor is hard-coded.
12. **What you're good at (screen 44):**
    - A topic's share and trend are shown only from 10 answers. The trend is the change against the snapshot from at least 7 days before.
    - The gain shown is the pass-chance points the weakest topic's portion would add if that topic reached 80%.
13. **Kept nightly and after every test** in `readiness_snapshots`, one row per candidate per day.
    - The nightly job runs at 00:10 Texas time, the candidates' time zone (`StudyPlans::TIMEZONE`). It records the day and rebuilds the study plan (reason `readiness`) when the weak topics have changed, so tomorrow's plan follows readiness.
    - The snapshots feed trends and the weekly report.

## Drills, fix plans and the hub (A9.2b)

14. **The smart drill** (screen 47) is a practice-mode test of 15 questions, samples included:
    - up to five recent misses (wrong in the last 30 days and not right since);
    - the rest from the three weakest topics, least-seen first.

    A topic drill is 10 questions on one topic. **Hint** strikes out one wrong option, the same one each time for that question in that drill. Hints are stored on the attempt (`hints`) so challenges (A9.3) can cost points for them. The streak is the run of right answers, counted back from the last one answered.
15. **Fix a weak topic** (screen 46) is a `topic_plans` row of 4 to 6 steps, built from the candidate's own misses on the topic:
    - **micro-lessons**, up to two: the lesson a missed question came from (its review item). Otherwise, the passage that course search finds for the question's key words, opened at its second. Only lessons the candidate can open are used.
    - **the misses, with why**;
    - **practice**: a 10-question topic drill, repeated when the plan would otherwise be short;
    - **a retest**: 10 questions not seen yet, in exam mode.

    Lessons and the misses are done once opened. Practice and the retest are done once submitted, and the retest completes the plan. The topic leaves the weak list when readiness reaches 70%. Flashcards join as a step in A9.2d.
16. **The hub's ranking** (screen 38) is ordered: the diagnostic if not done, plans under way, the smart drill, then fixing the weakest topic (when it has no plan).
    - A full simulation comes first at readiness 80%+. Otherwise it follows practice at 60%+, or within 45 days of the exam.
    - The readiness map is last.
    - Every item says why. Later modes join the ranking as they arrive.

## Math lab (A9.2c)

17. **Each kind of math problem is a generator** (`ProblemType`) that makes a problem from a seed with a small xorshift generator, so the same seed always gives the same numbers.
    - **Kinds:** prorations (Texas taxes paid at year end, the seller through closing, 365-day year), commissions, loan-to-value, area and acres, capitalization, net to seller, and points and interest.
    - **No answers on the screen:** the client gets the kind, the seed, the wording and the step labels. The server rebuilds the problem to check each step in order, with a tolerance for rounding.
    - **Wrong value:** returns the step's hint. **Show the answer** reveals it and counts as shown, not right.
    - **Records:** each problem's steps are kept in `math_solves`.
    - **Variants and links:** each solve offers a variant of the same kind with a new seed. A missed math question in the results links to its kind (`MathLab::kindFor`, from the question's wording).
    - **Tests:** each generator is tested against worked answers computed from its own wording, over 60 seeds.

## Flashcards and the speed round (A9.2d)

18. **Flashcards are scheduled by FSRS-5 with its published default weights**, at 90% target retention. The ratings are Again, Hard and Got it.
    - **Again** brings a card back in ten minutes, so a miss returns within the same two-minute burst.
    - **Same-day reviews** use FSRS's short-term stability.
    - The same algorithm is written twice, `Fsrs.php` and `apps/mobile/src/lib/fsrs.ts`, and both are tested against the same values.
    - **On the phone:** the whole deck is kept in MMKV and reviews are scheduled there, so flashcards work fully offline. Each review is queued with a UUID.
    - **Sync:** the server applies synced reviews oldest first, each once by its UUID, and counts a review older than the card's last one as now. It stays the record.
    - **Limit:** up to 15 new cards a day.
    - **Where cards come from:** the course's approved flashcards for lessons started; **Save as flashcard** from a check-in, an Ask answer or answer review (once per question); and speed-round misses.
19. **The speed round** is 60 seconds of true or false, made from questions: is this option the answer? It is right half the time.
    - The server keeps the truths and scores the taps.
    - Misses become flashcards.
    - Each answer counts a quarter of a test answer towards readiness ("lightly").
20. **Fix plans** gain a two-minute flashcards step when the topic has cards. **The hub** ranks flashcards due (with the count) and the speed round after the drill and fix plans.

## Consequences

- Readiness (A9.2) reads `by_topic` with portions mapped from topics (state rules to the state portion), and `by_portion` from simulations for the booking line.
- Seat time is unaffected: tests aren't lessons.
- The Pearson VUE numbers must be re-checked against the current candidate handbook before the pilot. They are state rules, so this needs no deploy.

## Alternatives considered

- **Scored-only practice sets (80 and 40):** simpler, but not the real experience. Rejected by decision 1.
- **One clock for the whole simulation:** doesn't match the real exam, where each portion is timed separately. Rejected.
