# ADR 0053: Mentor program and paying with points

- Status: Accepted (A13.1a; A13.1b and A13.3 extend it)
- Date: 2026-11-27 (Sprint 13; spec in docs/sprints/S13-spec.md)

## Context

New agents need an experienced agent to call before their first offer.
- **eXp** pairs agents who have fewer than three closings with a certified mentor, paid from the mentee's first deals.
- **Real** and **Keller Williams** run cohort coaching.
- These programs pay mentors from commission splits rather than for their time, rarely rate them, and run outside the tools agents work in.

Our prospect asked for:
- mentors from the agent's own brokerage only, priced by the brokerage;
- a free assigned mentor;
- reward points that can pay for sessions.

REOS already had what's needed to build this:
- 1-on-1 bookings with checkout, no-show refunds, ratings shown after 24 hours and follow-up tasks (A10.2);
- live rooms that end on time;
- payouts through Stripe Connect (B5.2);
- a double-entry ledger with a points account (B5.3).

## Decision

1. **The brokerage runs the program** (broker web screens 21–23, `/organisation/mentors`). Mentors are people with the Mentor role at the brokerage. `mentor_settings` holds what the brokerage sets for each one:
   - topics;
   - the 30- and 60-minute prices;
   - whether points are accepted;
   - max mentees;
   - who can book (everyone at the brokerage, or assigned mentees only).

   The brokerage's prices replace whatever the mentor enters (`SessionBookings::saveOffer`). The mentor still sets their own hours. Every change is audited with before and after.
2. **Assignment with matching** (`mentor_assignments`). For each mentor with room, a score adds:
   - 3 for a shared language;
   - 2 for each topic that fits the agent's goal (launch: first deals, buyers, contracts; grow: listings, marketing, negotiation; and so on);
   - up to 3 for spare capacity.

   Assigning ends the earlier assignment, is audited, and tells both people, with a first session suggested. The agent can ask for a change, which tells the brokers.
3. **The assigned mentor is free** to the agent. Booking prices that mentor's sessions at 0 (`paid_with = free`). Mentors set to "assigned only" are hidden from other agents and refuse their bookings. Mentors from other brokerages are never listed.
4. **Points pay like money.**
   - 1 point = 1 cent inside REOS (1,000 = $10). A session paid with points spends them in the ledger (`points_redeemed`).
   - REOS also writes a paid order with the gateway `points`, so the existing revenue split gives the mentor their share, less the platform fee, in the next payout.
   - The `points` clearing account records that REOS funds the points it issues.
   - A refund (a cancellation more than 24 hours ahead, or a mentor no-show) gives the points back, cancels the mentor's share and reverses the order in the ledger.
   - Points have no cash value for agents. Daily reconciliation leaves the `points` gateway out (it has no outside party).

5. **The mentor's workspace (A13.1b).** Today, mentees and mentee detail are built from what REOS already holds: the plan, the scorecard, deal counts and sessions. Notes are the mentor's only, and goals become the mentee's to-dos. Mentors never see a mentee's contacts, clients or messages.
6. **Wrap-up and ratings (A13.1b).** The wrap-up is drafted from the shared notes (Claude or the notes' action lines) and sent as dated to-dos with the summary. A rating of 2 stars or less with a comment counts only after the brokerage keeps it.

## Consequences

- Mentor earnings show on the existing earnings screen (mentor screen 28) with no new payout code. The brokerage pays assigned mentors outside REOS during the pilot; REOS records those sessions.
- Point earning rules arrive in A13.3. Until then only grants (tests, the demo, and later bonus campaigns) give points.
- Mentors see a mentee's plan and what the mentee shares, never their CRM contacts (A13.1b).
