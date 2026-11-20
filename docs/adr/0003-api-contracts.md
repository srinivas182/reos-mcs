# ADR 0003: API contracts

- Status: Accepted
- Date: 2026-11-20

## Decision

- The REST API lives under `/api/v1`. Breaking changes go to a new version; v1 stays supported for at least 12 months after v2 ships.
- The web app uses Inertia and Wayfinder for its own pages; the mobile app and partners use only the REST API.
- From Sprint 1.3, Scramble generates the OpenAPI document from the Laravel code, and `packages/api-client` generates the typed TypeScript client from it. CI fails if the generated client is out of date.
- Responses use Laravel API resources with consistent error shapes (RFC 9457 problem details).

## Consequences

The mobile app never hand-writes request or response types once the generated client exists. `apps/mobile/src/lib/api.ts` is a temporary Sprint 1 bridge.
