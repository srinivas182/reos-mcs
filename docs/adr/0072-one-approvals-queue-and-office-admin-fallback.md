# ADR 0072: One approvals queue, counted in working hours, with an office admin fallback

- Status: Accepted (A15.3)
- Date: 2026-11-30 (Sprint 15; spec in docs/sprints/S15-spec.md)

## Context

A broker clears three kinds of waiting work, each built in its own sprint and on its own page:

- agents' posts waiting for approval (B12.1, on Advertising);
- public agent pages waiting for approval (B13.2, also on Advertising);
- questions the virtual broker sent on (A12.1, on Virtual broker).

The broker journey (screens 7–12) shows one queue, oldest first, with the item on the right. It also asks that nothing waits forever when the broker is busy: the office admin steps in. A15.2 (ADR 0071) gave office admins and team leaders duties, so the queue can follow them.

Transaction coordinators arrive with the provider network (B15.2), so coordinator requests are not in REOS yet.

## Decision

1. **One queue, built from the existing sources** (`App\Brokerage\ApprovalsQueue`), oldest first, with no new tables for the items:
   - `PostApprovals::pending`, `AgentPages::pending` and waiting `BrokerQuestion`s;
   - each item carries its kind, agent, a summary, working hours waited, whether it's overdue, and its detail (the post's advertising checks, the page link, or the question with the virtual broker's answer and why it was sent on).
2. **Who sees what** follows the duties in ADR 0071:
   - the designated broker sees everything;
   - an office admin sees posts and pages with `approve_posts`, and questions with `answer_escalations`;
   - a team leader sees the same, for their team's agents only.

   Agents in teams now count for the brokerage too (`Memberships::holdersInTree`), so a team agent's post reaches the designated broker.
3. **Clearing an item** goes through the existing actions:
   - posts: approve, or return with a note;
   - pages: approve, or return with a note;
   - questions: answer, optionally adding the answer to the brokerage FAQ, which the virtual broker uses at once.

   Each action is audited as before. An item outside the person's queue is a 404.
4. **Working hours.** Waiting time counts 8 AM to 6 PM Central, Monday to Friday (`WorkingHours`). A post sent on Friday evening hasn't waited the weekend.
5. **Fallback.** `reos:approvals:fallback` runs every 15 minutes. An item waiting past the brokerage's limit is passed once to the designated broker and the office admins whose duty covers it, as an `approval_overdue` notification. The limit defaults to 4 working hours; the designated broker sets it from 1 to 40 (`organisations.approval_settings`). Each pass is recorded in `approval_fallbacks` (row-level security), so nobody is told twice, and audited as `approvals.fallback`.
6. **Where:**
   - Brokerage › Approvals on the web: a split view, with the fallback setting for the designated broker. Approvals is in the broker sidebar.
   - On the phone: Your brokerage → Approvals, and Review.
   - The Advertising and Virtual broker pages keep their own lists.

## Alternatives considered

- **A table of approval items, written whenever something waits.** This gives faster reads, but two copies of the same state drift apart. The sources are each capped at 100 rows and indexed, so building the queue on read is enough for a brokerage.
- **Clock hours.** These are simpler, but they would page office admins every Monday morning for items sent on Friday.
- **Escalating to the office admin by moving the item.** The broker would lose sight of it. Telling both keeps it in each queue that covers it.

## Consequences

- Coordinator requests join the queue as a fourth kind when coordinators arrive (B15.2 and later).
- An answer becomes an FAQ entry. Turning it into a brokerage rule instead (a "manual note") is left to Policies, where rules are checked against TREC as they're written (ADR 0071).
- Working hours are Texas hours. A brokerage in another time zone needs a setting when REOS opens a second state.
