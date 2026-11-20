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
| `docs/adr` | Architecture decision records |
| `docs/sprints` | Sprint specifications |

The mobile app talks to the platform only through the versioned REST API. There is no Node back end.

## Requirements

- PHP 8.5 (CI runs 8.5; 8.3+ works locally until Sprint 2 raises the constraint) and Composer 2
- Node 22.12+ and pnpm 10.28 (`corepack enable`)
- For mobile: the Expo Go app or an Android/iOS simulator

## Getting started

```bash
corepack enable
pnpm install

cd apps/platform
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
composer dev          # Laravel server, queue, logs and Vite
```

In a second terminal:

```bash
cd apps/mobile
EXPO_PUBLIC_API_URL=http://<your-machine-ip>:8000 pnpm dev
```

Sprint 1.3 replaces these steps with a single Docker Compose environment and demo data.

## Everyday commands (from the repo root)

| Command | Runs |
| --- | --- |
| `pnpm build` | Builds every app and package (Turborepo, cached) |
| `pnpm check` | Lint and format checks |
| `pnpm types:check` | TypeScript checks |
| `pnpm test` | JavaScript tests |
| `composer test` (in `apps/platform`) | Pint, PHPStan level 9, Pest |

## Working agreement

- Branch from `main` as `sNN.N-short-name`; open a pull request; CI must be green before merging.
- Every change ships with tests. Controllers stay thin; business logic lives in services and domain classes.
- Design standard: luxury, modern and calm; easy for non-technical and older users (16pt minimum body text, 48pt touch targets, plain language).
- Read `CLAUDE.md` and `docs/adr` before making structural changes.
