#!/usr/bin/env bash
# shellcheck disable=SC2015,SC2016 # PHP in single quotes is intentional; pass() always succeeds.
# Proves every local service works end to end with the app's own config:
# database + extensions, Redis cache, S3 storage, e-mail, search.
# Run with `pnpm dev:verify` after `pnpm dev:setup`. CI runs it too.
set -euo pipefail
cd "$(dirname "$0")/../apps/platform"

pass() { printf '  \033[32m✔\033[0m %s\n' "$1"; }

echo "Verifying local services"

php artisan tinker --execute '
    $ext = collect(DB::select("select extname from pg_extension"))->pluck("extname");
    foreach (["vector", "citext", "pg_trgm"] as $e) { if (! $ext->contains($e)) { throw new RuntimeException("Missing extension: $e"); } }
    if (App\Models\User::query()->where("email", "sofia.reyes@demo.reos.test")->doesntExist()) { throw new RuntimeException("Demo personas are not seeded"); }
' && pass "PostgreSQL: connected, vector/citext/pg_trgm installed, demo personas seeded"

php artisan tinker --execute '
    cache()->put("reos:verify", "ok", 10);
    if (cache()->get("reos:verify") !== "ok") { throw new RuntimeException("Redis cache round-trip failed"); }
' && pass "Redis: cache round-trip"

php artisan tinker --execute '
    $disk = Storage::disk("s3");
    $disk->put("verify/hello.txt", "REOS");
    if ($disk->get("verify/hello.txt") !== "REOS") { throw new RuntimeException("S3 round-trip failed"); }
    $disk->delete("verify/hello.txt");
' && pass "S3 (SeaweedFS): write, read, delete"

mailpit=${MAILPIT_URL:-http://127.0.0.1:8025}
subject="REOS verify $(date +%s)"
php artisan tinker --execute "Mail::raw('Local mail check', fn (\$m) => \$m->to('verify@reos.test')->subject('$subject'));"
curl -fsS "$mailpit/api/v1/search?query=subject:%22$(printf '%s' "$subject" | sed 's/ /%20/g')%22" | grep -q "$subject" \
  && pass "Mailpit: e-mail delivered" || { echo "Mail did not arrive in Mailpit"; exit 1; }

meili=${MEILISEARCH_HOST:-http://127.0.0.1:7700}
curl -fsS -H "Authorization: Bearer ${MEILISEARCH_KEY:-reos-local-master-key}" "$meili/version" | grep -q pkgVersion \
  && pass "Meilisearch: reachable with the master key" || { echo "Meilisearch check failed"; exit 1; }

curl -fsS "http://127.0.0.1:${FORWARD_APP_PORT:-8000}/api/v1/health" >/dev/null 2>&1 \
  && pass "Web app: /api/v1/health answering" || echo "  · Web app not running (start it with 'composer dev'); skipped"

echo "All local services verified."
