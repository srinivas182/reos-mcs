#!/usr/bin/env bash
# Nightly backup of the preview database (cron: 0 3 * * * deploy/preview/bin/backup.sh).
# Keeps seven days in deploy/preview/backups (gitignored).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p backups
docker compose exec -T postgres pg_dump -U reos -Fc reos > "backups/reos-$(date +%F).dump"
find backups -name 'reos-*.dump' -mtime +7 -delete
