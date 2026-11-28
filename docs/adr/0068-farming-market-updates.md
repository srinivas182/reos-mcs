# ADR 0068: Farming market updates come from the agent's MLS, follow its display rules, and pass the compliance engine

- Status: Accepted (B14.3c)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

Farming means mailing the same neighborhood every month until the agent is the name people think of. Tools like Homebot, RealScout and Market Leader send market reports. Their weaknesses:

- Their figures come from public records or a vendor, not the agent's own MLS.
- The agent pays per contact.
- Compliance is left to the agent. In Texas an email to many people is an advertisement: it must name the broker, make no misleading claims and use fair housing wording.
- Texas is a non-disclosure state, so most MLS licenses keep sale prices off public material.

REOS already reads the agent's MLS through `ListingService`, which applies each MLS's display rules (B10.2). It has the compliance engine and log (B13.1, B13.2) and the agent's tagged contacts (B7).

## Decision

1. **An area** is a city or a ZIP code in the agent's MLS (from their confirmed MLS member links), plus:
   - the tag of the contacts who get the update;
   - the sender's postal address, which is required by CAN-SPAM in every marketing email.
2. **The figures** cover the last three months. They use only listings the MLS lets the public see (`idx`) from a fresh feed:
   - homes for sale and the median asking price;
   - homes under contract;
   - new listings;
   - homes sold;
   - the median days on market;
   - months of supply.

   The median **sale price** appears only when every MLS involved allows sold prices on public material (`soldPublic`), so it's off by default in Texas. Counts of sales are always allowed. The MLS source line ("Based on information from HAR for Katy, … Not guaranteed.") goes with every update.
3. **The draft** is written in the agent's language and ends with the agent's and broker's names. The agent can edit it.
4. **Every update is checked as an advertisement.**
   - A new compliance channel, `newsletter`, follows the ad rules: the broker's name, no misleading claims, fair housing wording, no service advertising before a license.
   - The check runs on the draft, again on request, and again at the moment of sending. A blocked update is refused.
   - Sends and blocks go to the broker's compliance log.
5. **Sending**
   - It goes to the agent's contacts with the area's tag and an email address, less anyone who unsubscribed.
   - At most one update per area a week.
   - Each email has a reply-to of the agent, the agent, broker and postal address, and why they're getting it.
   - It has a signed unsubscribe link, plus `List-Unsubscribe` and `List-Unsubscribe-Post` headers (RFC 8058 one-click).
   - The link carries a hash of the address, never the address. A person opening it sees a button first, so link scanners don't unsubscribe anyone; a mail app's one-click POST works without a session.
   - Opt-outs are kept per agent as SHA-256 hashes. Sends are audited.

## Consequences

- No per-contact fees and no new vendor: the figures come from the MLS feed REOS already licenses, under its display rules.
- If the brokerage's MLS license allows sold data publicly, sale prices appear with no code change, because the register drives the display rules.
- Claude can later write the narrative. The compliance check is already in place for whatever it writes.

## Alternatives considered

- **Public-records sale prices:** they conflict with the MLS license and non-disclosure in Texas.
- **Sending from the agent's own mailbox (Gmail or Outlook connectors):** it would hit the mailbox's sending limits and lose unsubscribe handling. It may come later for small farms.
- **No check at send time:** an edited update could slip past the draft's check.
