# ADR 0065: A client portal on one private link, with signing inside it and a review after closing

- Status: Accepted (B14.2)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

Clients ask their agent the same questions all the way to closing: where are we, what's next, what do I sign? Lofty launched a client transaction portal in June 2026, and Rechat and ListedKit show deadlines to clients. All of them ask the client for an app or an account. Review requests usually come from yet another tool.

REOS already has the deal, with its parties, key dates and the documents its transaction tool tracks (B9), and signatures sent through the agent's own DocuSign (B9.3).

## Decision

1. **One private link per client.**
   - The agent creates a link on the deal, on the web or the phone, from a party or by name and email, in English or Spanish. The same client's open link is reused.
   - The link is the key:
     - 48 random characters, looked up only by its SHA-256 hash;
     - kept encrypted so the agent can share it again;
     - valid for 180 days (renewed when the agent emails it again);
     - can be turned off at once;
     - `noindex`.
   - Creating and turning off links is audited.
   - The agent shares the link from the phone's share sheet, copies it, or has REOS email it in the client's language.
2. **What the client sees** is plain server HTML, readable on any phone:
   - the deal's stage and price;
   - its key dates;
   - the documents the transaction tool tracks;
   - their agent (name, broker, the contact details from the agent's card, and a link to their page and listings);
   - the TREC notices.

   Nothing about other clients or the agent's other deals is reachable.
3. **Signing in the portal.**
   - When the agent sends documents for signature, any signer whose email matches an open portal link on the deal is added with a DocuSign `clientUserId`. DocuSign then doesn't email them; they sign inside the portal.
   - "Review and sign" asks DocuSign for a one-time recipient view, with the client identified by email, and comes back to the portal. REOS then refreshes the envelope.
   - Signers without a link still sign from DocuSign's email.
   - The demo simulator has its own signing page.
4. **A review after closing.**
   - Once the deal is closed, the portal asks for a rating from 1 to 5 and an optional comment. The client can change it later.
   - The review shows on the agent's public page only if the client ticks "Show my review and first name", and then as "Priya S.".
   - Shown reviews feed the page's schema.org `aggregateRating` and `review` data.

## Consequences

- Clients follow their deal and sign without an account, and agents get genuine reviews tied to a real, closed transaction.
- Anyone holding the link can see that one deal. That's why links expire, can be turned off, say "just for you", and show no other clients' details.
- Signing inside the portal needs the documents to be sent after the link exists. Envelopes sent earlier keep DocuSign's email signing.
