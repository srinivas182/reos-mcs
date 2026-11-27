# ADR 0056: Feedback from agents after verified closings

- Status: Accepted (A13.3b)
- Date: 2026-11-27 (Sprint 13; spec in docs/sprints/S13-spec.md)

## Context

How an agent works with other agents (do they answer, keep the deal moving, behave professionally) matters a great deal, but nothing measures it.
- **Consumer review sites** (Zillow, RealSatisfied) rate agents only by clients.
- **Brokerages** hear about cooperating agents only by word of mouth.

Ratings between agents come with two risks:
- **Retaliation.** The rated agent can tell who rated them.
- **Fake ratings** from people who never worked together.

## Decision

1. **Only after a verified closing.** The rater's own deal must be closed, and the other agent must be a REOS user with the Agent role, found on the deal's parties by email under an agent role. Otherwise there's nothing to rate. Each rater can rate each agent once per deal.
2. **Three ratings before anything shows.** Below three, the rated agent sees only how many have arrived. From three, they see:
   - the averages;
   - the comments, with no rater, no deal and only the month;
   - a tip on the lowest area.

   Rows carry no organisation, because the two agents are usually at different brokerages; only the rated agent ever reads them.
3. **The rated agent is heard.**
   - They can reply to any comment.
   - They can report a rating. A reported rating leaves the average until REOS support looks; reports are audited and can be undone.
4. **Private by default.** The average shows on the public agent page only when the agent turns it on, and only with three ratings or more.
5. **The tip** comes from Claude using the comments when Claude is configured. The comments are data, never instructions; the tip has no names and no legal advice. Otherwise REOS uses its own tip for the lowest area. Tips are cached for a week, or until the comments change.

## Consequences

- Agents get honest, safe feedback from the people they actually closed with.
- The three-rating minimum delays feedback for agents who close rarely, which is the price of anonymity.
- REOS support's review of reported ratings uses the audit log for now; a review queue can follow if volume needs it.
