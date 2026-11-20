# ADR 0007: Translations (English and Spanish)

- Status: Accepted
- Date: 2026-11-20

## Context

REOS launches in Texas in English and Spanish, on web, mobile and email. Translations must never drift between those three, and no screen should ship half-translated.

## Decision

- `packages/i18n/src/locales/{en,es}.json` is the single source, nested by area (`common`, `nav`, `appearance`, `tour`, `mobile`, …). English is the source; `status.json` records each language's review state (Spanish is a reviewed machine draft until professional translation before the pilot).
- Web and mobile use i18next + react-i18next through `createI18n()`. Keys are type-checked (a typo in `t('…')` fails `tsc`).
- Laravel gets generated flat copies, `apps/platform/lang/{locale}.json`, with `{{name}}` converted to `:name`. So `__('nav.settings')` and emails use the same text.
- `pnpm --filter @reos/i18n test` runs in CI. It fails when:
  - a key is missing or extra in Spanish
  - a string is empty
  - the placeholders differ from English
  - the Laravel copies are stale

  A pre-commit hook regenerates the Laravel copies.
- **Which language is used:** the signed-in person's saved language (`users.locale`), then the device cookie, then the browser's Accept-Language, then English. The server renders `<html lang>`, so the first paint is in the right language. Mobile follows the phone's language until sign-in.
- Each language is named in its own language in the switcher ("English", "Español").

## Consequences

- Every user-facing string goes through `t()` / `__()`; reviewers reject hard-coded text.
- The Fortify sign-in and registration pages from the starter kit are still English; they're rebuilt and translated with identity in Sprint 3 (A3.1), together with Spanish validation messages.
