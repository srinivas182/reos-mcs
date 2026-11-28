# ADR 0077: CE compliance for the broker, and sponsorship join and release with a handover

- Status: Accepted (A16.2)
- Date: 2026-11-28 (Sprint 16; spec in docs/sprints/S16-spec.md)

## Context

TREC holds the sponsoring broker responsible for supervising its sales agents. At a first renewal a new agent needs 98 hours of SAE, which can't be deferred. Later renewals need 18 hours of CE. Brokers track this in spreadsheets, and find out when a licence expires.

REOS already works out each licence's requirement from TREC's rules (`CeRequirements`, A5.2), reads TREC's nightly file (B10.3a), and reminds agents itself at 60, 30 and 7 days (A11.2). Switching brokerage moves the agent and leaves deals with the old brokerage (A14.3), but nothing records a release, or who takes over the agent's open deals.

## Decision

1. **CE compliance** (`App\Brokerage\CeCompliance`): one row per agent with the Texas licence, renewal date, hours required, done and left, SAE (first renewal), assigned courses and the last reminder. Each row has a status:
   - **overdue:** the date has passed;
   - **behind:** the oversight rule (A16.1): any hours left inside 60 days, or for SAE more than 30 hours left inside six months;
   - **due:** inside 90 days;
   - **on track**, **done**, or **no Texas licence**.
2. **Remind all due agents in one action:**
   - every agent due, behind or overdue gets a reminder that names the broker, with SAE wording at the first renewal;
   - an agent already reminded by the brokerage in the last 7 days is skipped and counted;
   - each reminder is recorded (`ce_broker_reminders`) and the action audited.
3. **Course assignment:**
   - a live CE, qualifying or elective course (REOS's, or the brokerage's own) goes to chosen agents or everyone due, with an optional due date;
   - each agent is assigned once (`course_assignments`) and told (`course_assigned`);
   - an assignment is done when the agent's completion of that course is recorded.
4. **Sponsorship** (`App\Brokerage\Sponsorships`, `sponsorship_changes`):
   - **Join** is recorded when an agent accepts the brokerage's invitation.
   - **Release** is recorded with a date from today to 30 days ahead. Open deals must be **handed over** to another agent or broker of the brokerage. The deals stay with the brokerage (A14.3), now owned by the receiver.
   - The agent and the receiver are told, and the release is audited.
   - On the date the agent's role ends: at once if it's today, otherwise through `reos:sponsorships`, run daily after TREC's import.
   - A change is **confirmed** when TREC's file shows it: the agent under the brokerage's TREC licence after a join, and no longer after a release. Until then it shows "Waiting for TREC".
5. **Who:**
   - The designated broker, and anyone with "sponsor agents" (A15.2).
   - A team leader with that duty sees their team's CE but can't release (sponsorship is the brokerage's).
   - Office admins without the duty are refused.
6. **Where:**
   - Web: Brokerage › CE compliance (`/organisation/ce`), with the table, Remind all, Assign a course, and Sponsorship.
   - Phone: Your brokerage → CE compliance, and Sponsorship.

## Alternatives considered

- **Reminding every agent with any hours left.** Agents who are on track would be nagged. Due, behind or overdue, once a week, is enough.
- **Releasing at once, always.** Brokers often agree an exit date. A date up to 30 days ahead, applied by the daily run, matches how releases happen.
- **Leaving open deals with the departing agent.** The deals are the brokerage's (TREC), and someone must supervise them on the release date.

## Consequences

- The broker's morning brief (A16.3) reads the same CE statuses.
- Brokerage training (A16.3) reuses `course_assignments` for the brokerage's own courses.
- States other than Texas need their regulator's file before changes can be confirmed. Until then they stay "recorded".
