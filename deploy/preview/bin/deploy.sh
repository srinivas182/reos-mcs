#!/usr/bin/env bash
# Builds the preview image from the current checkout and (re)starts it.
# First run: generates the app and credentials keys, migrates and seeds.
# Later runs: migrates only, so pilot users' data stays.
set -euo pipefail
here="$(cd "$(dirname "$0")/.." && pwd)"
root="$(cd "$here/../.." && pwd)"
cd "$here"

[ -f .env ] || { echo "Copy .env.example to .env and fill it in first."; exit 1; }
for key in POSTGRES_PASSWORD DB_PASSWORD REVERB_APP_SECRET MEILISEARCH_KEY REOS_DEMO_PASSWORD CLOUDFLARE_TUNNEL_TOKEN; do
  if grep -q "^${key}=CHANGE-ME" .env; then echo "Set ${key} in deploy/preview/.env."; exit 1; fi
done

# shellcheck source=/dev/null
set -a; . ./.env; set +a
tag="$(git -C "$root" rev-parse --short HEAD)"
docker build -f "$here/Dockerfile" -t "reos-preview:$tag" -t reos-preview:latest \
  --build-arg VITE_REVERB_APP_KEY="$REVERB_APP_KEY" --build-arg VITE_REVERB_HOST="$REVERB_HOST" \
  --build-arg VITE_REVERB_PORT="$REVERB_PORT" --build-arg VITE_REVERB_SCHEME="$REVERB_SCHEME" "$root"

run() { docker compose run --rm --no-deps web "$@"; }
docker compose up -d postgres redis meilisearch mailpit

if grep -q '^APP_KEY=CHANGE-ME' .env; then
  sed -i "s|^APP_KEY=.*|APP_KEY=$(run php artisan key:generate --show --no-interaction | tail -1)|" .env
  sed -i "s|^REOS_CREDENTIALS_KEY=.*|REOS_CREDENTIALS_KEY=$(run php artisan reos:credentials:key --show | tail -1)|" .env
  run php artisan migrate --force --seed
else
  run php artisan migrate --force
fi

REOS_PREVIEW_TAG="$tag" docker compose up -d --remove-orphans
echo "REOS preview $tag is starting at ${APP_URL}."
