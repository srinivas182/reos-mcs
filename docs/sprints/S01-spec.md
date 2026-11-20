# Sprint 1: Foundations (7 to 18 December 2026)

Goal: a working monorepo that every developer can clone, run and ship from, with quality gates on from the first commit.

| Part | Pull request | Contents | Status |
| --- | --- | --- | --- |
| 1.1 | Monorepo skeleton | pnpm + Turborepo workspace; Laravel 13 platform with `/api/v1/health`, Octane, Pest, PHPStan level 9; Expo SDK 58 app calling the health endpoint; shared tokens and TypeScript config; ADRs; basic CI | Merged (#1) |
| 1.2 | CI pipeline | Path filters, parallel jobs, caching, draft vs ready rules, Lefthook hooks, Renovate, monthly CI minutes report | Merged (#2) |
| 1.3 | Local environment and contracts | Docker Compose (PostgreSQL 17 + pgvector, Redis, Meilisearch, Mailpit, SeaweedFS S3, replacing MinIO, whose free images stopped in October 2025), `pnpm dev:setup` / `dev:doctor` / `dev:verify`, journey-persona demo logins, Scramble OpenAPI, `packages/api-client` (openapi-typescript + openapi-fetch), dev container, CI tests on PostgreSQL | Merged (#3) |
| 1.4 | Integrations register | `docs/integrations/register.yaml` (52 entries: integrations, platform services, vendor accounts, approvals) with schema, validator and generated README with an ordered action list; `pnpm integrations:status`; credentials policy; CI check; ADR 0005. Filing the applications themselves is the owners' task (client, Nelle, Mayura) | This PR |

## Definition of done

- `pnpm install && pnpm build` works from a clean clone.
- `composer test` passes in `apps/platform`; `pnpm test` and `pnpm types:check` pass for mobile.
- CI is green on the pull request.
