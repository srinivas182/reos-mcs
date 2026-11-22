# Personal data map

Where REOS keeps personal data, why, and how each right under the Texas Data Privacy and Security Act (TDPSA) reaches it. Reviewed 21 November 2026 (A4.3b; calendar rows added in B4.1, to-dos in B4.3, licences in A5.1, purchases in B5.1, ZIP codes for tax in B5.2a, payouts in B5.2b, the ledger and refunds in B5.3, last activity in A6.1); the review date shows on Compliance and audit (screen 71, `reos.privacy.data_map_reviewed`). Add a row, and the field to `App\Services\Privacy\DataExport`, in the same change that adds personal data.

| Data | Where | Why | See ("Download my data") | Delete (account deletion, after 30 days) |
| --- | --- | --- | --- | --- |
| Name, email, phone, language, target state, when last active | `users` | Account and sign-in; "online now" on the platform overview (A6.1, counts only) | Profile | Deleted |
| Roles in organisations | `model_has_roles` | What each person can do | Roles | Deleted (cascade) |
| Linked Google or Apple accounts | `social_accounts` | One-tap sign-in | Sign-in | Deleted (cascade) |
| Single sign-on links | `sso_identities` | Association sign-in | Sign-in | Deleted (cascade) |
| Devices (name, platform, first and last seen) | `known_devices` | New-device alerts, active role | Devices | Deleted (cascade) |
| Waitlists and free quiz answers | `state_waitlist`, `quiz_attempts` | Getting started | Getting started | Deleted (cascade) |
| Connected tools, account label, status | `connections` | Two-way sync | Connections | Disconnected and deleted |
| Calendar events, made in REOS or synced from a connected calendar (title, place, notes, guest emails, times) | `calendar_events` | The REOS calendar, written back to the person's calendar tool (B4.1) | Calendar | Deleted (cascade). Synced events also go when their calendar is disconnected or switched off |
| Losing versions of calendar events changed in two places | `calendar_conflicts` | Undo for 30 days, so nothing is overwritten silently | Calendar | Deleted (cascade); otherwise after 30 days |
| Demo calendar data on Simulator connections | `simulator_records` | Demo connections only (no real tool) | — (demo data) | Deleted with the connection |
| State licences (state, type, number, status, renewal and licence dates, sponsoring broker, the name on the regulator's record, how it was checked) | `licences`, `users.current_state` | Licences in several states (A5.1); checked with the state regulator's public records or approved by a reviewer | Settings › Licenses (web), Me › My licences (phone) | Deleted (cascade) |
| CE completions (course, hours, provider, date) | `ce_completions` | Works out what CE each licence still needs (A5.2) | Settings › Licenses, Me › My licences, dashboard | Deleted (cascade) |
| To-dos (title, notes, due and reminder times, the name of the contact it's about) | `todos` (and on the phone until sent, when captured with no signal) | To-dos typed or spoken (B4.3); a due date is also a calendar event | To-dos | Deleted (cascade) |
| AI credit use (feature, engine, count; never what was said) | `ai_credit_uses` | The monthly AI allowance | AI credit uses | Deleted (cascade) |
| What was said for a voice to-do | Not stored | Speech becomes words on the phone (no audio leaves it); the words are sent to Claude on Amazon Bedrock (no training on it) or read by the built-in parser, then discarded | — | — |
| The phone's address book | Not stored | Searched on the phone for a name said in a to-do; only the chosen contact's id and name are saved with the to-do | — | — |
| Purchases (product, state, ZIP code when the gateway needs it for tax, price, sponsor discount, tax, total, gateway, payment and session ids, receipt link, date) | `orders` | Checkout on reos.com and receipts (B5.1); the ZIP code works out sales tax at PayPal and Authorize.net (B5.2a) | Purchases | Kept for tax records (7 years), linked to no one |
| Plans (product, gateway subscription id, status, renewal date) and what they unlock | `subscriptions`, `entitlements` | Access on the web and in the app (B5.1) | Purchases | Deleted (cascade); cancel the plan first in Plan and billing |
| Sponsor codes used (which code, when); the sponsor sees the person's name on its codes | `sponsor_code_redemptions` | Sponsored seats (B5.1b) | Purchases | Deleted (cascade) |
| The person's customer id at each payment gateway | `gateway_customers` | Receipts and the gateway's card and invoice portal (card details stay with the gateway, never REOS) | Purchases | Deleted (cascade); the gateway keeps its own records under its own policy |
| Instructors' and mentors' Stripe account id (never bank or tax details), revenue shares and what each sale earned them | `connected_accounts`, `product_splits`, `payout_lines` | Payouts (B5.2b); Stripe holds bank and tax details | Payouts | Shares and the account link are deleted (cascade); earnings already paid stay with their orders for tax records, linked to no one |
| AI credits and points: balances and every movement (grants, use, top-ups, expiry, awards, redemptions); never what was asked | `ledger_accounts`, `ledger_entries` (the person's accounts) | The AI allowance and points (B5.3) | Ledger | Entries kept with the ledger (never edited), linked to no one |
| Refunds of their orders (amount, full or partial, the reason REOS gave) | `refunds` | Refunds from REOS (B5.3) | Refunds | Kept for tax records with the order, linked to no one |
| Tool interests and requests | `integration_interests`, `tool_requests` | Catalogue demand | Tool interests | Deleted (cascade) |
| Invitations sent to them | `invitations` (email or phone) | Joining a tenant | — (the tenant's record) | Expire after 14 days |
| Privacy requests | `privacy_requests` | TDPSA duties | Privacy requests | Kept as a record without name, email or details |
| What they did (audit log) | `audit_events` (actor, IP, user agent) | Security and accountability | Activity (last 1,000) | Kept for the tenant's log retention, linked to no one |

Secrets are never exported: password hashes, two-step secrets, recovery codes, API tokens and OAuth tokens.
