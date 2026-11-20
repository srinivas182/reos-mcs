# REOS: Real Estate OS

REOS is one platform for the whole real estate career in Texas: pre-licensing, exam prep, licensing, continuing education, compliance, brokerage operations and growth. It serves candidates, agents, brokers and team leaders, instructors and mentors, service providers, and association administrators.

This is a private, proprietary repository owned by Mayura Consultancy Services.

## Repository layout

| Path | What it is |
| --- | --- |
| `apps/platform` | Laravel 13 back end, REST API (`/api/v1`) and the React web app (Inertia, React 19, TypeScript, Tailwind 4) |
| `apps/mobile` | React Native app (Expo SDK 58, Expo Router), built and released with EAS |
| `packages/tokens` | Design tokens shared by web and mobile (colour, type, spacing, touch targets) |
| `packages/config` | Shared TypeScript configuration |
| `packages/api-client` | Typed REST client generated from the platform's OpenAPI document |
| `docs/adr` | Architecture decision records |
| `docs/sprints` | Sprint specifications |
| `docs/integrations` | Integrations register (every integration, vendor account and approval, with owners and dates) and credentials policy |

The mobile app talks to the platform only through the versioned REST API. There is no Node back end.

## Requirements

- Docker (Docker Desktop, OrbStack or Docker Engine) for the local services
- PHP 8.5 with `pdo_pgsql`, `redis`, `intl`, `bcmath` (CI runs 8.5; 8.3+ works locally until Sprint 2) and Composer 2
- Node 22.12+ and pnpm 10.28 (`corepack enable`)
- For mobile: the Expo Go app or an Android/iOS simulator

Not sure what you have? `pnpm dev:doctor` checks everything and tells you how to fix each problem. Or skip all of this and open the repo in the **dev container** (VS Code "Reopen in Container", or GitHub Codespaces): everything is installed for you.

## Getting started

```bash
corepack enable
pnpm dev:setup                       # checks your machine, starts services, installs, migrates, seeds
cd apps/platform && composer dev     # web app on http://localhost:8000
```

In a second terminal, for the mobile app:

```bash
cd apps/mobile
EXPO_PUBLIC_API_URL=http://<your-machine-ip>:8000 pnpm dev
```

### Local services

| Service | Address | Used for |
| --- | --- | --- |
| PostgreSQL 17 + pgvector | `127.0.0.1:5432` (user `reos`, password `reos-local`) | Main database (`reos`) and tests (`reos_testing`) |
| Redis 7.4 | `127.0.0.1:6379` | Cache, sessions, queues |
| Meilisearch 1.57 | http://localhost:7700 | Search |
| Mailpit | http://localhost:8025 | Catches every e-mail the app sends |
| SeaweedFS (S3) | http://localhost:8333, bucket `reos` | File uploads |
| API docs | http://localhost:8000/docs/api | Generated from the code (local only) |

`pnpm dev:up` / `pnpm dev:down` start and stop them; `pnpm dev:reset` wipes their data; `pnpm dev:verify` proves each one works. More in `docs/local-development.md`.

### Demo logins

Every journey persona has a login. The password for all of them is `password` (local and staging only; never seeded in production).

| Role | Person | E-mail |
| --- | --- | --- |
| Candidate | Sofia Reyes, Texas Realty School | sofia.reyes@demo.reos.test |
| Agent | James Miller, Lone Star Realty | james.miller@demo.reos.test |
| Broker / team leader | Linda Park, Lone Star Realty | linda.park@demo.reos.test |
| Mentor | Dana Brooks, Lone Star Realty | dana.brooks@demo.reos.test |
| Instructor | Dr. Rachel Hughes, Texas Realty School | rachel.hughes@demo.reos.test |
| School admin | Paul Diaz, Texas Realty School | paul.diaz@demo.reos.test |
| Service provider | Ana Ruiz, Katy Home Loans | ana.ruiz@demo.reos.test |
| Association admin | Karen Webb, Texas REALTORS® | karen.webb@demo.reos.test |
| Super admin | REOS Operations | ops@demo.reos.test |

Roles and permissions arrive in Sprint 2; until then every login sees the same app.

## Everyday commands (from the repo root)

| Command | Runs |
| --- | --- |
| `pnpm build` | Builds every app and package (Turborepo, cached) |
| `pnpm check` | Lint and format checks |
| `pnpm types:check` | TypeScript checks |
| `pnpm test` | JavaScript tests |
| `pnpm integrations:status` | What's late or due in the integrations register, as of today |
| `pnpm api:generate` | Re-export the OpenAPI document and regenerate `@reos/api-client` after changing an API endpoint |
| `composer test` (in `apps/platform`) | Pint, PHPStan level 9, Pest |

## Working agreement

- Branch from `main` as `sNN.N-short-name`; open a draft pull request, mark it ready once for the full test run, and merge only when **CI result** is green (see `docs/ci.md`).
- Every change ships with tests. Controllers stay thin; business logic lives in services and domain classes.
- Design standard: luxury, modern and calm; easy for non-technical and older users (16pt minimum body text, 48pt touch targets, plain language).
- Read `CLAUDE.md` and `docs/adr` before making structural changes.
