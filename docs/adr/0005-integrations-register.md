# ADR 0005: Integrations register as data

- Status: Accepted
- Date: 2026-11-20

## Context

The pilot needs every integration in the PRD live, and many depend on outside approvals (OAuth verification, partner programmes, MLS data licences, app review, agreements) with lead times of weeks to months. The back office also needs a catalogue of the same integrations with statuses (enabled, beta, coming soon, paused, retired).

## Decision

- `docs/integrations/register.yaml` is the single source of truth: one entry per integration, platform service, vendor account and outside approval, with owner, access route, estimated lead time, the sprint that needs it, status, sandbox, credentials model, personal data, cost and fallback.
- A JSON Schema (`register.schema.json`) and `scripts/integrations-register.mjs` validate it and render `docs/integrations/README.md`, including an ordered action list. CI fails on invalid data or a stale README; `pnpm integrations:status` reports late items against today's date.
- From Sprint 3 (B3.2), the back-office catalogue is seeded from the same file, so documentation and product can't disagree.

## Alternatives considered

- **A spreadsheet**: easy to edit, but drifts from the code, has no validation and no history in pull requests.
- **A hand-written Markdown table**: no validation, no computed dates, and the catalogue would still need a second copy.

## Consequences

- Updating an application's status is a small pull request (a docs-only change runs only the register check in CI).
- Lead times are estimates until vendors confirm; owners update them when they file.
