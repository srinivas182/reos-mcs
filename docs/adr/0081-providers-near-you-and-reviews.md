# ADR 0081: Providers near you, Request to connect in two taps, and reviews from connected agents

- Status: Accepted (B16.3)
- Date: 2026-11-28 (Sprint 16; spec in docs/sprints/S16-spec.md)

## Context

Agents could already browse verified providers by category and place (B15.3). They couldn't see who serves the areas they actually work in, what other agents thought of them, or reach one without first being asked. On Thumbtack and Angi, reviews come from any customer, and reaching out means filling in a form that gets sold to several businesses.

## Decision

1. **Providers near you** (`App\ProviderNetwork\AgentDirectory::near`):
   - the agent's cities and ZIP codes come from their REOS deals and confirmed MLS listings in the last 12 months;
   - live, published providers whose areas cover them are marked **Near you** and listed first, then Partner and Pro, then the best reviewed. Each category shows its count near the agent;
   - an agent with no deals or MLS record yet sees every live provider;
   - providers blocked either way are left out.
2. **Request to connect in two taps:**
   - tap a provider (one), then tap Request to connect (two): an agent asking is agreeing, so the connection opens at once with a private chat (B16.2);
   - if the provider had already asked, connecting accepts that request; a second tap returns the same connection;
   - only agents can connect this way; brokers browse;
   - it never counts toward the provider's monthly limit (`initiated_by = agent`). Contacts still need both to press Share;
   - the provider is told (`provider_request`), and it's audited.
3. **Reviews** (decision 5, `provider_reviews`):
   - only from an agent connected with the provider for 30 days;
   - one per agent per provider, and a second review replaces the first;
   - 1 to 5 stars, and an optional note of up to 1,000 characters with no phone numbers, emails or links;
   - shown with the agent's first name and initial; providers see the average and count.

## Alternatives considered

- **Making the agent's request wait for the provider.** Providers joined to meet agents and accepted the network rules. Making the agent wait would add a step and remove the point of the directory.
- **Reviews from anyone.** Only agents who worked with the provider can judge them, and fake reviews would be easy otherwise.
- **Ranking by rating alone.** A brand-new provider with no reviews would never be seen. Near comes first, then the plan and the rating.

## Consequences

- When a provider-response metric arrives (provider journey "results"), it can use the chats these connections open.
- Reviews will need moderation tools in the back office (Sprint 18). Until then REOS's team handles reports through Messages.
