# Personal data map

Where REOS keeps personal data, why, and how each right under the Texas Data Privacy and Security Act (TDPSA) reaches it. Reviewed 21 November 2026 (A4.3b); the review date shows on Compliance and audit (screen 71, `reos.privacy.data_map_reviewed`). Add a row, and the field to `App\Services\Privacy\DataExport`, in the same change that adds personal data.

| Data | Where | Why | See ("Download my data") | Delete (account deletion, after 30 days) |
| --- | --- | --- | --- | --- |
| Name, email, phone, language, target state | `users` | Account and sign-in | Profile | Deleted |
| Roles in organisations | `model_has_roles` | What each person can do | Roles | Deleted (cascade) |
| Linked Google or Apple accounts | `social_accounts` | One-tap sign-in | Sign-in | Deleted (cascade) |
| Single sign-on links | `sso_identities` | Association sign-in | Sign-in | Deleted (cascade) |
| Devices (name, platform, first and last seen) | `known_devices` | New-device alerts, active role | Devices | Deleted (cascade) |
| Waitlists and free quiz answers | `state_waitlist`, `quiz_attempts` | Getting started | Getting started | Deleted (cascade) |
| Connected tools, account label, status | `connections` | Two-way sync | Connections | Disconnected and deleted |
| Synced calendar events (title, place, times) | `calendar_events` | Calendar sync | Connections | Deleted with the connection |
| Tool interests and requests | `integration_interests`, `tool_requests` | Catalogue demand | Tool interests and requests | Deleted (cascade) |
| Invitations sent to them | `invitations` (email or phone) | Joining a tenant | — (the tenant's record) | Expire after 14 days |
| Privacy requests | `privacy_requests` | TDPSA duties | Privacy requests | Kept as a record without name, email or details |
| What they did (audit log) | `audit_events` (actor, IP, user agent) | Security and accountability | Activity (last 1,000) | Kept for the tenant's log retention, linked to no one |

Secrets are never exported: password hashes, two-step secrets, recovery codes, API tokens and OAuth tokens.
