# ADR 0063: Switching brokerage or team moves the agent; their own data stays with them

- Status: Accepted (A14.3)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

A Texas sales agent is sponsored by one broker at a time. When agents change brokerage at the big franchises, their contacts, CE history and marketing usually stay behind or are exported by hand. Until now REOS let a person accept an agent invitation from a second brokerage and end up holding two agent roles.

Most of an agent's data in REOS already belongs to the person, not to an organisation:
- licenses, CE completions and certificates;
- contacts the agent owns (`owner_type = user`);
- posts, the agent page, points, and role-play and video practice.

The brokerage's records are scoped to the brokerage: deals and transaction files, the compliance log (ADR 0058), its own contacts, mentor assignments and pods.

Licenses in several states already worked in one account (one license per state, A3). This sprint only checks that the CE wallet stays per license.

## Decision

1. **Accepting an agent invitation while an agent elsewhere is a move.**
   - The invite page on the web shows the move before the agent joins.
   - On the phone, the agent enters the invite code under Me, then "Join another brokerage or team", and sees the same preview (`POST /api/v1/me/moves/preview`).
   - The preview lists:
     - **Moves with you:** licenses, CE hours, certificates, your contacts, posts, points and practice.
     - **Stays with {old brokerage}:** deals and their files, and compliance records.
     - **Ends:** the mentor assignment, pods, and anything waiting for the old broker's approval.
2. **Confirmation is required.** Joining without `move` on the web, or `confirm` on the phone, returns a 422 on `move`. Once confirmed, joining the new organisation and leaving the old one happen in one transaction:
   - the old agent role is revoked (its seat is freed);
   - the old brokerage's mentor assignment ends, and its pod memberships end;
   - posts waiting for approval go back to drafts, and the page's approval request is withdrawn. The new broker's rules apply from then on.
   - `agent.moved` is audited in both tenants.
3. **Nothing is copied.** The agent's own data already belongs to the account, so it stays with it. The old brokerage keeps its records, but the agent can no longer see them once they've left; row-level security does that unchanged.
4. **A team change inside the same brokerage** only changes the team's role. Nothing ends, and nothing goes back to drafts.

## Consequences

- An agent can switch brokerage in minutes without losing CE, contacts or marketing, and brokerages keep the records TREC expects them to keep.
- Deals stay with the old brokerage. If both brokers agree a pending deal should move, that's a broker-to-broker handover outside this flow (B14.3 referrals).
- Holding an agent role at two brokerages is no longer possible through invitations.
