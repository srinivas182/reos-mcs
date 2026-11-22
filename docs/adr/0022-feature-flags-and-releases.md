# 0022: Feature flags, rollouts and releases

**Status:** accepted (A6.2, 22 November 2026).

## Context

REOS ships into live tenants every sprint. A new feature (messages, notifications, video rooms) must reach REOS staff first, then one or two tenants, then a state, then everyone, and be switched off at once if it misbehaves. Screen 60 already had six per-tenant feature switches (`TenantFeature`), stored in `organisations.features`, with defaults per tenant type and a beta feature that "is switched on from Features and releases (screen 70)". LaunchDarkly and similar services do flags well but hold user attributes outside REOS and are priced per seat; Laravel Pennant stores answers in our own database but has no screen, rollout steps, release notes or rollback.

## Decision

1. **Laravel Pennant, database store.** Every flag the code checks is declared in `App\Features\FlagCatalogue` (key, name, description, owner, starting stage, whether it is a tenant feature). `Flags::define()` registers each with Pennant; Pennant stores the answer per audience in `features`. The rollout itself lives in `feature_flags` (one row per flag, created at its starting stage the first time it is read), so flags are added in code and moved on screen 70.
2. **Audience (the Pennant scope).** `Audience` carries the tenant (id, type, states, its own switches), the person's active role, the person and whether they are REOS staff (super admin or the platform organisation). It is stored as tenant and role (or person and role outside a tenant). `Flags::forUser()` builds it from the active role; `Flags::forTenant()` and `Organisation::hasFeature()` from the tenant alone.
3. **The answer**, in order: turned off everywhere (kill switch) → off; limited to some roles and this role isn't one → off; a tenant feature the tenant switched itself (screen 60, switchable features only) → its choice; inside the rollout → on (a tenant feature: its type's default, so nothing changed for existing tenants); otherwise off.
4. **Rollout steps** (`RolloutStage`): off → REOS staff → named tenants → states → a share of tenants → everyone, each including the ones before. The share uses a stable bucket (`crc32(flag:tenant) % 100`), so the same tenants stay in as it grows; people outside a tenant are bucketed by person.
5. **Every change forgets stored answers.** A rollout change, kill, restore, release or automatic rollback purges that flag in Pennant; a tenant's features, states or type changing purges every flag. Pennant's in-memory cache is flushed too.
6. **Automatic rollback.** `FlagUsage` (bound per request) counts, per minute in the cache, one use for each flag that was on in a request, and one error for each such flag when the request reports an exception (the exception reporter in `bootstrap/app.php`). `reos:flags:watch` (every minute) saves the counts to `feature_flag_metrics` and, if a flag's rate stayed at or above its threshold (errors per 100 uses, default 5) in each of the last 10 minutes with at least 10 uses each minute, steps it back one stage, records `flag.rolled_back` and emails the super admins. A flag is judged only on minutes after its last stage change. Canary cells wait for the AWS build; until then the rollback is per feature.
7. **Features and releases (screen 70).** The flag table (feature, rollout step with tenants, states and share, who sees it, errors since the rollout), Change rollout, Turn off everywhere (immediately, with Undo, not a confirmation) and Turn back on; releases (version, date, plain-words notes per role, the flags they rolled out, how many people dismissed them) and New release, which publishes at once and rolls the ticked flags out to everyone. Super admin with two-step only; every change audited with before and after (`flag.*`, `release.published`; area "Features and releases").
8. **What's new.** The latest published release from the last 30 days with a note for the person's active role, until they dismiss it (`release_reads`): a card at the top of every web page and on Today in the app (`GET /api/v1/me/whats-new`, `POST /api/v1/me/whats-new/{release}/dismiss`). Dismissing on one hides it on the other.
9. **The tenant features become flags.** The six `TenantFeature` cases are catalogue flags starting at "everyone" (Recruiting agents, beta, starts off and is turned on by rollout, never by the tenant switch). The Sprint 6 features (`messages`, `notification_centre`, `video_rooms`) start at REOS staff.

## Consequences

- Flags never leave REOS and cost nothing per seat; the trade-off is that rules are limited to the steps above (no arbitrary targeting rules).
- Pennant writes one row per flag and audience the first time it is checked; purges are coarse (per flag) but rare.
- A flag that is still in the catalogue but no longer checked keeps its row; removing a flag means removing it from the catalogue and the code together.
