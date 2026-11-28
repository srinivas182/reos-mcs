# ADR 0079: Agents in a provider's areas, and requests to connect limited per plan

- Status: Accepted (B16.1)
- Date: 2026-11-28 (Sprint 16; spec in docs/sprints/S16-spec.md)

## Context

Providers on REOS (lenders, inspectors, title) pay a flat monthly fee (B15.2, RESPA). Until now they saw only how many agents were active in each service area. Thumbtack and Angi sell leads one at a time, so providers pay per contact and agents get flooded. RESPA forbids paying for referrals, so REOS can't sell contacts at all.

## Decision

1. **Agents in my areas** (`App\ProviderNetwork\AgentsInArea`), for a live provider:
   - REOS agents whose MLS listings (as listing or co-listing agent, through the member record they confirmed) or REOS deals in the last 12 months fall in the provider's cities, ZIP codes or radii;
   - a **level** for each: *top producer* with 12 or more in 12 months, *new* when first licensed in the last 12 months, *active* otherwise;
   - filters by level and area;
   - the provider sees the first name and initial, the brokerage, the level, the cities and the count. Never contacts, clients or deals: contacts are shared only when both agree (B16.2);
   - agents who turned the provider network off are left out. Agents are now in the network by default and can turn it off in Settings › Privacy (S16 decision 4). Candidates already were.
2. **Requests to connect** (`App\ProviderNetwork\ProviderRequests`, `provider_requests`):
   - **monthly limit per plan**: Listed 5, Pro 25, Partner 75 (`ProviderPlans::REQUESTS`), reset on the 1st in Central time. The count and the insert run under a lock for each provider, so two requests sent at once can't get past the limit;
   - only to agents in the provider's areas; one waiting request per agent;
   - a declined request is final; an expired one can be sent again 30 days after it expired; a withdrawn one still counts for the month;
   - expires after 14 days unanswered (`reos:providers:requests`, daily); a waiting request past its date already counts as expired;
   - an optional note of up to 500 characters with no phone numbers, emails or links;
   - the agent is told (`provider_request`, in Messages), and the request is audited.
3. **No fee per request, connection or closing.** A request is a request to talk, never a referral. The network stays behind `provider_network` until counsel signs off.

## Alternatives considered

- **Showing every MLS agent, including those not on REOS.** They never agreed to hear from providers, and can't answer in REOS.
- **Credits per request.** That is pay-per-lead under another name, which RESPA risks and agents dislike.
- **A shared pool of requests for each business.** The limit belongs to the plan, which belongs to the profile, so it's counted per profile.

## Consequences

- B16.2 adds the agent's answer (accept opens a private chat), mutual contact sharing, block and report, and opting out per category. These filter this list too.
- B16.3 lets agents send requests to providers. Those don't count toward the provider's limit.
- Levels depend on agents confirming their MLS member record (A14.2). Agents who haven't are placed by their REOS deals only.
