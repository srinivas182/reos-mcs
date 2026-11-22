#!/usr/bin/env bash
# Creates pilot user accounts from a CSV (see docs/preview.md) and prints a
# sheet with each person's temporary password, for Nelle to hand out.
#   deploy/preview/bin/pilots.sh pilots.csv > pilot-passwords.csv
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f "${1:-}" ] || { echo "Usage: bin/pilots.sh pilots.csv"; exit 1; }
docker compose exec -T web php artisan reos:preview:pilots - < "$1"
