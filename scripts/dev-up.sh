#!/usr/bin/env bash
# Starts the local services and waits until every one is healthy.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose up -d --wait postgres redis meilisearch mailpit s3
docker compose run --rm s3-bucket >/dev/null
echo "Local services are up: PostgreSQL :5432 · Redis :6379 · Meilisearch :7700 · Mailpit :8025 · S3 :8333"
