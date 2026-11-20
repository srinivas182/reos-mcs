# REOS repository conventions

Read `README.md` and `docs/adr/` first.

- Monorepo: pnpm workspaces + Turborepo. Use `pnpm`, never npm or yarn. PHP dependencies use Composer inside `apps/platform`.
- Back end: Laravel 13. Controllers are thin and final; business logic goes in `app/Services` or domain classes; validation in Form Requests; API output through API resources. New API routes go in `routes/api.php` under `/api/v1`.
- Static analysis: PHPStan level 9 must pass. Don't add baseline entries; fix the types.
- Tests: Pest for PHP (feature tests for every endpoint), Jest for mobile. Every change ships with tests.
- Web: Inertia + React + TypeScript + Tailwind, shadcn/ui components, Wayfinder for routes.
- Mobile: Expo Router, functional components, shared tokens from `@reos/tokens`. Talk to the server only through the REST API.
- Design standard: minimum 16pt body text, 48pt touch targets, plain language, high contrast; luxury, calm and modern.
- Security: never commit secrets, `.env` files or signing keys. Validate all input, escape output, rate-limit public endpoints.
- Branches: `sNN.N-short-name`. Open pull requests as drafts (quick checks only) and mark them ready once for the full suite. "CI result" must be green before merging. See `docs/ci.md`.
- Git hooks (Lefthook) run on commit and push; don't skip them except in an emergency.
