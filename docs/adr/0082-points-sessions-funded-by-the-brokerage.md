# ADR 0082: Points-funded mentor sessions: the brokerage funds them, the mentor is paid after the session

- Status: Accepted (A17.1)
- Date: 2026-11-28 (Sprint 17; spec in docs/sprints/S17-spec.md)

## Context

Agents earn points for daily goals (A13.3a) and can spend them on sessions with their brokerage's mentors (A13.1a). Until now, a points session wrote the mentor's payout line at once, as if a card had paid. REOS then sent the mentor cash for points that no one had paid for. The line could also go out in a weekly payout before the session happened.

## Decision

1. **The brokerage funds points spent on its mentors** (decision 1):
   - booking with points posts `points_session_funded` from the brokerage's points account. That account goes negative by what the brokerage owes, the same as campaign bonuses (A13.3a);
   - a refund (cancelled in time, or a mentor no-show) posts `points_session_unfunded`, and the agent gets the points back. Each happens at most once per session.
2. **The mentor is paid once the session happens** (decision 2):
   - a points session's payout lines start **held** (`PayoutLineStatus::Held`), and the weekly payout skips them;
   - when the session completes (the host joined and it ended, `reos:sessions:bookings`), the lines become **pending** and go out in the next weekly payout;
   - a refund cancels held lines the same way it cancels pending ones.
3. **The split is unchanged:** REOS's platform fee is 10%, and the mentor gets the rest (A13.1a).
4. **Who sees what:**
   - the mentor's earnings show the held amount as "paid once they happen" (web and phone);
   - the broker's Mentor program shows this month's points sessions and what the brokerage funded.

## Alternatives considered

- **REOS funds points.** Points would then be a cash cost to REOS for every session, with no limit. The brokerage chooses whether its mentors accept points, so it funds them.
- **Paying on booking, reversing on refund.** A transfer reversal can fail once the mentor has withdrawn the money. Holding until the session happens avoids that.
- **Charging the brokerage's card at once.** Many small charges, with refunds. The amount is collected on the brokerage's bill instead.

## Consequences

- The brokerage's points account shows what it owes for campaigns and sessions. Billing it on the brokerage's monthly invoice comes with the back office (Sprint 18).
- Card-paid sessions still pay out weekly whether or not the session has happened (B5.2b). The same hold could apply to them later.
