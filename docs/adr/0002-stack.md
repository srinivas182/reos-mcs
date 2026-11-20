# ADR 0002: Technology stack

- Status: Accepted
- Date: 2026-11-20

## Decision

| Layer | Choice | Why |
| --- | --- | --- |
| Language | PHP 8.5 | Latest version supported across our tools; 8.6 (released 19 Nov 2026) once FrankenPHP and our packages support it |
| Framework | Laravel 13 | Team expertise, first-party auth, queues, events, Octane |
| App server | Octane on FrankenPHP | Long-lived workers for high throughput; HTTP/3 |
| Web UI | Inertia v3, React 19, TypeScript, Tailwind 4, shadcn/ui | Server routing with a React UI, no separate SPA back end |
| Typed routes | Wayfinder | Generated TypeScript for Laravel routes and actions |
| Auth | Fortify (2FA, passkeys); Sanctum for mobile tokens (Sprint 2) | First-party, audited |
| Mobile | Expo SDK 58 (React Native 0.88), Expo Router, EAS | One React Native developer can ship iOS and Android; over-the-air updates |
| Quality | Pest 4, Larastan level 9, Pint; ESLint, TypeScript strict, Jest | Strictest practical settings from day one |
| Data | Aurora PostgreSQL 17 with row-level security and pgvector, Redis, Meilisearch, ClickHouse | See the PRD |

## Notes

- `apps/platform/composer.json` currently allows PHP ^8.3 because the build sandbox has 8.3. CI runs 8.5, and Sprint 2 raises the constraint to ^8.5.
- No Node back end. Node runs only build tooling.
