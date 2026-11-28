# ADR 0070: The brokerage roster comes from TREC's sponsorship records; the broker chooses who pays

- Status: Accepted (A15.1b)
- Date: 2026-11-28 (Sprint 15; spec in docs/sprints/S15-spec.md)

## Context

Since A15.1a, every brokerage tenant stands for a TREC brokerage licence. TREC's nightly file lists each agent's sponsoring broker, so REOS knows who works at the brokerage without the broker typing anyone in. TREC publishes no emails or phones, though. Back-office tools (Brokermint, Lone Wolf) import a spreadsheet, and nothing checks it against the regulator.

Seats, who pays and AI credit pools already exist as tenant limits (A4.3, B5). Until now only REOS set them, on Tenant detail.

## Decision

1. **The roster is TREC's.** Bring in your agents (broker journey screen 3) lists every active sales agent or broker whose sponsoring licence is the brokerage's TREC number. Each row has the public facts (name, licence, expiry) and a state:
   - in the brokerage (holds the agent role here or on one of its teams);
   - invited (a pending invitation to their contact);
   - on REOS (an account with that Texas licence);
   - ready (an email or phone was added);
   - needs an email or phone.

   Nothing from an agent's account is shown to the broker beyond "on REOS".
2. **Contacts.** The broker pastes one line per agent: the TREC licence number, then an email or phone. A line for someone TREC doesn't list under the brokerage is refused. Contacts are kept per brokerage (`brokerage_roster_contacts`, with row-level security).
3. **Invite all.** One step invites everyone not in or invited, at their account's email (on REOS) or the contact added. It goes through the existing invitations (14 days, one reminder, seats checked all or nothing, duplicates and members skipped). Running it again sends nothing twice.
4. **Who pays** (screen 4). The designated broker chooses:
   - the brokerage pays;
   - each agent pays;
   - or **split**: the brokerage pays the seat and agents pay their own extras (a new payer value).

   They also choose whether AI credits are pooled across seats: credits per seat each month, with no roll-over. Seat counts and prices stay with the plan bought on reos.com, and nothing is sold in the app. Changes are audited (`tenant.seats_chosen`), and making the choice completes the "seats" setup step.
5. **Only the brokerage's own brokers.** The broker role must be held at the brokerage itself. A team leader (the broker role on a team) runs their team, not the roster or seats. A15.2 narrows this further to the duties the designated broker grants.
6. **On the phone:**
   - Me → Your brokerage: the checklist, the roster with Invite all, and seats and who pays;
   - Me → Verify your brokerage for anyone else, which ends on reos.com to choose the plan.

   Adding contacts in bulk stays on the web.

## Consequences

- The roster refreshes nightly with TREC's file. An agent TREC moves elsewhere drops off the roster, and A14.3 handles their move.
- The "split" payer is recorded and shown. Billing for agents' own extras already goes to each person (B5), so nothing else changes.

## Alternatives considered

- **Spreadsheet import:** names can't be checked, and agents who left get invited.
- **A public brokerage join link for any sponsored agent:** convenient, but any agent who sees the link could join. Invitations to known contacts keep the broker in control.
