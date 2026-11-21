# ADR 0018: Voice to-dos and reminders

- Status: Accepted
- Date: 2026-11-21 (Sprint 4, B4.3; spec in docs/sprints/S04-spec.md)

## Context

Agents think of things to do while driving between showings. Todoist Ramble and Google Assistant capture speech well, but Ramble sends audio to the cloud, and Assistant's reminders live outside the agent's CRM and deals. Sprint 4 decision 3: on-device speech-to-text, Claude on Amazon Bedrock for meaning, and a built-in date parser as backup. The confirm card is the safety net: nothing is saved until the agent sees it.

## Decision

1. **To-dos** (`todos`) belong to a person and carry:
   - a title and notes;
   - a due moment (`due_at`) or a due day (`due_date`), not both;
   - a reminder time;
   - the person they're about: a phone contact today, CRM contacts from Sprint 5;
   - done, and the source (typed or voice).
   The phone names each new to-do (`client_id`, a UUID), so a capture sent twice after being offline is saved once (the second answer is 200 with the same to-do).
2. **Due dates go on the calendar.** A to-do with a due date makes a REOS calendar event of kind `todo`: 15 minutes when timed, all day when dated. Through B4.1 and B4.2 that event reaches Google or Outlook.
   - Changing or removing the due date moves or removes the event, and deleting the to-do deletes it.
   - Edits made to that event in the calendar tool don't change the to-do.
3. **Understanding speech** (`POST /api/v1/voice/parse`): the phone sends the words it heard, the language, its time zone, and when they were said (for queued captures). It gets back items for the confirm card: title, notes, people's names, due time and whether to remind.
   - **Claude on Amazon Bedrock** (Converse API) when "Claude on Amazon Bedrock" runs with test or live keys and a model id.
     - Claude answers through a forced tool with a fixed shape, and REOS checks every field, dropping impossible dates.
     - What was said goes in `<said>` tags, and the instructions tell Claude to treat it as data.
     - Each parse costs one AI credit.
   - **The built-in date parser** (`App\Voice\DateParser`) when AI is off, in the Simulator, out of credits, or Claude fails or answers out of shape. The person always gets a confirm card.
   - What was said is never stored or logged.
4. **One parser, two copies.** The phone needs the parser offline, so a reminder with a clear time is set at once. The TypeScript copy lives in `@reos/voice`, and both copies must pass `packages/voice/src/phrases.json` (English and Spanish). Rules:
   - A bare hour from 1 to 7 means afternoon, and 8 to 11 means morning.
   - A time already past today means tomorrow.
   - A weekday means the next one.
   - "Remind me" with only a date reminds at 9 AM.
5. **AI credits stub** (`AiCredits`, `ai_credit_uses`): each person has a monthly allowance (`reos.ai.monthly_credits`, 500 by default), and each use records the feature, engine and cost only. Pools per seat and top-ups arrive in Sprint 6.
6. **Reminders ring on the phone** (local notifications with Done and Snooze; B4.3b). The server keeps `remind_at` so every device knows; server push comes with B6.2.
7. **On the phone (B4.3b)** the flow is: the Ask bar's mic on Today (or the "Add a to-do" home-screen shortcut, or `reos://voice`) opens a sheet that is already listening. Hold the big mic and let go to finish, or tap it to start and tap again to stop (tapping works with screen readers). Speech is turned into words by the phone (`expo-speech-recognition`, on the device when the phone supports it); no audio leaves the phone. The confirm card shows each to-do with its date and time (moved in steps), the reminder switch, the contact link and notes; nothing is saved until Save.
   - **No signal:** the phone's parser reads the words, the confirmed to-do is kept on the phone (MMKV) and the reminder is scheduled at once. Done from the lock screen is kept too. Both are sent in order when the app is back in front or the phone is back online; the server's `client_id` check means a retry never saves twice. The confirmed card is what's sent: it is not read again by Claude later, because the person already checked it.
   - **Done and Snooze** don't open the app: Done marks the to-do done (or queues it), Snooze rings again in 10 minutes.
   - **Contacts:** permission is asked only when a to-do names someone. One match is linked unless the person says otherwise; several are offered as choices with "Don't link".

## Consequences

- Voice works with no AI keys at all; Claude improves it when switched on.
- Contacts are matched on the phone against its own address book (B4.3b), so no contact list leaves the phone. Sprint 5 adds matching against CRM contacts on the server.
- Amazon Transcribe, the planned fallback for phones without on-device speech, is not used yet. The phone's own recogniser serves every supported device, and the confirm card catches mistakes.
- "Hey Siri, add a to-do in REOS" needs a native App Intents extension, which Expo builds can't add without a custom native target. Until then the home-screen shortcut and the `reos://voice` link (which Siri Shortcuts and Android's Assistant routines can open) do the same job. App Intents are planned with the store builds.
- To-dos due today appear once on the phone's Today, under To-dos (with Done), not again in the calendar list. The web Today card still shows them with the calendar.
