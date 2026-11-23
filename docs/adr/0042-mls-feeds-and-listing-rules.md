# ADR 0042: MLS feeds and listing rules

- Status: Accepted (MLS feeds and My listings, B10.1; listing rules, posts and open houses, B10.2a; CMAs and the public agent page, B10.2b)
- Date: 2026-11-24 (Sprint 10; spec in docs/sprints/S10-spec.md)

## Context

Agents want their listings in REOS, so posts, CMAs, their public page and open houses (B10.2, B11) start from the MLS rather than retyping. Texas has four large MLSs: HAR (Houston), NTREIS (North Texas), Unlock MLS/ACTRIS (Austin) and SABOR (San Antonio). Each delivers its data through a vendor: Bridge, Trestle or MLS Grid. All of them speak the RESO Web API (OData) and the RESO Data Dictionary. They differ in sign-in, photos, filters and how they signal deletions. Licences take weeks and differ by MLS, so the work has to be built and shown before any MLS approves REOS.

## Decision

1. **REOS licenses each feed as a technology vendor** (decision 6). There is one copy per MLS on the platform, serving every agent.
   - The tables (`mls_listings`, `mls_members`, `mls_offices`, `mls_open_houses`, `mls_feeds`) are platform-wide, with no tenant column.
   - REOS's vendor keys are set in Settings › Integrations, like any platform integration.
2. **The register says which MLSs exist and who delivers them.**
   - Each MLS entry has an `mls` block: `vendor` (bridge, trestle, mls_grid, or to_confirm), `dataset` (the Bridge dataset, or MLS Grid's `OriginatingSystemName`) and `short_name`.
   - No MLS list lives in code. A new MLS is a register entry.
   - SABOR hasn't named its vendor, so it is `to_confirm` and runs on the Simulator only.
3. **One RESO Web API driver** (`ResoWebApi`) handles every vendor's differences:
   - **Bridge:** a server token; photos come inline with each listing.
   - **Trestle:** client credentials are swapped for a token, which is cached until shortly before it expires; photos come with `$expand=Media`.
   - **MLS Grid:** an access token; every query names the MLS (`OriginatingSystemName`); `MlgCanView` false means remove the record.
   - The driver follows `@odata.nextLink`, but only on the vendor's own host over HTTPS.
   - 401 and 403 mean the keys were refused, 429 waits as long as the vendor asks, and 5xx means try later.
4. **Replication** (`MlsSync`, `reos:mls:sync` every 15 minutes, one queued job per MLS):
   - It reads offices, members, listings and open houses changed since the last run, oldest first by `ModificationTimestamp`.
   - The cursor moves after each page, and records are upserted. A run that stops resumes from the same timestamp, so repeats are harmless.
   - A listing leaves REOS when it goes off the market (any `StandardStatus` other than Coming Soon, Active, Active Under Contract, Pending or Closed), or when the MLS withdraws it.
   - Once a day, the MLS's full list of listing keys finds listings deleted at the source.
   - Closed listings stay, for CMAs (B10.2).
5. **One mapping** (`ResoRecords`) reads Data Dictionary fields into REOS's columns, for every MLS and for the Simulator.
   - Photos are kept in order, up to 50, and only http(s) links.
   - The MLS's display flags are kept: `InternetEntireListingDisplayYN` and `InternetAddressDisplayYN`.
6. **The Simulator is a source like any vendor:**
   - It sends raw RESO records for each MLS (HAR carries the journey cast: James Miller, Dana Brooks and Linda Park at Lone Star Realty), so the same replication runs.
   - Agents see "Sample data".
   - Switching an MLS between the Simulator and a vendor purges the other source's records first, so sample and real data never mix.
7. **Linking an agent:**
   - The agent's MLS member record is found by their licence number (`MemberStateLicense`, compared as digits without leading zeros).
   - The agent confirms it ("Yes, this is me") or declines it ("Not me").
   - A member record links to one REOS account.
   - **My listings** are the listings where a linked member is the list or co-list agent. Unlinking offers Undo.
8. **Screens:**
   - Web: My listings sits beside Deals, at `/deals/listings` under Deals in the sidebar, which is locked to the journey.
   - Phone: My listings opens from the Deals pipeline.
   - Both show status, price (or the price it sold for), beds, baths, size, the agent's role, the MLS number, days on market and the next open house.
9. **Connection test:** each MLS's keys are tested by signing in the vendor's way and reading one office record.

## Decision: listing rules, posts and open houses (B10.2a)

10. **Display rules live in the register** (decision 7), in each MLS's `mls.display`:
    - `max_photos`;
    - `refresh_hours`;
    - `sold_public` (Texas is a non-disclosure state);
    - `sold_in_cma`;
    - `own_listings_only` (for posts);
    - attribution: listing brokerage, MLS name, logo.

    They are conservative until each signed licence says otherwise; changing one is a register edit.
11. **One listing service** (`ListingService`) is how every feature reads a listing. It takes a purpose (`agent`, `post`, `public`, `cma`) and enforces the rules there, so no screen can break them:
    - Photos are capped at the MLS's limit, and posts at 10 within it.
    - The MLS's attribution ("Listing courtesy of …. Source: … MLS.") travels with every listing.
    - Public pages and posts show nothing from a feed older than its refresh age.
    - They also show only listings the MLS lets consumers see, and withhold the address (and map position) when the MLS asks.
    - Sold prices show only to the agent and in CMAs unless the MLS allows public display.
    - Posts feature the agent's own listings only, where the MLS says so.
12. **Listing posts** (done when a listing post uses MLS photos within the licence terms):
    - The agent picks photos from those the service allows and writes a caption. The attribution is stored with the post and can't be edited.
    - There is one post per listing, changed until the content studio (B11) publishes it. Until then the agent can copy it (web) or share it from the phone's share sheet.
13. **Open houses on the calendar:** each upcoming open house of a linked agent's listing becomes a REOS calendar event (a showing, source = the MLS's register id).
    - The event is read-only in REOS and follows the MLS after each sync.
    - It is removed when the MLS cancels the open house or the agent unlinks.

## Decision: CMAs and the public agent page (B10.2b)

14. **CMA** for one of the agent's own listings:
    - **Comparables:** sold (within 3, 6 or 12 months, where the MLS allows sold data in CMAs) and active or pending listings from the same MLS and of the same property type, within 0.5 to 5 miles. A bounding box finds candidates first; the great-circle distance decides. Nearest first, at most 25.
    - **Adjustment:** each comparable's price per square foot is applied to the subject's size.
    - **Range:** the 25th to 75th percentile of the adjusted sold prices (all comparables when fewer than three sold), with the median as the suggestion.
    - **Export:** the CMA is a page that prints or saves as PDF; the app's shell is left out of print. Every comparable shows its MLS attribution.
15. **Public agent page** at `/agent/{handle}`: off until the agent publishes it from My listings.
    - **Content:** name, brokerage (from the MLS office), active license numbers, a short bio, and the agent's listings through the listing service for public display (fresh feeds only, the MLS's consumer flags, no sold prices in Texas, attribution on each).
    - **TREC notices:** the page links TREC's Consumer Protection Notice and Information About Brokerage Services with the link text 22 TAC §531.18 and §531.20 require.
    - **Handle:** taken from the agent's name (`james-miller`, then `james-miller-2`).
    - **Sample data:** it is marked as sample and kept out of search engines (`noindex`).

## Consequences

- B10.2's listing service, and through it posts, CMAs, agent pages and open houses, reads these tables. Display rules per MLS are added to the register's `mls` block and enforced there.
- An MLS licence arriving is a mode switch in Settings › Integrations, not a release.
- The IABS link points to TREC's form page. A brokerage's own completed IABS (which §531.20 expects) is a brokerage setting to add with the brokerage profile.
- Feeds hold no contact details (no member emails or phones), so the MLS tables carry no personal data beyond names and licence numbers already public on TREC.

## Alternatives considered

- **A driver per vendor:** three copies of OData paging, replication and mapping, for differences that fit in one class.
- **Agents connecting their own MLS accounts:** MLSs license feeds to vendors, not to agents' logins, and one feed serves everyone.
- **Matching agents by name:** names repeat and change. The licence number is unique and already verified (A5.1).
