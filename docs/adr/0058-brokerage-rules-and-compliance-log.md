# ADR 0058: Brokerage rules on every channel, routing and the compliance log

- Status: Accepted (B13.2)
- Date: 2026-11-27 (Sprint 13; spec in docs/sprints/S13-spec.md)

## Context

B12.1 gave brokerages banned phrases, Fix automatically, and approval of posts for new agents or for everyone. B13.1 put one engine on every channel. Two things were still missing:

- **A way out when a flag is wrong.** A flagged post could only be fixed. When the wording is fine (a subdivision named "Family Room Ranch", a seller's own remark), the agent had nowhere to go.
- **A record.** The broker couldn't show a regulator or their E&O carrier what was checked, fixed, approved and sent back.

## Decision

1. **The brokerage's rules apply on every channel the engine covers.** Banned phrases are checked on posts, videos, the public page, emails and texts. Fix automatically is offered on posts and messages.
2. **Approval covers public advertising.** Posts (and the videos and designs posted with them) already waited for the broker. Now the public page does too: when the brokerage approves its agents' ads, publishing or changing the page puts it in the broker's queue (`agent_pages.approval_requested_at`), and it goes live when the broker approves it. An unchanged live page stays live. A one-to-one email or text isn't an ad, so it's fixed rather than held.
3. **Routing: fixed, or sent to the broker with the reason.** When only wording is flagged (`fair_housing`, `claims`, `banned`), the agent can send the post to the broker with a reason instead of fixing it (`ask_broker`, stored as `social_posts.approval_reason`). The broker sees the flags and the reason, then approves or sends it back with a note. Everything else must be fixed and can't be sent to the broker: the broker's name, team names, titles, the post's shape, and agents without a brokerage.
4. **The compliance log** (`compliance_events`, row-level security; independent agents' rows have no brokerage). `ComplianceLog::record` stores every decision that counts:
   - posts saved to go out (checked or blocked), Fix automatically, sent to broker, approved, sent back;
   - videos sent to render;
   - pages published (checked, blocked or waiting) and the broker's decisions;
   - emails and texts checked or fixed, and fixed assistant drafts.

   Each line keeps the agent, who acted, the channel, the item, the rules with their citations, and the note. Checks while typing aren't logged. The broker filters the log by agent, channel, action and dates at `/organisation/compliance-log` and exports it to CSV. Cells can't start a spreadsheet formula, and the export is audited.

## Consequences

- A broker can answer "what did we check, and who allowed it?" for any ad or message, with the rule behind each decision.
- Wrong flags have a way out through the broker, and the broker's approval is on record.
- The log grows with use. It's indexed by brokerage and date; trimming old lines is left to the retention policy.
