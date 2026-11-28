# ADR 0085: Sharing a deal with a coordinator, by part, until closing

- Status: Accepted (B17.1)
- Date: 2026-11-28 (Sprint 17; spec in docs/sprints/S17-spec.md)

## Context

Agents hand files to transaction coordinators in Open to Close or dotloop. They share the whole file, access isn't tied to closing, and the agent's broker can't see what the coordinator did. REOS already has deals and deadlines (B9), the transaction coordinator provider category (B15.1), and agent–provider connections and chat (B16).

## Decision

1. **Who:** an agent shares their own deal with a transaction coordinator they're connected with (an accepted provider request in the `transaction-coordinator` category). No one else can be chosen, and only the deal's agent can share, change or remove.
2. **Access switches** (`deal_shares.switches`): key dates, documents, parties and messages to the agent. Documents and dates are on by default; parties and messages are off (decision 4). The coordinator's API returns `null` for a part that's off. Parties show name and role only, never contacts.
3. **Access ends at closing:**
   - when a deal is saved with the stage closed or cancelled, every active share ends in the same request (`Deal::saved` → `DealShares::dealChanged`);
   - a daily sweep, `reos:coordinators:sweep`, ends shares on deals that closed some other way (a tool's sync);
   - each coordinator read checks the stage again, so a stale share gives 404 at once;
   - the agent can remove a coordinator at any time.
   An ended share stays as a record with `end_reason` (closed, cancelled or removed), and the agent sees it.
4. **My files** (`DealShares::board` and `file`): active files, nearest open deadline first, with missing documents and progress. A checklist tick (a document as `doc:<name>` or a date's id) is saved on the share, not on the agent's deal, so the agent's own data is never changed by the coordinator.
5. **Messages:** the coordinator's drafted message goes into a chat with the agent (B16 relationships), so the agent always has it. Needs the messages switch.
6. **Audit:** `coordinator.shared`, `coordinator.switches`, `coordinator.ticked`, `coordinator.messaged` and `coordinator.access_ended`, all on the deal's brokerage, so the agent and broker see them. A coordinator works outside the brokerage's tenant, so these events are written at platform level with the brokerage's organisation id.
7. **Tenancy:** `deal_shares` joins two people across tenants, so it has no `organisation_id`; the deal's tenant is checked in `DealShares`.

## Alternatives considered

- **Coordinator as a guest member of the brokerage.** Would give them the whole deal list and brokerage tools, and leave access to end by hand.
- **Coordinators edit the deal itself.** Their ticks would overwrite the agent's own status and dates. A separate checklist keeps the agent's data the agent's.
- **Ending access only in a nightly job.** Leaves a window after closing; the hook ends it at once and the job is a backstop.

## Consequences

- RESPA: coordinators handle paperwork and dates; the file says advice stays with the licensed agent. No fee changes hands through REOS.
- External coordinators who aren't on REOS (email-only access) are carried forward.
- Sharing from the phone is web-first for now; coordinators work their files on the web and the phone.
