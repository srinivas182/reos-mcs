# ADR 0067: Referrals between agents go through their brokers; invite and earn pays in points

- Status: Accepted (B14.3b)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

Agents refer clients who are moving to agents in other areas, for a fee. In Texas the fee is paid only through the sponsoring brokers (TREC §535.2 and §1101.651). Referral networks (Referral Exchange, HomeLight, Redfin Partner Agents) take 25–35% of the commission themselves. Agents also invite friends into the business; recruiting tools pay cash bounties, and RESPA and TREC rules make cash for licensees risky.

REOS already has contacts in the agent's CRM with write-back (B7, B8), the lead path from B14.3a (`Leads::add`), the brokers' names from the compliance engine (B13.1), and points with a ledger (B5.3, A13.3a).

## Decision

1. **Referrals are between agents, recorded for their brokers.**
   - An agent refers a client by the other agent's name and email. The client must have agreed (a required checkbox).
   - The referral records:
     - the kind (buyer or seller), area and note;
     - the referral fee the brokers agree, as a whole percent from 0 to 50;
     - both brokers' names as they were when it was sent.
   - The fee is shown everywhere as "paid broker to broker at closing; REOS takes no fee". REOS never takes or pays a referral fee.
   - **Receiver on REOS** (an agent with that email):
     - They get a notification, then accept or decline.
     - Accepting adds the client to their CRM through `Leads::add`: tagged "Referral", with a note of who referred them and the fee, a suggested first message, and a to-do for tomorrow.
     - The sender is told the answer.
   - **Receiver outside REOS:** they get an email in the sender's language, with reply-to set to the sender. Nothing else about the sender's brokerage is shared.
   - Either agent can mark a referral closed. Referring to yourself is refused. Sending and accepting are audited.
2. **Referrals span tenants, like conversations.**
   - The `referrals` table keeps `organisation_id` (where it started) but is exempt from row-level security. `CheckTenancy::EXEMPT` gives the reason.
   - Access is checked in `Referrals`: only the sending and receiving agents can see a referral.
3. **Invite and earn.**
   - Everyone has an 8-character invite code (letters and digits that can't be confused) and a link, `/join/{code}`.
   - **Who invited them:**
     - Signing up on the web after opening the link records the inviter.
     - Someone who signed up in the app can enter the code within 30 days, once, and only before getting licensed.
     - Inviting yourself doesn't count.
   - **The reward:**
     - When the invited person's license is verified (by the TREC lookup or a reviewer), the inviter earns 2,500 points ($25 inside REOS) and a notification.
     - The award is once per person invited, through the points ledger's milestone (`invite:{id}:licensed`), and capped at 25 a year.
     - Points have no cash value: they pay for mentor sessions, AI credits and membership inside REOS.
   - Other people's names are shown short ("Ana R.").

## Consequences

- Agents keep the whole fee between their brokers, with no network cut.
- Referrals reach agents outside REOS by email, so the feature is useful before the other agent joins.
- The invite reward is tied to a verified license, not to sign-ups, so there's nothing to farm.

## Alternatives considered

- **A referral marketplace that matches agents:** REOS would be taking a cut, and that contradicts decision 6 of the sprint spec.
- **Cash for invites:** it's a payment to licensees for referrals, so it's off the table.
- **Rewarding sign-ups:** easy to game; a verified license is the outcome that matters.
