# ADR 0004: Local development environment

- Status: Accepted
- Date: 2026-11-20

## Context

Developers need the same services production uses (PostgreSQL with pgvector, Redis, search, e-mail, S3) without installing them by hand, on macOS, Windows and Linux.

## Decision

- **Docker Compose** at the repo root runs the services; PHP and Node run on the developer's machine (fastest file watching), or everything runs in the **dev container** / Codespaces.
- Services: PostgreSQL 17 + pgvector, Redis 7.4, Meilisearch 1.57, Mailpit, SeaweedFS (S3). Every port binds to `127.0.0.1` only; every image is pinned to an exact version (Renovate proposes updates).
- **SeaweedFS instead of MinIO**: MinIO stopped publishing community Docker images and binaries in October 2025. SeaweedFS is Apache-2.0, S3-compatible and runs as one container.
- `pnpm dev:setup` does everything for a new machine; `pnpm dev:doctor` diagnoses problems with a fix for each; `pnpm dev:verify` proves every service end to end. CI runs the same scripts whenever the environment changes, so the setup can't silently rot.
- CI runs the platform tests on PostgreSQL (not SQLite), and checks that migrations roll back cleanly.

## Alternatives considered

- **Laravel Sail**: MySQL-first, puts PHP in Docker (slow file sync on macOS), not shaped to our services.
- **Laravel Herd**: macOS/Windows only, can't match production services.
- **DDEV / Lando**: capable, but another tool to learn for little gain over Compose.

## Consequences

- Docker is required for local development (or use Codespaces).
- ClickHouse (analytics) joins the Compose file in the analytics sprint, not before it's used.
