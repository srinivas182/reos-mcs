# ADR 0078: Brokerage reports matched to the transaction tool, read receipts, and the broker's morning brief

- Status: Accepted (A16.3)
- Date: 2026-11-28 (Sprint 16; spec in docs/sprints/S16-spec.md)

## Context

Brokers pay commissions from their transaction tool (SkySlope, dotloop, Lone Wolf). A production report that disagrees with it is worse than none, so brokers keep a spreadsheet alongside. REOS already syncs each tool's deals (B9.1, B9.2) and records deals agents keep only in REOS.

Announcements show "Read by 82 of 140" (B6.1b), but the admin can't see who hasn't read one or nudge them. Course assignments arrived with CE compliance (A16.2), but there's no view of each course's progress. The agent has a morning brief (A11.1c); the broker opens four screens to find out what needs them.

## Decision

1. **Closings report** (`App\Brokerage\Reports::closings`):
   - the agents' deals closed in a period: this month, last month, this quarter, this year or last year;
   - totals, and by agent, team and month (every month of the period, with zero months);
   - **matched to the tool:** a deal synced from a transaction tool counts under its connection, with the last sync; a tool not synced for a day is marked **stale**. Every connected tool shows, even with nothing closed;
   - a deal closed only in REOS is a **difference**, listed by property, agent, price and date, so the broker can add it to the tool or check it;
   - the transaction tools are read from the integrations register (category `transactions`), never a list in code.
2. **Training** (`Reports::training`): each course the brokerage assigned, with assigned, done and overdue counts, and CE hours for CE courses (from `course_assignments`).
3. **Read receipts** (`Announcements::readers`, `remindUnread`):
   - who has read an announcement and when, unread people first;
   - **Remind** sends the unread a "Reminder:" announcement notification, at most once a day (`announcements.reminded_at`), audited `announcement.reminded`;
   - only the sending organisation's admin (the existing sender rule).
4. **The broker's morning brief** (`App\Brokerage\BrokerBriefs`):
   - agents who need help (top three, with their signals), deals at risk (top three), approvals waiting and past the limit, agents due for CE (only for those with "sponsor agents") and closings this month;
   - counts and stages only, like oversight: never a client or an address;
   - on the broker dashboard (`brokerBrief`), `GET /api/v1/organisation/brief`, and the phone (`brokerage/brief`);
   - `reos:brokerage:briefs` on weekdays at 7:30 Central tells each broker their brief is ready (kind `morning_brief`, push only, nothing private on the lock screen), and only when something is in it.
5. **Who:** reports and the brief are for brokers (designated broker, office admins, team leaders); a team leader sees their team.

## Alternatives considered

- **Querying the tool live for its own report.** Each tool reports differently, and the API limits are tight. The synced deals already mirror the tool; showing the last sync and marking stale ones is honest and fast.
- **Hiding REOS-only closings.** They are the differences the broker most needs to see.
- **Emailing the brief.** It would arrive after the morning has started, and the agent's brief is push-only for the same reason.

## Consequences

- Commission splits and payouts per closing can build on the same report when the back office arrives (Sprint 18).
- Lone Wolf and dotloop closings count the same way once synced; a new transaction tool in the register counts with no code change.
- Reminders reuse the announcement notification, so people's notification choices apply.
