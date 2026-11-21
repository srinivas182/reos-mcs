# ADR 0011: Roles, organisations and permissions

- Status: Accepted
- Date: 2026-11-21 (Sprint 3, A3.2; decisions in docs/sprints/S03-spec.md)

## Context

One person can be a candidate, an agent, a broker, a mentor, an instructor, a school admin, a service provider or an association admin, often more than one at once, and each in a particular brokerage, school or association. The platform also has its own super admin. Sprint 2 used an `is_super_admin` flag for the Integration Hub.

## Decision

1. **Spatie laravel-permission with teams.** A team is an **organisation** (`organisations`: type, name, parent).
   - Types: platform (REOS itself), brokerage, school, association, provider business.
   - It's minimal for now. A4.1 adds row-level security, the full hierarchy, branding and domains.
   - People who joined on their own hold their role in the REOS organisation ("On my own").
2. **Roles and permissions are defined in code**, in `App\Authorization\Role` and `Permission` (named `area.action`).
   - `RoleCatalogue::sync()` (`php artisan reos:roles:sync`) writes them to Spatie's tables. The A3.2 migration runs it once; deploys run it after migrations.
   - Catalogue roles are global, and any organisation can use them.
   - Roles and permissions that customers or plugins add are left alone.
   - Each role says which kinds of organisation it can be held in (a broker only in a brokerage, for example).
3. **The active role decides what a person can do**, not the union of every role they hold.
   - Each device remembers its last role (`known_devices.active_role`, `active_organisation_id`). The phone app's device comes from its token; a browser's comes from the `reos_device` cookie.
   - A device with no choice yet takes the role last chosen on any device, then the role with most responsibility (`Role::rank`).
   - Checks use `$user->can('integrations.manage')`. A `Gate::before` answers permission names from `ActiveRole`; other abilities go to policies. Spatie's own "any role I hold" check is switched off.
4. **Admin roles need two-step**: broker, school admin, association admin and super admin.
   - An authenticator app or a passkey counts as two-step.
   - Until it's set up, the role can be chosen but grants nothing. `/me` says `two_step.required`.
   - The `EnsureAdminTwoStep` middleware sends the person to Security settings to set it up, rather than just refusing them. The phone app gets the reason `roles.errors.twoStepRequired`.
5. **Granting and removing roles is audited** (`role.granted`, `role.removed`, with the actor and organisation).
   - REOS always keeps at least one super admin.
   - Demo data and test factories use `attach()`, which isn't audited.
6. **The API.**
   - `GET /api/v1/me` returns `roles`, `active_role`, `permissions` and `two_step`.
   - `PUT /api/v1/me/active-role` switches role.
   - The web app gets the same data in the shared `auth.roles` prop.
7. **Policies** cover people (`UserPolicy`), organisations (`OrganisationPolicy`) and the audit log (`AuditEventPolicy`). Screens check permissions in route middleware (`can:…`).
8. **`is_super_admin` is gone.** The migration moves anyone who had it to the super-admin role in the REOS organisation, and back again on rollback.

## Consequences

- New features add a permission to `Permission` and the roles that get it to `Role::permissions()`. A test (`RolesTest`) checks every role against every permission.
- Demo personas carry their roles and organisations (`Personas.php`). Linda Park (broker) and Dana Brooks (mentor) are also agents, to show the role switcher.
- Demo admins start without two-step on purpose, so the guided set-up can be shown.
- A3.2b adds the web role switcher, the People screen and the two-step guidance; A3.2c adds the switcher to the phone app's Me tab.
- A4.1 adds row-level security per organisation. Until then, organisation scoping is done in policies.
