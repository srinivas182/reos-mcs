# ADR 0001: One monorepo for platform, web and mobile

- Status: Accepted
- Date: 2026-11-20

## Context

REOS has a Laravel back end with a React web app, and a React Native mobile app. They share design tokens, API contracts and release timing. The team is PHP/Laravel and React developers, plus one React Native developer.

## Decision

Keep everything in one repository, `reos-mcs`, using pnpm workspaces and Turborepo:

- `apps/platform`: Laravel + Inertia React web app and the REST API.
- `apps/mobile`: Expo app, built and submitted with EAS.
- `packages/*`: shared TypeScript code (tokens, config, and from Sprint 1.3 the generated API client).

## Alternatives considered

- **Separate repositories for web and mobile.** Simpler permissions, but every API change needs coordinated pull requests across repos and the shared tokens would need publishing. Rejected.
- **Nx instead of Turborepo.** More features, more configuration. Turborepo's task caching is enough for two apps.

## Consequences

- One pull request can change the API, the web screen and the mobile screen together.
- CI must use path filters so mobile-only changes don't run the PHP suite (Sprint 1.2).
- Composer manages PHP dependencies inside `apps/platform`; pnpm manages all JavaScript.
