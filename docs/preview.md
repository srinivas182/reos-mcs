# The preview environment

A small private copy of REOS for usability checks with pilot users (Sprint 7, P7.0). It isn't staging or production. The AWS build stays at the end, as decided on 20 November 2026.

What's different from a normal install:
- **Private.** Nothing listens on the internet. Cloudflare Tunnel carries traffic in, and Cloudflare Access asks for an email one-time code before a browser reaches REOS. REOS's own sign-in still applies.
- **Nothing goes out.**
  - Every integration runs on its simulator.
  - Email lands in the Mailpit container.
  - Texts and pushes land in the simulators' outboxes.
  - Video rooms are simulated name tiles.
- **One code for everything.** Any code REOS asks for (phone verification, texts) is `123456`. A banner on every web and phone screen says so, and says it's a preview.
- **Test data only.** It's seeded with the journey personas. Pilot users get their own accounts next to them. It's wiped after Sprint 7.

Switched on by `REOS_PREVIEW=true` (never on in production, even if set). Files: `deploy/preview/`.

## 1. What you need

| Need | Notes |
| --- | --- |
| A Linux server with Docker | 2 vCPU and 4 GB RAM is enough for 40 people at once. Any provider; no inbound ports need opening. |
| A subdomain | `preview.reos.com` (or another), on Cloudflare DNS. |
| A Cloudflare account | Zero Trust free plan (up to 50 users) covers the tunnel and Access. |
| An Expo account | To build the phone app for the pilot users' phones (section 5). |

## 2. Cloudflare (once)

1. **Tunnel:** Zero Trust › Networks › Tunnels › Create a tunnel (Cloudflared).
   - Copy its token into `CLOUDFLARE_TUNNEL_TOKEN`.
   - Add a public hostname: `preview.reos.com` → `http://caddy:80`.
2. **Access for browsers:** Zero Trust › Access › Applications › Add (self-hosted) for `preview.reos.com`.
   - Policy **Allow**, with an email list: the pilot users, Nelle, Mayura and the client.
   - Login method **One-time PIN**.
3. **The phone app can't answer Access's email code.** Add a second application for `preview.reos.com/api` and one for `preview.reos.com/app` (live updates), each with a **Bypass** policy for everyone. These paths still need a REOS sign-in, and REOS rate-limits them.

## 3. Install and update

```bash
git clone https://github.com/srinivas182/reos-mcs.git && cd reos-mcs
cp deploy/preview/.env.example deploy/preview/.env
# Fill in every CHANGE-ME except APP_KEY and REOS_CREDENTIALS_KEY (generated on the first run).
deploy/preview/bin/deploy.sh
```

- The first run builds the image, generates the keys, creates the database and seeds the personas.
- Later runs (`git pull && deploy/preview/bin/deploy.sh`) migrate only, so the pilot users' data stays.
- **Nightly backup:** add `0 3 * * * /path/to/reos-mcs/deploy/preview/bin/backup.sh` to the server's crontab. It keeps seven days.

## 4. Pilot users

Make a CSV with one row per person (participant ids as in `docs/usability/check-1.md`; no other personal details):

```csv
participant,name,email,role,language
P01,Maria Lopez,maria@example.com,candidate,es
P02,Tom Reed,tom@example.com,broker,en
```

- `role` is one of: candidate, agent, broker, mentor, instructor, school_admin, association_admin, provider.
- `language` is `en` or `es`.

Then run:

```bash
deploy/preview/bin/pilots.sh pilots.csv > pilot-passwords.csv
```

- Each person joins the same organisation as the persona for their role (Texas Realty School, Lone Star Realty and so on), with the same roles, so the journeys' relationships work.
- The output has each person's temporary password. Hand it over in person or by phone, never in the same email as the link. Delete the file afterwards.
- Add the same emails to the Cloudflare Access policy.

## 5. Phones

Pilot users need a development build of the app (Expo Go can't run REOS's video and push modules):

```bash
cd apps/mobile
npx eas-cli@latest build --profile preview --platform android   # an APK link to share
npx eas-cli@latest build --profile preview --platform ios       # internal distribution; register each iPhone first
```

The `preview` profile points the app at `https://preview.reos.com`.

## 6. Resetting and removing

- `deploy/preview/bin/reset.sh` wipes everything back to the personas. It asks first. Run `bin/pilots.sh` again afterwards.
- **After Sprint 7:**
  - run `docker compose -f deploy/preview/compose.yaml down -v` on the server;
  - delete the tunnel and the Access applications;
  - delete the server.

  The usability findings live in GitHub issues, not in the preview.
