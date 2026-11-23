# ADR 0030: Seat time and the course player

- Status: Accepted
- Date: 2026-11-23 (Sprint 8, A8.2; spec in docs/sprints/S08-spec.md)

## Context

TREC and other state regulators approve CE and qualifying courses for a number of hours, often with a presence rule (check-ins every so many minutes, a share of the lesson that must be watched) and a maximum number of hours a day. A browser or phone can be made to claim anything, so what counts has to be decided by the server from evidence it can check.

## Decision

1. **Heartbeats, decided by the server** (`App\Content\Player\SeatTime`).
   - The player sends a heartbeat every 15 seconds: position, playing, visible (tab or app in front), a viewing id and the learner's time zone. Web: `POST /learn/lessons/{lesson}/heartbeat`; phone: `POST /api/v1/learn/lessons/{lesson}/heartbeat`.
   - Only the **real time between two heartbeats of the same viewing** counts, and **at most 20 seconds** per heartbeat. Speeding the video up, or sending beats faster, never adds time.
   - A silence longer than 45 seconds, or a new viewing id, starts again: the gap doesn't count (`gap`).
   - Nothing counts while paused (`paused`), hidden (`hidden`), with a check-in waiting (`checkin_due`) or past the daily maximum (`daily_max`). Each reason is shown to the learner in plain words.
   - A lesson with no video counts while the page is open and in front (reading slides or text); check-ins still apply.
2. **The rules come from the approval in force** (`CourseApproval`, A7.1): check-in minutes, presence share and maximum hours a day. Without one, REOS's defaults: a check-in every 15 minutes, 100% presence, no daily maximum.
3. **Required time** for a lesson is its minutes × the presence share (at least a minute).
4. **Check-ins.** An approved AI lesson (ADR 0031) uses its approved check-ins at the point in the script where they fall; otherwise there is one every N minutes. Each is paired with one of the lesson's approved questions when it has them, else it is a presence check ("Are you still here?"). The video stops and nothing counts until it is answered; the dialog can't be dismissed. A wrong answer still counts as present and shows why, with its source.
5. **The daily maximum** is per course and per day in the learner's own time zone (`seat_time_days`), checked inside the same transaction that counts, with the progress row locked.
6. **Completion.** A lesson completes when its required time has counted and every check-in is answered. A course completes when every learnable lesson completes; a CE or qualifying course then writes a `CeCompletion` (source `reos`), which renewal tracking (A5.1) already reads.
7. **Every stretch is logged** (`seat_time_events`), counted or not, with its reason and source (web or app). Rows with the same outcome within 30 seconds are merged, so the log stays small and auditable. A regulator audit can show exactly what counted and why.
8. **Progress** (`course_progress.percent`, 0–100) is shown on Learn and is what the refund policy reads: a refund is within policy only while no course the order unlocks is past the super admin's maximum progress (25% by default, screen 66).

## Consequences

- Seat time can't be inflated from the client; the worst a tampered client can do is claim 20 seconds of every 15–20 that really passed while the page was open.
- Offline viewing (A8.2b) records stretches on the phone and replays them later; they are checked against the same rules, with signed timestamps.
- "Save as flashcard" from a wrong check-in answer moves to A9.2, with the flashcard deck.

## Alternatives considered

- **Trusting the player's watched-time counter:** simple, but anyone could post "7200 seconds". Rejected.
- **Counting video position progress:** fails with speed changes and seeking. Rejected; position is recorded only for resume.
