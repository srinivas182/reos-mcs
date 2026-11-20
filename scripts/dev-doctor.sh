#!/usr/bin/env bash
# shellcheck disable=SC2015 # `cond && ok || bad` is intentional: ok() always succeeds.
# Checks this machine can run REOS locally. Run with `pnpm dev:doctor`.
# Prints a clear fix for every problem and exits non-zero if anything is missing.
set -uo pipefail

ok() { printf '  \033[32m✔\033[0m %s\n' "$1"; }
bad() { printf '  \033[31m✘\033[0m %s\n      → %s\n' "$1" "$2"; problems=$((problems + 1)); }
warn() { printf '  \033[33m!\033[0m %s\n      → %s\n' "$1" "$2"; }
problems=0

version_ge() { [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -1)" = "$2" ]; }

echo "REOS doctor"
echo
echo "Tools"

if command -v php >/dev/null; then
  php_v=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
  if version_ge "$php_v" "8.5"; then ok "PHP $php_v"
  elif version_ge "$php_v" "8.3"; then warn "PHP $php_v works for now; the team standard is 8.5" "Install PHP 8.5 (Herd, Homebrew 'php', or the dev container)"
  else bad "PHP $php_v is too old" "Install PHP 8.5"; fi
  for ext in pdo_pgsql redis intl bcmath mbstring; do
    php -m | grep -qi "^$ext$" && ok "PHP extension $ext" || bad "PHP extension $ext is missing" "Install php-$ext with your package manager (redis: 'pecl install redis'), or use the dev container"
  done
else bad "PHP not found" "Install PHP 8.5, or open the repo in the dev container"; fi

command -v composer >/dev/null && ok "Composer $(composer --version 2>/dev/null | awk '{print $3}')" || bad "Composer not found" "https://getcomposer.org/download/"

if command -v node >/dev/null; then
  node_v=$(node -v | tr -d v)
  version_ge "$node_v" "22.12" && ok "Node $node_v" || bad "Node $node_v is too old" "Install Node 22.12+ (nvm use)"
else bad "Node not found" "Install Node 22 (see .nvmrc)"; fi

command -v pnpm >/dev/null && ok "pnpm $(pnpm -v)" || bad "pnpm not found" "Run 'corepack enable'"

if command -v docker >/dev/null; then
  if docker info >/dev/null 2>&1; then ok "Docker is running"; else bad "Docker is installed but not running" "Start Docker Desktop / OrbStack / the docker service"; fi
  docker compose version >/dev/null 2>&1 && ok "Docker Compose $(docker compose version --short)" || bad "Docker Compose v2 not found" "Update Docker"
else bad "Docker not found" "Install Docker Desktop, OrbStack or Docker Engine"; fi

echo
echo "Ports (127.0.0.1)"
port_busy() {
  if command -v lsof >/dev/null; then lsof -iTCP:"$1" -sTCP:LISTEN -n -P >/dev/null 2>&1
  else (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; fi
}
# Port overrides from the root .env (see compose.yaml), else the defaults.
env_port() { local v; v=$(grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2 | tr -d '"'"'"' '); echo "${v:-$2}"; }
running=$(docker compose ps --services --status running 2>/dev/null || true)
for entry in postgres:FORWARD_DB_PORT:5432 redis:FORWARD_REDIS_PORT:6379 meilisearch:FORWARD_MEILISEARCH_PORT:7700 \
             mailpit:FORWARD_MAILPIT_PORT:1025 mailpit:FORWARD_MAILPIT_DASHBOARD_PORT:8025 s3:FORWARD_S3_PORT:8333; do
  IFS=: read -r svc var default <<<"$entry"
  port=$(env_port "$var" "$default")
  if echo "$running" | grep -qx "$svc"; then ok "$port used by REOS $svc (running)"
  elif port_busy "$port"; then bad "Port $port ($svc) is taken by another program" "Stop that program, or set $var to a free port in the root .env"
  else ok "Port $port ($svc) is free"; fi
done

echo
echo "Project"
[ -f apps/platform/.env ] && ok "apps/platform/.env exists" || warn "apps/platform/.env is missing" "Run 'pnpm dev:setup'"
[ -d apps/platform/vendor ] && ok "Composer packages installed" || warn "Composer packages not installed" "Run 'pnpm dev:setup'"
[ -d node_modules ] && ok "pnpm packages installed" || warn "pnpm packages not installed" "Run 'pnpm install'"
[ -f .git/hooks/pre-commit ] && ok "Git hooks installed" || warn "Git hooks not installed" "Run 'pnpm install' (installs Lefthook)"

echo
if [ "$problems" -eq 0 ]; then echo "All good."; else echo "$problems problem(s) found."; exit 1; fi
