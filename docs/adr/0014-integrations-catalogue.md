# ADR 0014: Integrations catalogue

- Status: Accepted
- Date: 2026-11-21 (Sprint 3, B3.2; spec in docs/sprints/S03-spec.md)

## Context

The Integration Hub (ADR 0009) says how REOS runs each tool (off, demo data, test account, live). People also need to know which tools they can connect, and the super admin needs to roll tools out, pause them and retire them by state, role and plan, without an app release (web journey screens 64–66; Connections and Sync journey screens 1–12).

## Decision

1. **Two separate settings per tool.**
   - `integration_settings` (ADR 0009): how REOS runs it. An engineering choice.
   - `catalogue_settings` (this ADR): what people see and who may use it. A product choice.
   - A tool with no catalogue row uses the register's `launch` value.
2. **Statuses:**
   - `enabled`, `beta`, `coming_soon`, `paused` (kill switch; people see "Temporarily unavailable"; data kept, syncing stops), `retired` (shown with its last day, then removed; syncing stops after it).
   - Every change is audited (`catalogue.updated`, with the fields changed).
3. **Availability:**
   - By state (the person's target state, or the deployment's region) and by active role.
   - Plan rules are stored now and enforced when plans exist (B5.1).
   - Beta tools: chosen organisations can connect; others can ask to join.
   - Organisations can hide a tool from their people when the tool allows it (`tenants_may_hide`), never enable one. Their admin screen comes with A4.
4. **Honest to people.**
   - A tool whose connector isn't built yet shows "Coming soon" with "Notify me", whatever its status.
   - A tool REOS has switched off shows "Temporarily unavailable".
   - "Connect" is only offered, and only accepted by the API, when the person can really connect.
5. **The people's library** is `GET /api/v1/catalogue`.
   - Tools people connect themselves, with personal or organisation accounts. Payment gateways and REOS's own data feeds stay in the back office.
   - Grouped into the journey's tabs: CRM, Deals, Calendar, Leads, More.
   - Each tool has an action: `connect`, `join_beta`, `notify` or `none`. Status changes show without an app release.
6. **Demand:**
   - "Notify me" and "Join beta" taps (`integration_interests`, once per person) are counted per tool.
   - "My tool isn't listed" requests go to `tool_requests`.
   - Both are on the super admin's Notify-me demand page.
7. **The register stays the one list.** The journey library's missing tools are added as `coming_soon` entries, under new categories `meetings`, `leads` and `contacts`:
   - Deals: Open to Close, Paperless Pipeline, ListedKit, BoldTrail BackOffice.
   - Calendar and meetings: ShowingTime, BrokerBay, Apple Calendar, Zoom, Microsoft Teams, Google Meet.
   - Leads: Zillow, Realtor.com, Homes.com.
   - Phone contacts.

   Their API access routes are first guesses to confirm when each partner application is filed.

## Plan

- **B3.2a (done):** the catalogue model, API, register gap and the super admin's screens:
  - 64: catalogue table with status tabs, states, roles, plans, connections, health and demand.
  - 65: the tool in tabs (Availability with "Credentials and health" beside it, Credentials, Health, Changelog) and the kill switch.
  - The Notify-me demand page.
- **B3.2b (done):** the phone's Connections screens (Connections and Sync journey, screens 2–7 and 12):
  - One place (12), Add a connection with its tabs, search, Notify me and "My tool isn't listed" (2–4), consent (5), connected with smart defaults (6), What to sync (7), and a tool's own page with Sync now and Disconnect.
  - Each tool says what REOS can and never does (`consent`) and what syncs, which way and how many records (`streams`), from the driver.
  - Tool descriptions for agents live in `packages/i18n` (`integrations.tools.*`, English and Spanish); the register check fails if a tool people can see has none. The register's `purpose` stays the admins' description.
  - The API answers in the phone's language (`Accept-Language`, then the person's saved language).
- **B3.2c (this change):** the website's Connections page (web journey screen 19):
  - Title, purpose line ("Tools available to agents in Texas · managed by REOS") and Request a tool; tabs by tool type in the journey's order (CRM, Transactions, Calendar and email, Leads, Social, MLS and IDX, E-sign, More), only those with tools; meeting tools sit with the calendars.
  - Tool cards with status (Connected, Needs sign-in, Available, Beta, Coming soon, Temporarily unavailable) and one action: Connect (the tool's own sign-in), Manage, Reconnect, Join beta or Notify me (with Undo). Connected tools come first. Brokerage tools say "Set up by your brokerage".
  - Manage: account, last sync, items synced, Sync now, how far back, and Disconnect behind a confirmation (the copied data is removed, so it isn't undoable).
  - The web sign-in returns here. Sync now, how far back, Notify me and tool requests live in the services, shared by the API and the website.
  - Four cards per row from 1600 px; at 1440 px three, so status and action stay on one row at 16 px text.

## Not built (yet)

- **"Add integration" on screen 64:** tools are added in the register, so the docs, the hub and the catalogue stay one list.
- **"Data mapping" tab on screen 65:** comes with the first two-way connectors (B4.1, B8.1).
- **Payment gateways (screen 66):** use this same status model with B5.1.
