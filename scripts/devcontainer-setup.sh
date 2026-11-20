#!/usr/bin/env bash
# First-run setup inside the dev container (Codespaces or VS Code).
# The services already run as sibling containers; this installs packages,
# creates the bucket, migrates and seeds. Safe to run again.
set -euo pipefail
cd "$(dirname "$0")/.."

pnpm install
(cd apps/platform && composer install --no-interaction)

if [ ! -f apps/platform/.env ]; then
  cp apps/platform/.env.example apps/platform/.env
  (cd apps/platform && php artisan key:generate --ansi)
fi

# Create the local S3 bucket (no-op if it exists).
curl -fsS -X PUT http://s3:8333/reos >/dev/null || curl -fsS -o /dev/null http://s3:8333/reos

(cd apps/platform && php artisan migrate --seed --force && pnpm build)

echo
echo "✅ REOS dev container is ready. Run: cd apps/platform && composer dev"
echo "   Demo logins are listed in README.md (password: password)."
