# Sprint 1: Foundations (7 to 18 December 2026)

Goal: a working monorepo that every developer can clone, run and ship from, with quality gates on from the first commit.

| Part | Pull request | Contents | Status |
| --- | --- | --- | --- |
| 1.1 | Monorepo skeleton | pnpm + Turborepo workspace; Laravel 13 platform with `/api/v1/health`, Octane, Pest, PHPStan level 9; Expo SDK 58 app calling the health endpoint; shared tokens and TypeScript config; ADRs; basic CI | This PR |
| 1.2 | CI pipeline | Path filters, parallel jobs, caching, draft vs ready rules, Lefthook hooks, Renovate | Next |
| 1.3 | Local environment and contracts | Docker Compose (PostgreSQL 17, Redis, Meilisearch, Mailpit, MinIO), demo data with the journey personas, Scramble OpenAPI, `packages/api-client`, dev container | Planned |
| 1.4 | Integrations register | `docs/integrations/register.md`: every integration and gateway, owner, sandbox status, credentials owner | Planned |

## Definition of done

- `pnpm install && pnpm build` works from a clean clone.
- `composer test` passes in `apps/platform`; `pnpm test` and `pnpm types:check` pass for mobile.
- CI is green on the pull request.
