# Google Calendar responses for the contract tests

Shaped from Google's documented responses (Calendar API v3 `events.list`,
`events.insert`, `events.patch` with `If-Match`, `events.get`,
`events.watch`, `calendarList.list`, the OAuth token and OpenID userinfo
endpoints). The tests never call Google.

`PLACEHOLDER_*` values are filled in by the tests (the event id REOS
derives, REOS's own id, and the time of a change made in Google).

**Re-record from the sandbox** once the `google-calendar` test keys exist
(B4.1 "done when"): sign in a test user, call the same endpoints, strip
personal data, and replace these files. The tests should pass unchanged.
