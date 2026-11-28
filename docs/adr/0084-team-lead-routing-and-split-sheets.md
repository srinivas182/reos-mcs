# ADR 0084: Team lead routing with claim timers and fallback, and split sheets

- Status: Accepted (A17.3)
- Date: 2026-11-28 (Sprint 17; spec in docs/sprints/S17-spec.md)

## Context

Team leaders route leads in Follow Up Boss: "first to claim" by push, with a claim window of at most 30 minutes, then fallback groups. They sign splits on paper or by email. REOS already has teams inside brokerages (A15.3), leads that land in an agent's CRM (B14), and messages.

## Decision

1. **Routing rules** for each team (`organisations.lead_routing`, `App\Teams\LeadRouting`):
   - the sources they apply to (empty means all); first to claim or round robin;
   - a claim time of 1–30 minutes, with 5 as the default (decision 3);
   - a fallback: round robin, the default, or the team leader;
   - off hours, 8 pm to 8 am Central by default.
2. **The rotation:** every team agent is in it unless the team leader switches them off, with the languages they take.
3. **First to claim:**
   - active agents who speak the lead's language are told at once (`LeadOffered`, kind `new_lead`), or everyone in the rotation when none does;
   - the first claim in time wins, under a lock;
   - a later claim gets "Another agent claimed this lead first." A claim after the time gets "The time to claim this lead is up."
4. **Fallback:** `reos:teams:leads` runs every minute. A lead nobody claimed goes to the agent in turn who wasn't offered it (or anyone, if everyone was), or to the team leader.
5. **Round robin, and sources outside the rules:** assigned at once to the active agent who got one longest ago.
6. **Off hours:** routing still runs, and the lead gets a reply that names the team and offers a call, in English or Spanish.
7. **Every assignment** puts the lead in the agent's CRM, with a note, a drafted first message and a follow-up task (B14). It's audited for the team: `team.lead_claim`, `team.lead_round_robin`, `team.lead_fallback` or `team.lead_leader`.
8. **Split sheets** (`App\Teams\SplitSheets`, `split_sheets`):
   - the team agreement sets the brokerage's and the team's percent (default 20/30), and the agent gets the rest;
   - the team leader sends a sheet for a closing (`SplitSheetSent`, kind `split_sheet`);
   - the agent signs it, or asks a question, which opens a chat with the team leader;
   - signed sheets go to QuickBooks (B17.3).
9. **Who:**
   - team leaders run routing and send sheets, for their team only;
   - agents claim leads offered to them and sign their own sheets.

## Alternatives considered

- **Notifying agents one by one (sequential offers).** Slow for hot leads. First to claim gets to the fastest responder, and the fallback catches the rest.
- **Keeping routing in the CRM.** Our agents' CRMs differ (B8), so routing lives with the team and writes to whichever CRM the agent uses.
- **E-signature for split sheets.** A split sheet is an internal agreement under a signed team agreement. A signed-in, audited Sign is enough, and payments run through accounting.

## Consequences

- Lead sources can post to `POST /api/v1/team/leads` as they're connected (Zillow, Realtor.com). Until then the team leader adds leads.
- Bonus campaigns scoped to a team are carried forward. Campaigns stay brokerage-wide for now (A13.3a).
