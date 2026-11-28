# ADR 0080: The private network: chat on acceptance, mutual contact sharing, block, and opting out

- Status: Accepted (B16.2)
- Date: 2026-11-28 (Sprint 16; spec in docs/sprints/S16-spec.md)

## Context

Providers can now ask agents in their areas to connect (B16.1). Lead marketplaces hand a provider the agent's phone and email when they pay, and agents end up flooded with calls. REOS's rules say contacts are shared only when both sides agree, and agents can turn requests off.

## Decision

1. **The agent's answer** (`App\ProviderNetwork\ProviderConnections`):
   - **Accept** opens a private one-to-one chat in Messages, with a first message from the agent. From then on the provider and the agent can message each other (`Relationships` counts an accepted request as a working relationship). The provider then sees the agent's full name;
   - **Decline** ends the request. **Decline and block** also blocks the provider, so they can't message or ask again, and the agent drops out of their Agents in my areas;
   - the provider is told it was accepted or declined, never the reason and never whether they were blocked.
2. **Mutual contact sharing:**
   - each side presses **Share**: a per-connection consent (`contact_sharing`, subject `provider_request:<id>`), recorded with the method, IP address and time like every consent (A6.3);
   - `contacts` (phone and email) appear only when both have shared, and never across a block;
   - either side can stop sharing, and then neither sees the other's contacts.
3. **Block and report:**
   - block from a request or a connection, either side, using the messaging block (B6.1b);
   - report a connection's chat through Messages' Report, which goes to REOS's team with the last messages.
4. **Agent opt-out:**
   - all providers: the provider-network switch (on by default for agents since B16.1);
   - per category: the same consent with subject `category:<key>`;
   - opted-out agents never show to providers, and requests to them are refused as if they weren't in the area.
5. **Where:**
   - Web: Provider requests (agent sidebar) with waiting requests, connections and choices; the provider's connections on Agents in my areas.
   - Phone: Me → Provider requests; a connection screen for either side; the provider's connections on Agents in my areas.

## Alternatives considered

- **Sharing contacts automatically on acceptance.** Agents told us they want to talk first. Accepting a chat isn't agreeing to calls.
- **A separate chat system for providers.** Messages already has read receipts, attachments, report and block, and the phone app.
- **Separate opt-out settings.** Consents already record each choice with its version and method, and Settings › Privacy shows the main switch.

## Consequences

- B16.3 adds agents asking providers. Those use the same accept, share and block, with the provider answering.
- A provider who was blocked stays blocked until the agent unblocks them in Messages.
