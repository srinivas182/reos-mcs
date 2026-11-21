# Outlook Calendar (Microsoft Graph) responses for the contract tests

Shaped from Microsoft's documented responses: the identity platform's token
endpoint, Graph `GET /me`, `GET /me/calendars`, calendar-view delta
(`@odata.nextLink`, `@odata.deltaLink`, `@removed`), event create, update
(`If-Match`, 412) and get, `POST /subscriptions`, and change notifications.
The tests never call Microsoft.

`PLACEHOLDER_*` values are filled in by the tests.

**Re-record from the sandbox** once the `outlook-calendar` test keys exist (a
Microsoft 365 Developer Program tenant): sign in a test user, call the same
endpoints, strip personal data, and replace these files. The tests should
pass unchanged.
