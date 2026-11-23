# ADR 0037: Deals from transaction tools

- Status: Accepted (the deals model, REOS deals, deadlines and screens, B9.1a)
- Date: 2026-11-23 (Sprint 9; spec in docs/sprints/S09-spec.md)

## Context

Agents run deals in transaction tools: Lone Wolf Transact, dotloop and SkySlope. Each tool shows only its own deals, its deadlines live inside it rather than in the agent's day, and each needs a separate login.

Sprint 9 brings deals into REOS:
- one deals model for every tool;
- every key date becomes a to-do and a calendar entry, with pushes before it;
- the Deals board, pipeline and alerts.

Decision 3 settled how REOS treats deals from a tool: they're read-only, and changes are made in the tool. Agents can also track deals in REOS without any tool.

## Decision

1. **One model** (`deals`, `deal_dates`) for every source. A deal has:
   - property, side (buy, sell, lease or both) and stage (active, under contract, closed or cancelled);
   - price (whole dollars), the agent's commission, parties with roles, and the document list (complete, missing or waiting);
   - key dates, typed: option period, financing, appraisal, inspection, survey, title commitment, closing, or another labelled date.

   `source` is `reos` or the tool's register id. A tool's deal is keyed by `(connection_id, external_id)`, as contacts are per tool (ADR 0027).
2. **Ownership:** a deal belongs to its agent (`user_id`) and is shown only to them.
   - `organisation_id` is the brokerage when the deal comes from a brokerage's tool. Row-level security applies, with personal deals allowed.
   - Brokers will see counts and deadlines for their agents' deals, never documents, as with contacts. That arrives with the brokerage tools in B9.1b.
3. **Read-only from tools (decision 3).** `Deals::receive` replaces a tool's deal with what the tool says, and REOS refuses edits, stage moves and deletes on it.
   - The board and the deal screens say "Read only: changes are made in Lone Wolf" and open the deal in the tool.
   - Deals tracked in REOS can be added, edited, dragged between stages and deleted, with Undo.
4. **Key dates are to-dos** (`related_type = deal`, `source = deal`) on their day, so they reach the agent's calendar through the to-do's calendar entry (B4.1, ADR 0018).
   - A date's id in the tool matches it, or else its kind and label.
   - A moved date moves its to-do and resets its pushes; a removed one removes its to-do.
   - A completed date completes its to-do; a cancelled deal drops its to-dos.
5. **Deadlines, checked hourly** (`reos:deals:deadlines`, at five past the hour; web journey rule).
   - A key date is due at 5:00 pm on its day in the agent's time zone, when Texas contract periods such as the option period end.
   - A push goes at 72 hours and at 24 hours before, once each, in the new `deal_deadline` notification kind under a new Deals group. Inside 24 hours, only the 24-hour push goes.
6. **Screens:**
   - **web Deals board** (screen 14): columns by stage, cards with property, side, price, the next deadline, the source and missing documents; a side panel for one deal;
   - **phone Deals pipeline** (Agent App screen 9): under contract, active clients, pending commission (the commission on deals under contract) and closed this year;
   - **phone Deal alerts** (screen 10): deadlines in the next 14 days and missing documents;
   - **one deal** on the phone.

## Consequences

- Every tool driver only has to normalise its transactions into the `receive` record. Deadlines, to-dos, pushes and screens come for free.
- A deal's to-dos are owned by the deal: editing their dates in the to-do list is overwritten at the next sync or save, so the deal is the place to change them.
- Coordinator sharing and contract review (web journey screens 14 and 15) aren't in Sprint 9.

## Alternatives considered

- **Writing stages back to the tools:** decision 3 chose read-only, which keeps one record of truth and the tools' own audit trails.
- **Midnight as the deadline:** too late for option periods, which end at 5:00 pm.
