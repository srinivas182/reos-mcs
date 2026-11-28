# ADR 0062: Course picks from renewal, practice and mode; audio-only lessons with spoken check-ins

- Status: Accepted (A14.2)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

Agents learn in the car and between showings. CE providers sell desk-bound video and text courses, and every agent gets the same catalogue whatever their renewal date, stage or weak spots. REOS already knows all three:
- the renewal date and CE hours owed (CE wallet, A11.2);
- the agent's mode (A11.1);
- the three fixes from role-play and video practice (A14.1).

Seat time (A8.2, ADR 0030) has counted only while the video plays and the page or app is in front. Texas online CE needs the school's approved delivery method, so audio-only study can count only once the school confirms it.

## Decision

1. **Course picks** (`GET /api/v1/learn/picks`, and `picks` on the web Learn page). Up to six live, unfinished courses in the agent's state, in this order:
   1. CE courses, when the license renews within 120 days and CE hours are still owed;
   2. courses that teach the skills behind the latest practice's three fixes, then the skills the assessment found weakest. The `topic` from a fix's lesson link comes first;
   3. courses that suit the agent's mode.

   Each pick says why it was chosen, whether it can be listened to, where to start, and how far the agent has got. Other roles get none.
2. **Telling REOS what a course teaches.** The content team ticks what a course teaches (`courses.skills`: the role-play lesson codes) and who it suits (`courses.modes`) in the course builder. Both changes are audited.
3. **Audio-only lessons** (`GET /api/v1/learn/lessons/{id}/listen`):
   - The response holds the lesson's text by heading, or its video's words when it has no text, about how long it takes to read at 150 words a minute, whether audio-only study counts, and the seat time so far.
   - The phone reads the lesson aloud. Heartbeats carry `mode: audio`, and then **being in front isn't required**. Every other rule still applies: real time only, gaps, the daily maximum, and the check-ins.
   - A check-in pauses the reading. The phone asks it aloud and listens on the device. The learner answers with "two", "option three", "B", or the answer's own words; a presence check-in takes "I'm here". A tap always works too.
   - Logged stretches carry `app-audio` as their source.
4. **The school confirms.** For CE and qualifying courses, audio-only seat time counts only when an approval in force has **"Audio-only study counts as seat time"** ticked (`course_approvals.audio_allowed`, off by default). The school sets it on the approval form. Until then the phone still reads the lesson but says it won't count (`audio_not_allowed`). Elective and test-prep courses can always be listened to.

## Consequences

- Agents get what matters to them first, and CE can be finished on the road once the school has confirmed it.
- Picks are only as good as the content team's tags: an untagged course is never picked for a skill or mode.
- Reading with the screen off depends on the phone letting speech play in the background. If the phone stops it, the reading pauses and the next heartbeat shows paused.
