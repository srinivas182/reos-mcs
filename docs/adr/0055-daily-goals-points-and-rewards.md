# ADR 0055: Daily goals, points and rewards

- Status: Accepted (A13.3a)
- Date: 2026-11-27 (Sprint 13; spec in docs/sprints/S13-spec.md)

## Context

New agents need a small, winnable target every day. Tools like Sisu and team boards gamify activity, but in two ways that fail agents:
- **The points do nothing**, or they're paid out in cash.
- **Some reward schemes pay agents for sending business to a lender or title company.** That's a kickback under RESPA §8.

REOS already had what's needed to do this properly:
- a points account in the double-entry ledger, where 1 point = 1 cent (B5.3), and mentor sessions paid with points (A13.1a);
- the agent's real activity: CRM conversations, CE lessons, to-dos and posts;
- modes (launch, grow, lead, own) with weekly targets (A11).

## Decision

1. **Goals come only from the agent's own activity.** There are four: conversations, a CE lesson, follow-ups, and a post. Nothing awards points for choosing, recommending or referring a service provider. The goal list is fixed in code (`PointRates::GOALS`), not configurable, so no setting can add one.
2. **The super admin sets the rates** (`platform_settings` key `points.rates`, audited):
   - points per goal;
   - a bonus for meeting every goal;
   - a daily cap;
   - REOS's targets per mode.

   The value is fixed: 1,000 points = $10 inside REOS, with no cash value.
3. **A brokerage can set its own targets per mode** (`daily_goal_settings`, row-level security). Setting a target to 0 turns that goal off for its agents.
4. **Points are awarded once per goal per day.** Each award is a ledger milestone (`goal:{day}:{key}`), so asking again, or the nightly run repeating, never pays twice.
   - `daily_goal_days` records what was done and whether every goal was met, which gives the streak.
   - The nightly `reos:daily-goals` run settles yesterday in each agent's own time zone.
5. **Bonus campaigns are funded by the brokerage, not REOS.**
   - Extra points are posted from the brokerage's own points account (`organisation:{id}:points`), so what a brokerage gave is always on its own account.
   - A campaign stops when its budget is spent; End now closes the budget.
6. **Redeeming is a sale paid with points.** An AI credit pack, or one period of membership that doesn't renew, becomes an order with gateway `points`.
   - The order goes through the revenue split and the money postings, exactly like the mentor sessions in ADR 0053.
   - The usual reports and reconciliation therefore cover redemptions with no special cases.

## Consequences

- Agents see a few small goals each day, a streak worth keeping, and points that pay for real things inside REOS.
- Brokerages can encourage CE or posting without paying cash, and see exactly what they funded.
- Membership paid with points is one period and doesn't renew on its own. Applying points to a card subscription's renewals would need the gateway's credit balance, which is left for later.
- Feedback from agents after verified closings is A13.3b.
