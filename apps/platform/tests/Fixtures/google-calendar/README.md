# Google Calendar responses for the contract tests

Shaped from Google's documented responses (Calendar API v3 `events.list`,
`events.watch`, OAuth token and OpenID userinfo endpoints). The tests never
call Google.

**Re-record from the sandbox** once the `google-workspace` test keys exist
(B3.1 "done when"): sign in a test user, call the same endpoints, strip
personal data, and replace these files. The tests should pass unchanged.
