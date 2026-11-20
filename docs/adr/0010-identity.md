# ADR 0010: Accounts, sign-in and devices

- Status: Accepted
- Date: 2026-11-20 (Sprint 3, A3.1; decisions in docs/sprints/S03-spec.md)

## Context

The journeys ask for one-tap sign-in on the phone (Apple, Google or a phone code, with no passwords), and on the web email and password, Google, Apple and passkeys. A person is one account across every role and device. The starter kit (Fortify) already gives the web email and password, email verification, password reset, passkeys and authenticator-app two-step.

## Decision

1. **Phone sign-in with the provider's own SDK and an ID token.**
   - The app sends the token to `POST /api/v1/auth/{google|apple}`.
   - The server checks the signature against the provider's published keys (cached for an hour, a minute of clock leeway), the issuer, the audience and the expiry.
   - Audiences come from Settings › Integrations (`google-sign-in`, `apple-sign-in`); Apple also accepts the app's bundle id.
2. **Phone codes go through Twilio Verify** (`twilio-verify`): `POST /api/v1/auth/phone/start` and `/verify`.
   - Limits: one code a minute and five an hour per number, and 20 an hour per network. Five wrong codes lock the number for 15 minutes. Audit rows show only the last four digits.
3. **One account per person.**
   - A Google or Apple identity is linked in `social_accounts`.
   - A sign-in joins an existing account **only on an email the provider has verified**. An unverified email is never stored as the account's email.
   - If the matching account's own email was never verified, its password and tokens are dropped when the provider proves ownership (this stops pre-registration account takeover).
   - Email, password and name are now optional: phone sign-ups give their name in the 60-second setup (A3.3), and a verified phone stands in for a verified email.
4. **Devices.**
   - The phone uses Sanctum, one token per installed app (`device_id`, platform, app version). Signing in again on the same app replaces its token.
   - Tokens stop working after 30 days unused.
   - `known_devices` remembers every app a person has used, so a sign-in on a new one sends an email, and signing out doesn't make it "new" again.
   - People list and sign out devices with `GET /api/v1/me/devices` and `DELETE /api/v1/me/devices/{id}`.
5. **Demo data.** Each sign-in integration starts on the Simulator like every other.
   - On demo data, `simulator:<email>` tokens sign in as that email and demo phone codes are written to the log.
   - **Never in production:** there, a sign-in option on demo data is treated as switched off.
6. **Errors** (`SignInFailed`) answer in the person's language with a stable `reason` key, and are never reported to Sentry. Every sign-in, sign-out, device revocation and failed code is in the audit log.

7. **Web sign-in with Google and Apple** uses OpenID Connect: state, nonce and PKCE (PKCE for Google only, since Apple doesn't support it).
   - The ID token goes through the same check as the phone app's.
   - The callback addresses are the ones Settings › Integrations shows: `/integrations/{google-sign-in|apple-sign-in}/callback`. CSRF-exempt, protected by the one-time `state`.
   - Apple answers with a cross-site POST, which doesn't carry the SameSite=Lax session cookie. The answer is held for five minutes and handed to a same-site GET.
   - People with two-step turned on still answer its challenge.
   - On demo data the flow goes to a persona picker (`auth/demo-sign-in`), outside production only.
8. **Web phone-code sign-in** uses the same `PhoneSignIn` service, so people who signed up on the phone with a number can use the web app.
9. **Browsers are devices too.**
   - A `reos_device` cookie identifies each browser, and every web sign-in (Fortify's `Login` event) goes through `SignInRecorder`, like the app. The same new-device email applies.
10. **Deleting an account.**
    - People are signed out everywhere at once; the account is deleted after 30 days by `reos:accounts:purge`, daily.
    - Signing in again before then cancels it.
    - Browsers signed in before the request are signed out by `EnsureAccountActive`.
    - The app needs a sign-in from the last 15 minutes (`DELETE /api/v1/me`); the web needs the password, or a confirmation for people without one.

## Consequences

- **Web sign-in pages** (A3.1b) add Google and Apple with Socialite's redirect flow, on the same `social_accounts` links.
- **Phone screens** (A3.1c) keep the token in `expo-secure-store` and offer Face ID to reopen the app.
- **iOS release:** Apple sign-in needs the Apple Developer account. Until then iOS can't ship (guideline 4.8 wants a privacy-friendly login next to Google); Android and the web can.
- **`users.name` and `users.email` are nullable.** Code that shows a person's name falls back to "there" or their phone's last digits.
