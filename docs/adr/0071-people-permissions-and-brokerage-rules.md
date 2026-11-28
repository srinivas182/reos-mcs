# ADR 0071: Duties per person in a brokerage, and brokerage rules checked against TREC as they're written

- Status: Accepted (A15.2)
- Date: 2026-11-28 (Sprint 15; spec in docs/sprints/S15-spec.md)

## Context

Until now, everyone with the broker role in a brokerage could do everything: change the advertising policy, upload the manual, approve posts, answer agents' questions, invite agents and admins. The broker journey's "Who sees what" table separates three roles:

- **The designated broker:** everything.
- **Office admins:** approve posts and answer policy questions only if allowed. They never change policies, sponsorships or roles.
- **Team leaders:** the same, for their own team only.

The policy manual is checked against TREC when uploaded (A12.1, ADR 0047). But rules the brokerage writes in REOS itself, such as FAQ answers, were never checked.

## Decision

1. **Titles.** Each broker-role holder in a brokerage is one of three (`brokerage_staff`, with row-level security):
   - **Designated broker:** the person who created the brokerage after the TREC check (A15.1a). For brokerages from before, it is their first broker; a migration records it, and REOS falls back to it when no row exists.
   - **Office admin:** the broker role at the brokerage.
   - **Team leader:** the broker role on one of its teams.
2. **Duties**, off by default and turned on per person by the designated broker:
   - `approve_posts`: posts and public pages waiting for approval;
   - `answer_escalations`: the virtual broker's questions;
   - `manage_policies`: the manual, the FAQ, the escalation settings, brokerage rules and the advertising policy;
   - `sponsor_agents`: bring in agents from TREC and invite agents.

   The designated broker always has every duty and is the only one who changes duties, seats and who pays, and who invites admins or team leaders. A refused action is a 403 with the reason. Changes are audited (`brokerage.duties_changed`).
3. **Where the duties are enforced:**
   - the advertising policy (update, approve, return, page approvals);
   - the virtual broker (manual, settings, FAQ removal and restore, replies);
   - the roster (contacts, Invite all);
   - invitations (agents need `sponsor_agents`; admins need the designated broker);
   - seats (designated broker only).
4. **Brokerage rules** (`policy_passages` of kind `policy`) are written in REOS beside the manual. Each is checked against the TREC rules:
   - as it's typed, through `POST organisation/policies/check`, which changes nothing;
   - and when it's saved.

   The check is the manual's (`TexasConflicts`), sentence by sentence, through the new `TexasConflicts::first`. A conflicting rule is kept, because changing it is the broker's call, and flagged with the TREC citation, the rule and its source link. FAQ answers are checked the same way when added.
5. **TREC wins in answers.** The virtual broker answers from brokerage rules like the FAQ. When the passage it would quote (manual, FAQ or rule) contradicts TREC, the TREC rule comes first.
6. **One home: Brokerage › Policies** (`/organisation/policies`, and Policies in the broker sidebar). It shows the manual (version and conflicts), the advertising policy (approvals and banned phrases) and the brokerage rules. On the phone, Your brokerage → Policies shows the same and lets the broker add a rule.

## Consequences

- An office admin added before this sprint has no duties until the designated broker grants them. That's deliberate: the journey says office admins act "if allowed".
- A15.3 uses `approve_posts` and `answer_escalations` for the one approvals queue, scoped to the team for team leaders.

## Alternatives considered

- **More roles instead of duties:** a fixed set of roles can't express "this office admin approves posts but doesn't touch policies".
- **Refusing a conflicting rule:** brokers would work around the check by rewording. Keeping and flagging the rule, with TREC winning in answers, is safer and honest.
