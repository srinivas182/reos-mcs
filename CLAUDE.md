# REOS repository conventions

Read `README.md` and `docs/adr/` first.

- Monorepo: pnpm workspaces + Turborepo. Use `pnpm`, never npm or yarn. PHP dependencies use Composer inside `apps/platform`.
- Back end: Laravel 13. Controllers are thin and final; business logic goes in `app/Services` or domain classes; validation in Form Requests; API output through API resources. New API routes go in `routes/api.php` under `/api/v1`.
- Static analysis: PHPStan level 9 must pass. Don't add baseline entries; fix the types.
- Tests: Pest for PHP (feature tests for every endpoint), Vitest + Testing Library for web components, Jest for mobile. Every change ships with tests.
- UI: use semantic tokens only (`bg-card`, `text-muted-foreground`, `bg-reos-*`), never raw colours. Buttons and inputs are 48 px by default (`size="sm"` only in dense admin tables). Prefer `undoToast()` over confirmation dialogs for reversible actions; give every empty list an `EmptyState`.
- Web: Inertia + React + TypeScript + Tailwind, shadcn/ui components, Wayfinder for routes.
- Mobile: Expo Router, functional components, shared tokens from `@reos/tokens`. Talk to the server only through `@reos/api-client`; never hand-write request or response types.
- API contract: after changing any `/api/v1` endpoint, run `pnpm api:generate` and commit `packages/api-client`. CI fails if it's stale. Document endpoints with PHPDoc (Scramble reads it).
- Demo data: personas live in `database/seeders/Demo/Personas.php`; keep them in step with the journey documents.
- Local services: `compose.yaml` (PostgreSQL 17 + pgvector, Redis, Meilisearch, Mailpit, SeaweedFS S3). Tests in CI run on PostgreSQL.
- Integrations: every integration, vendor account and approval is an entry in `docs/integrations/register.yaml`; never hard-code a vendor list elsewhere. Follow `docs/integrations/credentials-policy.md`.
- Design standard: minimum 16pt body text, 48pt touch targets, plain language, high contrast; luxury, calm and modern.
- Security: never commit secrets, `.env` files or signing keys. Validate all input, escape output, rate-limit public endpoints.
- Branches: `sNN.N-short-name`. Open pull requests as drafts (quick checks only) and mark them ready once for the full suite. "CI result" must be green before merging. See `docs/ci.md`.
- Git hooks (Lefthook) run on commit and push; don't skip them except in an emergency.
