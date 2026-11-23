# ADR 0037: Deals from transaction tools

- Status: Accepted (the deals model, REOS deals, deadlines and screens, B9.1a; Lone Wolf Transact, B9.1b; dotloop and SkySlope, B9.2)
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

## Decision (Lone Wolf Transact, B9.1b)

7. **The connection.** Transact's API signs in with OAuth 2 client credentials: REOS's partner client id and secret, plus the subscription key on every request, scoped to one Lone Wolf client by `lwt_client_id`.
   - So a brokerage connects once on Brokerage integrations (screen 40) by entering its **Lone Wolf client ID**, which is checked with Lone Wolf at once.
   - The partner keys live in Settings › Integrations (register: `client_id`, `client_secret`, `api_key` for the subscription key).
   - Screen 40 gains a "connect with the brokerage's ID" form for such tools, which SkySlope's brokerage keys will reuse (B9.2).
   - Tokens are cached until just before they expire.
8. **Read:** every 15 minutes, with no webhooks in Transact's published API.
   - The client's users come first, then each user's transactions with their contacts, documents and offers, one user per page.
   - A transaction goes to the brokerage agent whose email matches the Transact user's (`RecordOwners`, as for Salesforce). The rest are counted and listed on screen 40.
   - Anything gone from Transact by the end of a full pass is removed, with its to-dos.
9. **Mapping** (`LoneWolfRecords`):
   - **Fields:** address, `asking`, `listingExpiration`, and the accepted offer's `purchasePrice`, `closingDate`, `finalWalkthroughDate` and `possessionDate`; contacts' names, roles, email and phone; the document list.
   - **Texas contract dates** (option period, financing, survey and the rest) come from the transaction's key dates when Transact carries them.
   - **Codes to confirm:** Transact marks its `type`, `phase` and `contactType` codes "needs documentation". The maps follow the partner documentation's order and are confirmed in the partner sandbox before going live.
   - **Write:** read-only (decision 3); document upload follows with B9.3 where the partner terms allow it.
10. **Who sees what:**
    - **The broker** sees counts only on screen 40: open deals and deadlines this week, never documents.
    - **Each agent** sees their own deals on the board and in Connections › Transactions (Connections screen 17): stage, documents complete, next deadline and commission, read-only and opened in Transact.

## Decision (dotloop and SkySlope, B9.2)

11. **dotloop is the agent's own.** dotloop accounts belong to people, so each agent connects with dotloop's OAuth sign-in (Public API v2; REOS's `client_id` and `client_secret` in Settings › Integrations). Its deals belong to the person who connected; no email matching.
12. **Profiles.** A dotloop account can hold several profiles (the agent's own, a team's, an office's).
    - Every profile syncs until the agent chooses some, in the connection's settings (`profiles` on `PATCH /api/v1/connections/{id}`, both web and phone).
    - Changing the choice removes the connection's deals and their to-dos and syncs again from the start: REOS doesn't record which profile a loop came from, and a clean resync is simple and correct.
13. **dotloop's webhooks.** One subscription per chosen profile for loop and participant events.
    - Each subscription gets a signing key REOS derives from the app key and the connection, never stored; deliveries are checked with HMAC-SHA1 over "timestamp.body", refused when older than five minutes, and deduplicated by event id.
    - The address carries a sealed connection id, as for Follow Up Boss. A delivery queues a sync, and the 15-minute poll stays as the backstop.
14. **dotloop mapping** (`DotloopRecords`): status → stage (sold or leased → closed, archived → cancelled); transaction type → side; named contract dates → key dates (any "option" date → option period); task-list items with a due date → dated to-dos with their done state; participants; folders' documents.
15. **SkySlope is the brokerage's.** A broker or admin user's access key and secret reach every file, so the brokerage connects once on screen 40 with both (stored together, encrypted, as the connection's token).
    - REOS logs in with its own client ID and secret, signed with HMAC-SHA256 over "clientID:clientSecret:timestamp" using the user's secret, and caches the two-hour session.
    - Sales and listings changed since the last pass are read every 15 minutes (`earliestDate`, `latestDate`), 10 a page, and matched to agents by the file's email (`RecordOwners`).
    - SkySlope's published API has no checklist, document list or webhooks, so the deal carries its escrow closing and listing expiry dates and no documents until B9.3.

## Consequences

- Every tool driver only has to normalise its transactions into the `receive` record. Deadlines, to-dos, pushes and screens come for free.
- A deal's to-dos are owned by the deal: editing their dates in the to-do list is overwritten at the next sync or save, so the deal is the place to change them.
- Coordinator sharing and contract review (web journey screens 14 and 15) aren't in Sprint 9.

## Alternatives considered

- **Writing stages back to the tools:** decision 3 chose read-only, which keeps one record of truth and the tools' own audit trails.
- **Midnight as the deadline:** too late for option periods, which end at 5:00 pm.
