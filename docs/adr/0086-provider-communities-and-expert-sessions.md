# ADR 0086: Provider communities and expert sessions

- Status: Accepted (B17.2)
- Date: 2026-11-28 (Sprint 17; spec in docs/sprints/S17-spec.md)

## Context

Providers build audiences on Skool (one feed, points, cheap) or Circle (spaces, events, paid tiers). Those tools are generic:
- no licence checks;
- no CE rules;
- no advertising checks;
- a fee on every payment.

For real estate there's also RESPA §8: nothing of value may change hands for a referral. REOS already has provider profiles, plans and billing (B15), and connections and reviews (B16).

## Decision

1. **Who leads:** Pro and Partner providers (`tier` pro or premium), one community each, set up on first use. Listed providers are sent to their plan.
2. **Who joins:** verified members only, meaning:
   - agents or brokers with a role in a brokerage on REOS;
   - live providers.
   They join by the community's link or its QR code (`via` link or qr, kept for results). A new link turns the old one off.
3. **Posts are checked before they go up** (`App\Communities\PostCheck`):
   - REOS's advertising wording (fair housing and misleading claims, `App\Compliance\Wording`);
   - wording that ties money to referrals (RESPA);
   - phone numbers, email addresses and links: contacts go through connections (B16.2).
   A post that fails is not saved; the person is told why. The owner pins posts. The author or the owner takes a post down, and that's audited.
4. **Resources:** https links the owner shares.
5. **Expert sessions** (`community_events`, `community_rsvps`):
   - online (a join link shown only to people going) or in person, with an optional capacity;
   - members are told (`CommunitySessionPosted`, kind `announcement`);
   - **CE:** every session says whether it counts. With a course number from an approval elsewhere, it says "Counts for CE as stated by the host (course …)". Otherwise it says "Information only. Doesn't count toward CE." REOS doesn't issue CE for these;
   - **Paid on Partner only** (decision 5): a product sold by the provider's business at a flat price, 100% to the provider. REOS's fee is the Partner plan, not a cut. RSVP is held until checkout is paid (`Checkout::fulfil` → `Communities::paid`);
   - after the session the host ticks who came, and the session is done.
6. **Results** (provider screen 18): members, joined in 30 days, by link and by QR code, posts in 30 days, sessions, RSVPs, attendance and show rate, and the top sessions.
7. **Tenancy:** communities span tenants (agents from many brokerages, providers), so the tables have no `organisation_id`. Membership is checked in `Communities`. Audit events are written at platform level.

## Alternatives considered

- **Linking out to Skool or Circle.** No verified membership, no advertising or RESPA checks, and a fee on every payment.
- **Paid sessions on Pro with a per-sale fee.** That is the model we're replacing; a flat plan keeps money away from referrals.
- **REOS-issued CE for expert sessions.** CE needs approved courses (A11); sessions point to the host's own approval or say they're information only.

## Consequences

- Scanned QR codes open the web invite, or the app's `/join/community/<code>` screen once universal links are set up for the store builds.
- Points or leaderboards inside communities are not built; the pod and challenges (A13.2) cover that for agents.
