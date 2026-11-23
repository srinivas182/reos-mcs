# ADR 0044: Agent modes and Today

- Status: Accepted (modes and goal, A11.1a; plan, scorecard and Today, A11.1b; morning brief, A11.1c)
- Date: 2026-11-24 (Sprint 11; spec in docs/sprints/S11-spec.md)

## Context

Every licensed agent holds the same Agent membership, but a newly licensed agent chasing a first closing needs a different home screen from a producer building a team. The Agent Modes Journey settles this as four goals (Launch, Grow, Lead, Own) that decide what Today shows first. Flags tune any mode, and the app suggests a switch when signals appear. The agent always decides.

## Decision

1. **One profile per agent** (`agent_profiles`): the goal (null until picked), work-style flags, when they were welcomed, and a snoozed suggestion. Every change of goal is kept in `agent_goal_changes` (from, to, and whether it came from setup, by hand or a suggested switch), so My goal can show its history.
2. **Own unlocks** with a broker licence, or after the state's years licensed. Texas uses 4: TREC's broker applicants need four years' active experience as a sales agent or broker in the previous 60 months. The years are counted from the licence's licensed-on date (from TREC's data, B10.3a). Until then Own shows the date it opens.
3. **Suggested default:** Launch for agents licensed under a year with no verified closings, Grow otherwise.
4. **Suggested switches** come from verified closings, meaning deals closed in a transaction tool; deals typed into REOS don't count.
   - From Launch, the first closing suggests Grow.
   - From Launch or Grow, the state's count in 12 months suggests Lead (Texas default 12).
   - The thresholds are state rules on screen 62, under a new **Agent modes** topic, so they're set per state without a release.
   - "Not yet" snoozes that suggestion for 30 days.
5. **Flags:** part-time, bilingual, specialist, referral-only and returning. Part-time halves the plan's targets (A11.1b).
6. **Screens:**
   - **Phone:** a ModeCard on Today shows "Pick your goal" until a goal is set, then the mode badge (which opens My goal) and any suggested switch. The welcome, goal and flags steps are one screen; My goal is in Me.
   - **Web:** the same card on the dashboard, `/agent/setup` for the welcome steps, and Settings › My goal for agents.
   - Every feature stays reachable in every mode.
7. **Public agent pages** (`/agent/{handle}`, B10.2b) reserve `setup`, `mode`, `plan` and `brief`, so those paths are never a handle.
8. **The 90-day plan** (A11.1b) comes from templates in code, one per goal: phases, tasks and weekly targets. A plan is kept per agent and goal, so a switch starts a fresh plan and going back resumes the old one. Ticks are stored per task and week. REOS ticks "connect your tools" and "import your contacts" itself from the data, so the agent never ticks something REOS can see.
9. **Today is computed, not stored** (A11.1b): it reads to-dos (which carry CRM tasks and deal dates), the calendar and the plan on every request, and ranks them: overdue, deadlines within 72 hours, appointments, follow-ups, plan. Nothing new needs syncing, and "done" stays the to-do's own action, which already writes back to the source.
10. **The scorecard** counts what REOS already records (CRM activities and REOS notes, calendar appointments and showings, open deals, CE completions) against the plan's targets, and names the one metric furthest behind.
11. **The morning brief** (A11.1c) is text built on the server from Today's ranked items, read aloud on the device: expo-speech on the phone and the browser's speech synthesis on the web. Commands are recognized on the phone. There is no text-to-speech vendor, no audio file to store, and the same words serve as the transcript. The notification says only that the brief is ready; the content loads after the app opens, behind app lock.

## Consequences

- Today's ranked list, the 90-day plan and the scorecard (A11.1b) read the goal and flags from here.
- Templates live in code, so changing a plan is a release. A per-brokerage plan editor can come later if brokers ask for one.
- Conversations count only what's logged; an agent who calls from a personal phone without a CRM shows fewer conversations than they had. Connecting the CRM or logging a note in REOS closes the gap.
- Lead's team screens and Own's builder come with the team and brokerage sprints; the modes exist now so the home screen can change as they arrive.

## Alternatives considered

- **Separate memberships per mode:** more billing and more confusion. The journey's answer is one membership with add-ons on reos.com.
- **Switching modes automatically:** the journey is explicit that the agent decides, so REOS only suggests.
