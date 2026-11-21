# ADR 0017: Two-way calendar

- Status: Accepted
- Date: 2026-11-21 (Sprint 4, B4.1; spec in docs/sprints/S04-spec.md)

## Context

B3.1 synced Google Calendar one way into `calendar_events` (ADR 0013). Agents need REOS to be their calendar: showings, appointments, contract deadlines, coaching sessions and CE dates made in REOS must appear in the calendar they already use, and changes made there must come back. The Connections and Sync journey sets the rules: changes go back to the tool the record lives in, with Undo; REOS never deletes in their tools; one simple rule for conflicts (newest wins, decision 3). Follow Up Boss and Lofty sync both ways but resolve conflicts silently.

Sprint 4 decision 1 keeps calendars only: Gmail and Outlook mail are not read or sent.

## Decision

1. **One REOS calendar per person.** `calendar_events` gains an owner (`user_id`), a `source` (`reos` or the tool's register id), a `kind` (appointment, showing, deadline, session, ce, or `event` for the tool's own), notes, guests, a link to a contact or deal (for the CRM, Sprint 5), and write-back state (`etag`, `local_updated_at`, `push_state`). Events made in REOS keep living in REOS when a tool is disconnected; the tool's own events go with it.
2. **Calendar tools implement `CalendarDriver`** alongside `ConnectorDriver`: list calendars, add, change (only over the version REOS saw), read one, delete. Google is first (B4.1); Microsoft 365 follows the same contract (B4.2).
   - Google scopes are `calendar.events` and `calendar.calendarlist.readonly`, both sensitive (standard verification, no security assessment). The spec named `calendar.readonly` for the list; `calendarlist.readonly` is narrower and enough.
   - The register entry is now `google-calendar` ("Google Calendar"); a migration renames the id in stored rows. The callback address becomes `/integrations/google-calendar/callback`.
3. **Choosing calendars** (Connections and Sync journey, screen 16): each calendar is read in full, as **busy only** (times, nothing else is stored) or not at all. Only the main calendar is read by default. Events made in REOS go to one calendar per person (`write_calendar`, the main calendar of the first calendar connected), which must be writable and read in full. Changing a calendar's mode drops its events and reads it again.
4. **Writing back** (`CalendarEvents`, `CalendarWriter`, job `PushCalendarEvent`). Every change is saved in REOS at once and written to the tool in the background, so a slow tool never blocks a screen.
   - Added events get an id derived from REOS's id and version, so a retry after a lost answer finds the event (Google's 409) instead of adding it twice. The tool also stores REOS's id on the event.
   - Changes send `If-Match` with the version REOS saw. A 412 means it changed in the tool meanwhile: REOS reads the tool's version and the conflict rule decides.
   - Guests are not emailed unless the person asks (`notify_guests`): REOS never sends messages for them.
   - REOS deletes only events it made, in the tool too; deleting can be undone. A tool's own events can be changed (when the calendar allows) but not deleted from REOS.
   - When the tool is off, paused or needs reconnecting, changes wait; reconnecting writes them, and `reos:calendar:catch-up` (every 15 minutes) retries anything left. When retries run out the event shows "Not saved" with Try again.
5. **Conflicts: newest change wins, with a trail.** When an event changed in REOS (not yet written) and in the tool, the later change wins. The losing version is kept in `calendar_conflicts` for 30 days, shown on the event, and Undo makes it the newest change. A deletion in the tool always wins; REOS's version is kept so Undo can make it again. `reos:calendar:prune` (daily) removes versions and deleted events after 30 days.
6. **Sync across calendars.** The `events` stream reads each chosen calendar in turn, with a sync token per calendar (`GoogleCalendarCursor`). REOS's own writes echo back with the same version and change nothing.
7. **Push channels per calendar** (`ChangeChannels`), kept in `meta.channels`: missing ones are opened after a sync, channels for calendars no longer read are stopped, and channels ending within a day are renewed (new first, then the old one stopped) by `reos:connections:channels` (hourly).
8. **The Simulator is a fake Google** that REOS writes to (`simulator_records`), with four calendars (main, Family, Personal, a read-only holidays calendar). `reos:connections:simulate {id} --change-event="…"` changes an event as if in Google and delivers the change, for demos and the end-to-end test.
9. **API**: `GET /api/v1/calendar/events?from&to` (up to 62 days, with where new events go and events deleted in the tool that can be brought back), `POST`, `PUT`, `DELETE`, `restore`, `retry`, and `POST /calendar/conflicts/{id}/undo`. `PATCH /connections/{id}` takes `calendars` and `write_calendar`.

## Consequences

- The calendar screens (phone Today and New appointment, web dashboard card) read one API whatever the tool.
- A sign-in made before write access (B3.1's read-only scope) is refused by Google when REOS first writes (403 `insufficientPermissions`); the connection asks to reconnect, and the waiting change is written afterwards.
- Busy-only calendars never store titles, places or guests, which keeps personal calendars private by design.
- Recurring events are read as their instances (`singleEvents`); changing a series from REOS is not supported (an instance changes alone).
