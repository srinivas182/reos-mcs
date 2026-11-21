#!/usr/bin/env bash
# Creates the reos_app database user and hands it the local databases
# (ADR 0015). New volumes get this on first start; run it once for a volume
# created before tenancy (Sprint 4), then set DB_USERNAME=reos_app in .env.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec -T postgres psql -v ON_ERROR_STOP=1 -U reos -d reos < docker/postgres/app-user.sql
echo "✔ reos_app is ready. In apps/platform/.env: DB_USERNAME=reos_app, DB_PASSWORD=reos-app-local"
