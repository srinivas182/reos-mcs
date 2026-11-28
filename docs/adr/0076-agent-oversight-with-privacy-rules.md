# ADR 0076: Agent oversight from the agent's own work, with privacy rules

- Status: Accepted (A16.1)
- Date: 2026-11-28 (Sprint 16; spec in docs/sprints/S16-spec.md)

## Context

Brokers need to know which agents need help and which deals are at risk before an agent leaves or a deal falls through. Retention tools (Brokerkit, with Relitix and Courted; Lone Wolf; BoldTrail) infer this from MLS production, sell it as an add-on, and see either all of an agent's business or none of it. REOS already holds the agent's own work:

- conversations and appointments (A11);
- the 13-week plan (A11);
- CE and SAE against TREC's rules (A5.2, A11.2);
- deals and their key dates from the transaction tools (B8, B9).

## Decision

1. **Signals** (`App\Brokerage\Oversight`), computed on read for each agent in the brokerage and its teams:
   - **quiet:** no sign-in, conversation or appointment for the brokerage's quiet days (default 14);
   - **no_deal:** no open deal, and none created or closed in the no-deal days (default 90);
   - **ce_behind:** CE hours left inside 60 days of the renewal. For SAE at the first renewal, also more than 30 hours left inside six months;
   - **plan_behind:** three or more tasks short of where the plan's past weeks call for. This week's tasks are still on time;
   - **deadline_missed:** an open deal's key date has passed without being done;
   - **new:** licensed, or on REOS, within the new-agent days (default 90).

   A new agent with no other signal isn't counted as needing help.
2. **Deals at risk:** an open deal with a key date within 72 hours, a key date already past, or no change for 30 days. Sorted by the next date.
3. **Privacy rules** (decision 1):
   - The broker sees counts, stages, sides and cities.
   - The property address shows only once a deal is under contract, when the broker supervises it.
   - Parties, client names, contact details and notes are never in the response.
   - Opening an agent's profile is audited.
   - The agent sees exactly the same summary in "What my broker sees" (`GET /api/v1/me/broker-view`), so nothing is hidden from them.
4. **Scope:**
   - The designated broker and office admins see the brokerage and its teams.
   - A team leader sees only their team, and can't open another agent (404).
   - Only the designated broker changes the thresholds, which are stored in `organisations.oversight_settings` and audited.
5. **Where:**
   - Web: Brokerage › Agents and deals (`/organisation/oversight`), and an agent's page.
   - Phone: Your brokerage → Agents and deals, and an agent; for agents, Me → What my broker sees.

## Alternatives considered

- **A nightly table of signals.** It would be fast to read but always a day old. Computing on read keeps the answer current; a brokerage's roster is small enough.
- **Showing clients' names to the broker.** Brokers supervise transactions, but contacts are the agent's own business. Under contract, the address is enough to supervise; the full file stays in the transaction tool.
- **Paid switch-risk data (Relitix, Courted).** It could be added later through the integrations register. The signals here come from work the agent already does in REOS.

## Consequences

- The broker's morning brief (A16.3) reads the same signals.
- More signal types (for example, CE complete but licence inactive) are added in one place.
- Thresholds are per brokerage; teams share their brokerage's.
