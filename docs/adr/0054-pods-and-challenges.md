# ADR 0054: Accountability pods and brokerage challenges

- Status: Accepted (A13.2)
- Date: 2026-11-27 (Sprint 13; spec in docs/sprints/S13-spec.md)

## Context

New agents quit when they work alone. The market offers two kinds of peer accountability:
- **Coaching companies** (Tom Ferry, Buffini) put agents in small groups with a weekly call. Agents pay extra, the groups meet outside their tools, and the numbers they report are typed in by hand.
- **Leaderboards** (Sisu, brokerage dashboards) rank everyone on totals. Top producers always win, so the agents who most need the push stop looking.

REOS already has what both need:
- real activity: calls, emails, texts and notes in the CRM, appointments, CE hours and posts;
- video rooms that end on time;
- messaging with relationship rules.

## Decision

1. **Pods of five, matched inside the brokerage.** An agent opts in (`pod_requests`). The daily `reos:pods` run (06:10) matches on:
   - same brokerage (independent agents only with other independent agents), so a pod never spans brokerages and row-level security holds;
   - same state, goal mode, language and part-time flag.
   It fills an open pod first. Otherwise it waits for five, or makes a pod of three after 7 days of waiting. A part-time pod meets at 18:30; the others at 08:30, Monday, in Central time.
2. **Numbers come from activity, not self-reports** (`App\Agents\Activity`): conversations, appointments, clients and CE hours against the agent's plan targets for the week. Each member chooses what to share (`pod_members.share`), and it's shown only inside the pod. Each week every member can set one goal (`pod_goals`, unique per week).
3. **The call and messages reuse what exists.** Each week's 15-minute call is a `VideoSession` of kind `pod_call` with all members invited. The pod gets one group conversation, and `Relationships` lets pod members message each other.
4. **Leaving is undoable.** Leave sets `left_at`; rejoin restores it if there's still room.
5. **Brokerage challenges are opt-in and fair to beginners.** The broker sets a challenge (`brokerage_challenges`, audited `challenge.created`, up to 92 days, not in the past) on conversations, appointments, CE hours or posts. Only agents who join appear on the boards. There are two boards:
   - **total**;
   - **most improved** against the agent's own baseline (the same length of time just before the start, stored at joining).
   Beating the baseline is a **personal best**. Leaving hides the agent from the boards.

## Consequences

- The weakest agent can win the improved board, which keeps beginners playing.
- No one types numbers in, so the scorecard can't be gamed by claims; it can only be as good as the agent's logging.
- Pods made by the brokerage (choosing members by hand) are deferred; matching covers the done-when.
- Points for challenge winners come with A13.3 (goals and rewards).
