# ADR 0075: The provider profile: no contact details, checked before it's published, verified for agents

- Status: Accepted (B15.3)
- Date: 2026-11-30 (Sprint 15; spec in docs/sprints/S15-spec.md)

## Context

Provider screen 7 builds the profile agents see: photo and logo, a short bio ("Write it for me", with a Spanish version), services, specialities, a response-time goal, free resources and expert sessions. The spec sets three conditions:

- no phone or email on the profile;
- the text checked by the compliance engine before it's published;
- agents see a verified profile with the licence badge, when it was checked, the areas and the services.

## Decision

1. **The profile lives on `provider_profiles`** (B15.2). It holds:
   - the photo and logo paths;
   - `bio` and `bio_es`;
   - services and specialities;
   - `response_hours` (1, 2, 4, 8 or 24);
   - resources (`{title, url}`, https only);
   - sessions (`{title, minutes}`);
   - `profile_issues`;
   - `published`, the version agents see, with `published_at`.
2. **No contact details.** Saving refuses phone numbers, emails and web addresses in the bio, the services, the specialities and the resource and session titles. Agents reach providers through REOS, which keeps the RESPA and consent rules enforceable. Resource links must be https and appear only as links.
3. **Checked before it's published.** Every save runs the compliance engine on the new `provider` channel. That channel has the wording rules only:
   - misleading claims;
   - fair housing;
   - the unclear-wording second opinion;
   - banned phrases.

   A provider names no broker, so the broker, team and link rules don't apply. A clean save becomes the published version. A blocked one stays a draft with its issues, and agents keep seeing the last version that passed. Both outcomes are audited. Provider profiles are left out of brokerages' compliance logs.
4. **"Write it for me"** uses Claude through Bedrock (`ClaudeProviderBio`) to write both languages from the provider's own facts:
   - name and category;
   - category details;
   - areas;
   - services;
   - response goal.

   The facts are data, never instructions. Without Claude, a plain template writes them. Nothing is saved until the provider saves.
5. **The agent's view** (`ProviderProfiles::card`):
   - only live profiles with a published version, Partner first, filtered by category and city or ZIP code;
   - the licence badge with its number (not for photo IDs), whether it's verified and when it was checked;
   - the areas as labels;
   - the bio in the viewer's language.

   A provider can preview their own profile whatever its status. Photos are served through links signed for a day, so the phone can show them without a session.
6. **Where:**
   - API: `GET` and `PUT /api/v1/provider/profile/builder`, `POST /api/v1/provider/profile/bio`, `POST /api/v1/provider/profile/photo`, `GET /api/v1/providers` and `GET /api/v1/providers/{profile}`.
   - Web: `/provider/profile`, `/providers` and `/providers/{profile}`.
   - Phone: Me → Build your profile, and Me → Verified providers.

## Alternatives considered

- **A moderation queue for every profile change.** This means slower publishing and staff time. The compliance engine already decides the same rules automatically for agents' content.
- **Removing contact details automatically.** That's silent and surprising. Refusing with the reason teaches the rule once.

## Consequences

- The provider marketplace (Sprint 16) builds search, invites and messages on these cards.
- Spanish wording is checked against the same phrase lists, which are English. A Spanish phrase list is future work.
