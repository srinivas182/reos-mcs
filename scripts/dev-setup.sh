#!/usr/bin/env bash
# One-command local setup. Run with `pnpm dev:setup`. Safe to run again.
set -euo pipefail
cd "$(dirname "$0")/.."

step() { printf '\n\033[1;34m▶ %s\033[0m\n' "$1"; }

step "Checking your machine"
bash scripts/dev-doctor.sh || { echo; echo "Fix the problems above, then run 'pnpm dev:setup' again."; exit 1; }

step "Installing JavaScript packages"
pnpm install

step "Starting local services (PostgreSQL, Redis, Meilisearch, Mailpit, S3)"
bash scripts/dev-up.sh

step "Installing PHP packages"
(cd apps/platform && composer install --no-interaction)

step "Preparing apps/platform/.env"
if [ ! -f apps/platform/.env ]; then
  cp apps/platform/.env.example apps/platform/.env
  (cd apps/platform && php artisan key:generate --ansi)
else
  echo "Keeping your existing .env"
fi

step "Creating the database and demo logins"
(cd apps/platform && php artisan migrate --seed --force)

step "Building the web app once"
(cd apps/platform && pnpm build)

cat <<'DONE'

✅ REOS is ready.

  Start everything:   cd apps/platform && composer dev   → http://localhost:8000
  Mobile app:         cd apps/mobile && pnpm dev
  E-mail inbox:       http://localhost:8025
  API docs:           http://localhost:8000/docs/api

  Demo logins (password: password)
    Candidate          sofia.reyes@demo.reos.test
    Agent              james.miller@demo.reos.test
    Broker             linda.park@demo.reos.test
    Mentor             dana.brooks@demo.reos.test
    Instructor         rachel.hughes@demo.reos.test
    School admin       paul.diaz@demo.reos.test
    Service provider   ana.ruiz@demo.reos.test
    Association admin  karen.webb@demo.reos.test
    Super admin        ops@demo.reos.test
DONE
