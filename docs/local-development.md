# Local development

## First time

```bash
corepack enable
pnpm dev:setup
```

This checks your machine (`pnpm dev:doctor`), starts the services, installs PHP and JavaScript packages, creates `apps/platform/.env`, migrates, seeds the demo logins and builds the web app once. Run it again any time; it's safe.

Prefer not to install PHP? Open the repo in the dev container (VS Code: "Reopen in Container") or a GitHub Codespace. Setup runs automatically.

## Every day

```bash
pnpm dev:up                          # start services (if they're stopped)
cd apps/platform && composer dev     # Laravel, queue worker, logs and Vite
```

| Command | What it does |
| --- | --- |
| `pnpm dev:up` | Start the services and wait until they're healthy |
| `pnpm dev:down` | Stop them (data is kept) |
| `pnpm dev:reset` | Wipe all local data and start fresh, then run `php artisan migrate --seed` |
| `pnpm dev:doctor` | Check tools, PHP extensions and ports, with a fix for each problem |
| `pnpm dev:verify` | Prove the database, cache, S3, e-mail and search all work |
| `pnpm api:generate` | After changing an API endpoint: re-export the OpenAPI spec and regenerate the typed client |

## Port clashes

If something else already uses a port, create a `.env` file at the repo root (it's git-ignored) and move REOS:

```dotenv
FORWARD_DB_PORT=55432
FORWARD_REDIS_PORT=56379
```

Then update the matching value (`DB_PORT`, `REDIS_PORT`, ...) in `apps/platform/.env`. Variables: `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `FORWARD_MEILISEARCH_PORT`, `FORWARD_MAILPIT_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT`, `FORWARD_S3_PORT`.

## Mobile on a real phone

Your phone can't reach `localhost`. Use your computer's LAN address:

```bash
cd apps/mobile
EXPO_PUBLIC_API_URL=http://192.168.1.20:8000 pnpm dev
```

and start Laravel listening on all interfaces: `php artisan serve --host=0.0.0.0`.

## Troubleshooting

- **"Docker is installed but not running"**: start Docker Desktop / OrbStack.
- **Migrations fail with "extension vector does not exist"**: the database volume predates the init script. Run `pnpm dev:reset`.
- **Uploads fail**: run `pnpm dev:up` again; it re-creates the `reos` bucket if needed.
- **E-mails don't arrive**: they never leave your machine. Open http://localhost:8025.
