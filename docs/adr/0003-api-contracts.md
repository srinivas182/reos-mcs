# ADR 0003: API contracts

- Status: Accepted
- Date: 2026-11-20

## Decision

- The REST API lives under `/api/v1`. Breaking changes go to a new version; v1 stays supported for at least 12 months after v2 ships.
- The web app uses Inertia and Wayfinder for its own pages; the mobile app and partners use only the REST API.
- Scramble generates the OpenAPI document from the Laravel code (`packages/api-client/openapi.json`), and openapi-typescript generates `packages/api-client/src/schema.d.ts` from it; openapi-fetch provides the tiny typed client. `pnpm api:generate` does both. CI and a Pest test fail if either is out of date.
- The spec's server URL is fixed (`http://localhost:8000/api/v1`) so it is identical on every machine; apps pass their own base URL.
- Interactive docs live at `/docs/api`, locally only. The Scalar "try it" proxy is disabled so requests never leave our network.
- Responses use Laravel API resources with consistent error shapes (RFC 9457 problem details).

## Consequences

The mobile app never hand-writes request or response types: `apps/mobile/src/lib/api.ts` wraps `@reos/api-client`. Any API change shows up as a TypeScript error in the mobile app until it's handled.
