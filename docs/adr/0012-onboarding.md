# ADR 0012: Onboarding, states and the free quiz

- Status: Accepted
- Date: 2026-11-21 (Sprint 3, A3.3; decisions in docs/sprints/S03-spec.md)

## Context

The mobile journeys (candidate screens 1–11, agent screens 1–3) start without an account: choose a path, pick a state, take a free 10-question quiz, see the score, then sign up in one tap and finish a 60-second setup. States other than Texas show "Coming soon" with a waitlist that the super admin can see (web journey screen 61).

## Decision

1. **States.**
   - A `states` table holds the 50 states and DC, each with a status: live, in preparation, waitlist or not planned.
   - Texas is live, with TREC, Pearson VUE, 180 qualifying hours and 18 CE hours per renewal. Other states' details are filled in by the super admin from each regulator, never guessed.
   - A5.1 adds licences and the full rules per state.
2. **Waitlist.**
   - `POST /api/v1/states/{state}/waitlist` records one entry per device (before sign-up) or per person.
   - The super admin's **States** screen (`/admin/states`, permission `states.manage`) shows how many are waiting and changes status. Each change is audited and can be undone.
   - Emails to waiting people come with notifications.
3. **Free quiz.**
   - `GET /api/v1/states/{state}/free-quiz` serves 10 questions in English or Spanish, each with its answer, explanation and source. The phone scores them at once, with no account needed.
   - The answers stay on the device and are saved with `POST /api/v1/me/quiz-attempts` after sign-up. The server re-scores them and returns strong, learning and start-here topics.
   - The questions are **samples** (12, marked `is_sample`) until the licensed Texas question bank arrives with content (A5.3).
4. **Choices on the account.**
   - `PUT /api/v1/me/onboarding` saves the path, target state, exam month ("Not sure" is null), minutes a day, language and reminder time. `complete: true` marks the setup done, so it never shows again.
   - Choosing "I want to become an agent" grants the candidate role on the person's own. An invite or code (A4.3) moves them to a school or brokerage instead.
   - Other paths are stored for their own journeys: licence lookup (A5.1), instructors, and providers.
5. **Reference data** (states and sample questions) is loaded by a migration, so every deployment has it.

## Consequences

- The phone (A3.3b) and the web (A3.3c) build the journey screens on these endpoints.
- Notification permission is asked only after a reminder time is picked (on the phone).
- The free quiz returns answers to the client on purpose: it's a sample for learning, not a graded test.
- **Phone (A3.3b), from the Candidate Complete Journey screens 1–11.**
  - A first launch opens **What brings you to REOS?** (screen 1); a phone that has signed in before opens Sign in. Brokers are sent to reos.com, where brokerages are set up.
  - State (2) → waitlist for states that aren't live (3) → welcome (5) → free quiz (6) → score and sign-up (7). Until sign-up the path, state and answers live only on the phone (`lib/onboarding.ts`, MMKV); they are saved to the account straight after sign-in, then cleared.
  - A candidate who hasn't finished setup always lands on **Your plan · 3 quick questions** (8), then **Your plan is ready** (9).
  - The daily reminder is a local notification (`lib/reminders.ts`), scheduled only after a time is chosen.
  - **Enter a code** (11) takes the code but says codes work from A4.3; nothing is redeemed yet.
  - `GET /api/v1/states` now includes `waiting` (people on the waitlist not yet told), for "N people in Louisiana are waiting".
- Known gaps against the journey, on purpose: the score screen has no "exam-ready in about N weeks" line (there is no honest estimate until real courses exist), and the plan's button is "Go to Today" rather than "Start my first lesson" (lessons arrive with A5).
