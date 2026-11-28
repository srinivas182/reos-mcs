# ADR 0073: Provider categories drive onboarding, with typed profile fields

- Status: Accepted (B15.1)
- Date: 2026-11-30 (Sprint 15; spec in docs/sprints/S15-spec.md)

## Context

The super admin manages provider categories on screen 68 (A6.1). Each category has:

- a licence check;
- a verification;
- profile fields;
- the number of areas on Pro;
- flat monthly prices per state and tier.

Two things weren't set up for onboarding. The licence check was free text ("NMLS", "TREC inspector", "ID check + E&O"), and profile fields were plain labels with no type. So onboarding couldn't follow the settings, and a new category needed code. Providers whose work wasn't listed had no way to ask for it; the admin's "requested" counts were seeded by hand.

## Decision

1. **A licence step per category.** `provider_categories.licence_kind` is one of:
   - `nmls`: checked by a REOS reviewer;
   - `trec_inspector` or `tdi`: checked against the regulator's data in seconds (B10.3a);
   - empty: business details and a photo ID.

   The migration sets it from the old text. The free-text check and verification stay as the wording providers see.
2. **Typed profile fields.** Each field is `{key, label, type, options}`:
   - the type is `text`, `list` (one or more values, from the admin's options when given) or `yes_no`;
   - keys come from labels and are unique in the category;
   - old labels become text fields;
   - the admin dialog edits fields as rows (label, type, values), and plain labels still work for old clients.
3. **Onboarding reads the settings** (`App\ProviderNetwork\ProviderJoining`):
   - the grid is the live categories, with the licence step and the flat prices in the provider's state;
   - the provider's choice and answers are kept in `provider_profiles` before they buy;
   - answers are checked against each field's type;
   - changing category drops answers to fields the new one doesn't have.
4. **Request a category.** A provider names the work that isn't listed:
   - it becomes, or adds to, a `requested` category in the admin's queue;
   - each person is counted once (`provider_category_requests`), with their note shown to the admin;
   - asking for a live category points back to the grid;
   - requests are audited.
5. **Where:**
   - API: `GET /api/v1/provider/categories`, `POST /api/v1/provider/categories/requests`, `GET /api/v1/provider/profile`, `PUT /api/v1/provider/profile/category` and `PUT /api/v1/provider/profile/fields`.
   - Web: `/provider/join`.
   - Phone: Me → Join the provider network.

   B15.2 puts this step inside the full joining flow.

## Alternatives considered

- **A form builder with validation rules per field.** This offers more control, but the categories in the journey need only three types, and every extra type has to work in both the web and phone forms.
- **Keeping the choice on the provider business tenant.** That tenant exists only after the plan is bought (B15.2). Keying the profile by person lets onboarding start before payment.

## Consequences

- Adding a category, its fields, licence step and prices needs no code or release.
- Provider counts on screen 68 are real: providers who chose the category.
- More field types (a number, a link) can be added in `FIELD_TYPES` with a control on each client.
