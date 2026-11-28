# ADR 0069: A brokerage is created only by its TREC-verified designated broker, once

- Status: Accepted (A15.1a)
- Date: 2026-11-28 (Sprint 15; spec in docs/sprints/S15-spec.md)

## Context

Since A6.1b anyone with an account could buy a brokerage plan on reos.com and become the broker of a tenant with any name. A brokerage tenant controls its agents' advertising approvals, compliance log and sponsorship, so the person who creates it must be the brokerage's real designated broker. Back-office tools (Brokermint, now BoldTrail BackOffice; Lone Wolf) leave this to a sales call and never check the regulator.

REOS already imports TREC's public licence file nightly (B10.3a, ADR 0043). For each licence it has the type, status and "related licence". For an agent, the related licence is the sponsoring broker. For a business-entity broker (LLC, corporation or partnership), it is the entity's designated broker.

## Decision

1. **Verify before buying.** "Start your brokerage" begins with "Verify your brokerage" (broker journey screen 2).
   - The person enters their TREC broker licence number.
   - REOS finds the individual broker licence (BRK) in TREC's records and shows the name, status and expiry, and whether the name matches the account. It uses the same last name and first initial rule as licence checks.
2. **Their brokerages:**
   - every active business entity whose designated broker TREC lists as this licence;
   - their own licence, for a broker who sponsors agents themselves.

   Each shows how many active agents TREC lists under it.
3. **The role.**
   - A **designated broker** with an active licence in their own name gets a verification for one brokerage, valid for a day, and continues to the plan.
   - An **office admin or team leader** gets no verification. They are told to ask the designated broker for an invite, and no tenant is created for them.
4. **Once only.**
   - The tenant keeps the brokerage's TREC number (`organisations.trec_number`, unique). The sign-up carries the verification, and the verification is marked used when the tenant is created.
   - A brokerage already on REOS shows as "Already on REOS" and can't be claimed again.
5. **A name mismatch is refused.** It is not sent to a review queue this sprint. The person updates their account name or contacts REOS support.
6. **Setup checklist** (broker journey screens 1–6): six steps with their state and a link, on the broker's dashboard and at `GET /api/v1/organisation/setup`, for the brokerage's brokers only:
   - verified with TREC;
   - agents;
   - who pays for seats;
   - the policy manual;
   - the advertising policy;
   - mentors.
7. Each verification is audited (`brokerage.verified`).

## Consequences

- A brokerage tenant always stands for a real TREC brokerage, so A15.1b can build the roster from TREC's sponsorship records (agents whose related licence is the brokerage's number).
- The Simulator's TREC file gains a business entity, Bayou City Homes LLC (designated broker Grace Hill), with two sponsored agents. The demo Lone Star Realty stands for Linda Park's own broker licence (9012345).
- Schools and associations are unchanged: schools sign up as before, and associations join with the REOS team.

## Alternatives considered

- **Upload a licence picture:** slower, and it proves nothing TREC's data doesn't already prove.
- **Let anyone create and verify later:** a tenant could be used before its broker is known.
- **A review queue for name mismatches:** useful later, but this sprint support handles the rare case.
