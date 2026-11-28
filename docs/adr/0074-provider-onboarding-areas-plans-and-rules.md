# ADR 0074: Provider onboarding: steps, service areas, plans through checkout, versioned rules, monthly re-checks

- Status: Accepted (B15.2)
- Date: 2026-11-30 (Sprint 15; spec in docs/sprints/S15-spec.md)

## Context

Providers (loan officers, inspectors, title companies, photographers and so on) join REOS to be found by agents. Provider screens 1–6 in the journey cover the steps:

1. welcome;
2. category;
3. licence;
4. service areas;
5. plan;
6. network rules.

Three things were already built:

- licences are checked (B10.3a: TREC inspector and TDI in seconds, NMLS by a reviewer);
- categories drive onboarding (B15.1, ADR 0073);
- checkout creates tenants (A6.1b).

Four of the Sprint 15 decisions apply:

- **Decision 4:** plans are Listed, Pro and Partner, flat monthly fees per category and state.
- **Decision 5:** providers stay off in production until the RESPA counsel review is signed off.
- **Decision 6:** cities, ZIP codes and a radius must work without a map.
- **Spec:** an expired licence hides the profile.

## Decision

1. **One profile per person** (`provider_profiles`) holds the steps. Its status is:
   - `joining`;
   - `waiting`: every step done, but the network is closed in the state;
   - `live`;
   - `hidden`, with the reason.

   `App\ProviderNetwork\ProviderOnboarding` works the status out after:
   - every step;
   - every licence added or reviewed;
   - every reading of the status;
   - the monthly re-check.

   A profile is live when all six steps are done, the licence is verified and not expired, and the `provider_network` flag is on for the provider business. The flag is per tenant or state, and starts off.
2. **Licence step:** the category's `licence_kind` decides what's asked for.
   - **NMLS, TREC inspector or TDI:** the B10.3a licence check. People joining may use it before they have the Provider role.
   - **Categories without a licence:** business name, address and a photo ID. The ID is stored on the content disk and becomes a `business_id` licence for the same reviewer queue. Only a super admin can open the ID, and each view is audited.
3. **Service areas** (`provider_service_areas`): up to 10 cities, ZIP codes, or radiuses around a ZIP code.
   - Each area shows the active agents there: distinct listing agents on the MLS in the last 12 months. A radius uses the ZIP code's listings as its centre and the haversine distance in PHP, so it needs no map or geo extension.
   - Each area also shows the live providers in the same category with that city or ZIP code.
4. **Plans** are bought through REOS checkout. `ProviderPlans` keeps one product per category and tier (`provider-{category}-{tier}`, buyer Provider), priced at the admin's flat monthly fee in the state; a changed fee ends the old price and starts a new one. The areas each plan allows:
   - Listed (`basic`): 1;
   - Pro: the category's areas on Pro;
   - Partner (`premium`): 10.

   A plan with fewer areas than the provider has is refused. `TenantSignups` gains a `provider` type, never offered on the public start page. Paying creates the provider business, gives the owner the Provider role, and records the business and tier on the profile.
5. **Network rules** (`NetworkRules`):
   - five rules: flat fee only, no payments to agents, client contact only with consent, Texas advertising rules, no spam;
   - acceptance records the version and date, and is audited;
   - accepting an old version is refused;
   - changing `VERSION` asks every provider again.
6. **Monthly re-check:** `reos:providers:recheck` runs on the 2nd of each month, after the regulators' imports. It looks up live and hidden providers' TREC and TDI licences again:
   - an expired or inactive licence hides the profile;
   - a fixed one shows it again;
   - a reviewer's earlier approval of a name mismatch stands.

   NMLS IDs and photo IDs keep the reviewer's answer.

## Alternatives considered

- **A separate provider billing flow.** That would duplicate checkout, tax and the gateways. A product per category and tier reuses all of it, and the admin's price stays the source.
- **PostGIS or Mapbox for radiuses.** These are more precise. But decision 6 asks for radiuses without a map, and listing coordinates are good enough to count agents.
- **Hiding a profile at once when a reviewer is re-checking.** Only a licence that's actually expired, inactive or rejected hides a live profile.

## Consequences

- Turning on `provider_network` for Texas (screen 70) publishes every waiting profile the next time it's read or re-checked.
- B15.3 builds the public profile on this one, and agents see only live profiles.
- More states need their own regulators' data for the re-check.
