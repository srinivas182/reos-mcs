# 0024: Video sessions

**Status:** accepted for sessions, the room and notes (B6.3a), 22 November 2026. The whiteboard, recording with consent and quick actions (B6.3b) extend it.

## Context

Instructors and mentors teach and coach one to one and in live classes. Today that happens in Zoom or Teams links pasted into texts: nothing ties the call to the student's course, CE or notes, anyone with the link can join, and calls overrun. REOS needs rooms that only the people in the session can enter, on the web and the phone, that end on time, with notes kept with the session. The Sprint 6 decision (22 November 2026) is LiveKit Cloud.

## Decision

1. **LiveKit Cloud for media**, set up in Settings › Integrations (`livekit`: server URL, API key and secret). REOS never routes media itself. The web uses the LiveKit React components; the phone uses LiveKit's React Native SDK with its Expo config plugins (development builds; WebRTC is registered once at start).
2. **Sessions belong to their participants**: `video_sessions` (1-on-1 or live class, title, agenda, booked start and end, status scheduled, live, ended or cancelled, shared notes) and `video_session_participants` (host or guest, when they joined). Like conversations, a session spans tenants (a school's instructor and a brokerage's agent), so row-level security isn't used; only participants can open one (404 for anyone else), checked in `VideoSessions` and before a room token is minted.
3. **Who schedules with whom.** Instructors and mentors schedule (web, for now) with people they work with — the same relationships as Messages (ADR 0023). A 1-on-1 has one guest; a live class up to 100. Lengths are 15, 30, 45, 60, 90 or 120 minutes; a host can't double-book. Booking by students, payment and availability arrive with the marketplace sprints; the room doesn't change.
4. **Tokens per person, per room, until the end.** Join opens five minutes before the start. REOS mints a LiveKit token (HS256 with the API secret) for one person and one room (`reos-<session>`), valid until the booked end. In a 1-on-1 both publish; in a live class only the host publishes (speakers arrive with B6.3b); the host is room admin. The token is fetched when the person taps Join and never sits in a page.
5. **Ends on time.** Every minute `reos:sessions:tick` ends sessions whose booked end has come: the status becomes ended, LiveKit's `DeleteRoom` closes the room for everyone, and `session.ended` on each participant's channel closes open screens at once. Screens also close themselves at the end and warn at two minutes. The host can end early or cancel before the start; guests are told (`SessionBooked`).
6. **Reminders.** "Starting in 10 minutes" (`SessionStarting`) goes to everyone once; it is urgent, so it arrives in quiet hours and on the phone as push (ADR 0023).
7. **Notes** (screen 16): one set of notes kept with the session that everyone in it can read and edit, with who changed them last. Sessions and notes are in "Download my data".
8. **Simulated rooms.** Outside production, until LiveKit is set up, Join returns a simulated room: the same screens, timer and rules with name tiles for video, so the journeys can be demonstrated and tested. Production refuses to join without LiveKit.
9. **Behind the `video_rooms` flag** (ADR 0022): off means a 404 on the pages and API and no menu entry or Home card.

## Consequences

- Rooms work across tenants without exposing directories; the participant check is in one place.
- LiveKit Cloud is billed per participant-minute; a closed room stops the meter at the booked end.
- The phone needs a development build with WebRTC (no Expo Go), already the rule.
- Recording (B6.3b) will need LiveKit egress to S3 and a consent record from everyone in the room before it starts.
