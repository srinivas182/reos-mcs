# 0023: Messaging and notifications

**Status:** accepted for messaging (B6.1a, 22 November 2026); attachments, report and block, announcements (B6.1b) and the notification centre (B6.2) extend it.

## Context

Brokerages, schools and mentors talk to their people in Slack, texts and CRM inboxes today, mixing clients, colleagues and vendors with no rules. REOS needs messaging that only connects people who work together, arrives live on the web and the phone, and keeps tenant data separate. People hold roles in several organisations, and a team (its own tenant) sits under a brokerage (another tenant).

## Decision

1. **Relationships decide who can message whom** (`App\Messaging\Relationships`), never a free directory. Within an organisation, its parent and its teams: broker with agents, mentors and other brokers; agents with each other only inside their team; school admins and instructors with their students and each other; mentors with their mentees (agents or students) and school staff; association admins with each other. Providers join when an agent accepts their request (provider app). REOS support works as the tenant's admin only inside an approved support session (A4.1c). A one-to-one also needs the relationship to still hold when sending (an agent who left can't be messaged).
2. **Conversations belong to their participants**, not to a tenant: `conversations` (one-to-one or a group of up to 20, optional name, where it started), `conversation_participants` (read up to which message, when) and `messages` (text up to 4,000 characters, reply to, edited, deleted for everyone). Only participants can open one; anyone else gets a 404. Row-level security isn't used for these tables because a conversation spans tenants (a team agent and the brokerage's broker); the participant check is in one place (`Conversations::participant`) and on the live channel. Tenant-owned messaging data (announcements, B6.1b) keeps row-level security.
3. **Edits and deletes for 5 minutes.** The sender can edit (marked "Edited") or delete for everyone (the words go; "This message was deleted" stays) within 5 minutes. A deleted account leaves its messages as "Former member".
4. **Live through Laravel Reverb.** Each change is broadcast at once (`ShouldBroadcastNow`) on private channels: `users.{id}` (the inbox: `message.posted`) and `conversations.{uuid}` (`message.posted`, `message.changed`, `conversation.read`), authorised for participants with the flag on. The web signs channels with its session (`/broadcasting/auth`, `@laravel/echo-react`), the phone with its token (`/api/v1/broadcasting/auth`, `laravel-echo` with `pusher-js`), reading the public key and address from `/api/v1/app-config`. A message is saved before it is broadcast, and an unreachable Reverb never fails a send (`Realtime::send`); screens catch up on load. Reverb scales horizontally with Redis (`REVERB_SCALING_ENABLED`). When the app is in the background, push carries messages (B6.2).
5. **Behind the `messages` flag** (ADR 0022): REOS staff first, then tenants on screen 70. Off for a person means a 404 on the pages, the API and the channels, and no menu entry.
6. **Screens.** Web: Messages for every role (provider screen 26): conversation list with unread counts, the open conversation, New message with only the people you may message; the menu shows unread. Phone: Messages from Today (unread count), a conversation, New message.

## Consequences

- Who can message whom is code, reviewed with the journeys; a tenant can't open messaging to everyone.
- Participant-scoped tables need their access check kept in the service and the channel; tests cover both.
- Reverb is another process to run and monitor (B6 platform health); the app degrades to refresh-on-open without it.
