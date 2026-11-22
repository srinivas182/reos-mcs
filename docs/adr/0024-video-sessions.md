# 0024: Video sessions

**Status:** accepted for sessions, the room and notes (B6.3a) and the whiteboard, quick actions and scheduling on the phone (B6.3b), 22 November 2026. Recording with consent (B6.3c) extends it.

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
10. **Whiteboard: Excalidraw, not tldraw** (decided 22 November 2026). The spec named tldraw, but since tldraw SDK 4.0 production use needs a paid commercial licence (without a key the board stops rendering); Excalidraw is MIT-licensed, has no watermark and does what sessions need. On the web the board is drawn together live: only changed elements travel on the LiveKit data channel (`whiteboard` topic, reliable, in small chunks) and are merged by element version (`reconcileElements`); in a simulated room it works alone. A few seconds after the last change the room saves the drawing (Excalidraw JSON, up to 2 MB) and a PNG picture of it with the session (`POST /sessions/{id}/whiteboard`; the picture on the content disk, opened through a 30-minute signed link). Someone joining late starts from the saved drawing. Excalidraw loads only when the board is opened, and its fonts are served by REOS (copied at build time), never a CDN. The phone shows the latest picture (refreshed in the room) rather than drawing; after the session the picture stays with the notes.
11. **Quick actions** (host): share a lesson (live courses in the host's state; guests open it in Learn, where their own access applies) or send three practice questions on a topic from the question bank, answered in the room with the answer, why and its source. Shares are kept with the session and arrive at once (`session.shared` on each participant's channel).
12. **Scheduling on the phone** for instructors and mentors (B6.3b), with the same rules as the web: pick the type, title, a day in the next two weeks, a start time (half hours, 7 AM to 9:30 PM in the phone's zone), the length and who.
13. **Times are stored in UTC** whatever zone they arrive in (`VideoSessions::schedule` converts); found by a test in B6.3b.

## Consequences

- Rooms work across tenants without exposing directories; the participant check is in one place.
- LiveKit Cloud is billed per participant-minute; a closed room stops the meter at the booked end.
- The phone needs a development build with WebRTC (no Expo Go), already the rule.
- Recording (B6.3b) will need LiveKit egress to S3 and a consent record from everyone in the room before it starts.
