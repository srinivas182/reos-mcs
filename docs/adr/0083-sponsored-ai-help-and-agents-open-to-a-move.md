# ADR 0083: Sponsored AI help in broker offers, and recruiting licensed agents open to a move

- Status: Accepted (A17.2)
- Date: 2026-11-28 (Sprint 17; spec in docs/sprints/S17-spec.md)

## Context

Brokerages already make offers to candidates who opted in (A10.3b), and credit pools already sponsor AI for an organisation's own members (B5.3). A candidate isn't a member of a brokerage that wants to recruit them, so the brokerage had no way to pay for their study help. Licensed agents could turn on "Open to recruiting" (A6.3), but no brokerage could see them.

## Decision

1. **Sponsored AI help** (`App\Recruiting\SponsoredHelp`, `candidate_sponsorships`):
   - an offer can include AI credits (`broker_offers.ai_credits`, up to 5,000). When the candidate accepts, a sponsorship starts with that cap;
   - the candidate's AI use is paid in this order: the month's free credits, then the offer sponsorship (`candidatePayer` → `offer`, from the brokerage's credit pool, within the cap), then member sponsors, then credits they bought;
   - it **ends** when the candidate passes, or holds an Agent role at another brokerage (they picked another sponsor). This is checked whenever the sponsorship is used or shown, and audited;
   - candidates see only "Includes sponsored AI study help", never counts. The broker sees the pool, each candidate's use and status, and can change the cap (audited).
2. **Licensed agents open to a move** (`App\Recruiting\OpenAgents`, `recruiting_invites`):
   - only agents whose latest recruiting choice is on, licensed in the brokerage's states, and not with the brokerage or its teams;
   - shown by first name and initial, with closings in 12 months, their focus (mode) and why they match: strong producer, works where your agents work, or a newer agent;
   - **Invite to talk** opens a private chat in Messages (`Relationships` counts an invite as a working relationship), recorded once every 90 days;
   - **their current broker is never told:** the invite is audited only for the inviting brokerage, and nothing is shown or sent to the agent's own brokerage.

## Alternatives considered

- **A credit transfer to the candidate.** The candidate would see the credits and could spend them anywhere. A capped sponsorship keeps it to study help and lets the brokerage stop.
- **Sponsoring until the brokerage withdraws it.** If the candidate joins another brokerage, the first one would keep paying for a rival's agent. The sponsorship ends on its own.
- **Showing all MLS agents to recruiters.** That is what agents dislike about recruiting tools. Only those who opted in show.

## Consequences

- Buying credits for the pool stays on reos.com (B5.3), and the screen shows the balance.
- When an agent turns off "Open to recruiting", they leave every list at once. Chats already opened stay in Messages, where the agent can block.
