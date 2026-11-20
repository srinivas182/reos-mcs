# CI and developer checks

Goal: catch problems on your own machine first, then spend GitHub Actions minutes only once per change.

## What runs when

| Situation | What runs |
| --- | --- |
| `git commit` | Lefthook pre-commit, on staged files only: blocks secrets and signing files, Pint (PHP style), web lint/format, mobile ESLint. Fixes are re-staged for you. |
| `git push` | Lefthook pre-push, only for apps you changed: PHPStan, Pest (parallel), web types, mobile types and Jest. |
| Draft pull request | **Quick checks only**: Pint, PHPStan level 9, web lint and types, mobile lint and types. |
| Pull request ready for review (and later pushes to it) | Quick checks, then **full tests**: web build and Pest (parallel) for the platform, Jest for mobile. |
| Docs-only change (`docs/**`, `*.md`, except `docs/integrations/`) | Nothing runs except the "CI result" gate. |
| Change to the dev environment (`compose.yaml`, `.devcontainer/`, `scripts/dev-*`, migrations, seeders, `.env.example`) | Also the **Dev environment** job: starts the Compose services the way developers do, migrates and seeds, runs `pnpm dev:verify`, and builds the dev container image. |
| Change to `scripts/` or `docs/integrations/` | **Repo scripts** job (drafts too, about 30 seconds): integrations register valid and its README current, script tests, shellcheck. |
| Merge to `main` | Nothing runs: the pull request already proved it. |
| A newer push to the same PR | The older run is cancelled automatically. |

Only the touched apps run: a mobile-only change skips all PHP, a platform-only change skips mobile. Changes to `packages/**`, the lockfile, the root workspace files or `ci.yml` run both apps.

Platform tests run on **PostgreSQL 17 + pgvector and Redis** (the production engines), after checking migrations roll forward, back and forward again. The quick checks also fail if the API changed without `pnpm api:generate`.

Every job stops after 12 minutes. A typical full run is 2 to 7 minutes.

## Required check

Branch protection on `main` requires the single check **CI result**. It passes when every job that needed to run passed (skipped jobs are fine) and fails if any job failed or was cancelled.

## Caches

- Composer download cache and `vendor/` (keyed by `composer.lock` and the PHP version)
- pnpm store (keyed by `pnpm-lock.yaml`)
- PHPStan result cache (`storage/phpstan`), so unchanged files aren't re-analysed

## Working with the hooks

- Hooks install automatically with `pnpm install` (root `prepare` script).
- Skip them only in an emergency: `LEFTHOOK=0 git commit ...`. CI still runs every check.
- Open pull requests as **drafts** while you're still pushing; click **Ready for review** once, when you want the full suite.

## Dependency updates (Renovate)

Renovate opens grouped pull requests every Monday before 9am IST: Laravel, PHP quality tools, web toolchain, GitHub Actions. Security fixes open immediately with the `security` label. Low-risk dev-only patches merge themselves once CI is green. Expo SDK minor and major upgrades are never automatic; they're planned as their own sprint task.

Renovate needs its GitHub app installed on the repository once: https://github.com/apps/renovate.

## CI minutes report

On the 1st of every month, the **CI minutes report** workflow posts last month's billable minutes by workflow and by job in its run summary. Run it any time from the Actions tab (choose a month), or locally:

```bash
GITHUB_TOKEN=$(gh auth token) pnpm ci:usage 2026-12 srinivas182/reos-mcs
```
