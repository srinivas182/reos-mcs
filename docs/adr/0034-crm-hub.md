# ADR 0034: The CRM hub, AI next step and tasks to the CRM

- Status: Accepted (B8.3a, B8.3b)
- Date: 2026-11-23 (Sprint 8; spec in docs/sprints/S08-spec.md)

## Context

Agents have people in Follow Up Boss, kvCORE (BoldTrail), Lofty, a brokerage's Salesforce and their phone. ADR 0027 already merges them into one contact each. Sprint 8 makes that the place they work: web screen 13 (Contacts, the CRM hub), a contact list on the phone, and Today's "do this now" (Agent App Journey screen 6). The AI summary is free for agents (Sprint 8 decision 2).

## Decision

1. **One list, the agent's own.**
   - `ContactHub` lists the agent's contacts (owner = the agent), newest change first, up to 100 a page. It can filter by tab, by one of the agent's tags, or by a search on name, email or phone digits.
   - Each row shows the last touch: the newest note or finished task from any source.
   - Brokers and other agents get 404. A brokerage's Salesforce people reach agents only as each agent's own contacts (ADR 0033).
2. **Tabs from stage words.** Every CRM names stages its own way, so `Segments` reads the words in the stage. The first match wins, in this order:
   - past (past, closed, sold);
   - sphere (sphere, friend, family);
   - new (new, lead, not contacted, open, or no stage);
   - active (active, client, buyer, seller, prospect, nurture, pending, contract, and so on).

   A stage with none of these words appears only under All. The same rule runs in PHP for labels and counts, and in SQL (bound `LIKE`s) for filtering. A test checks that the two agree.
3. **AI summary and next step.** `ContactInsights` sends Claude on Bedrock a brief of up to 20 notes, 10 tasks and 10 sync changes from every source.
   - **Prompt safety.** The contact's data goes inside `<contact>` tags, and the system prompt says to treat it only as data. The system prompt also sets Fair Housing limits and forbids promises.
   - **Output.** A forced tool call returns the summary, one next step, why, a channel (call, text, email or note) and an optional text in the agent's voice. Output is cut to length, and an unknown channel becomes `note`.
   - **When Bedrock has no keys,** or Claude fails, a rules-based simulator writes the same shape from the same brief. The simulator leads with an overdue task, otherwise days since the last touch and the last thing the person said.
   - **Caching.** The insight is kept in `contact_insights` with a fingerprint of everything that should change it: stage, `updated_at`, note and activity ids, task changes and the agent's language. A stale insight is never shown; the list shows a fresh one's next step, or "Ask AI".
   - **Cost and limits.** Each one written is logged in `ai_credit_uses` at 0 credits (`AiCredits::logFree`) under the hourly AI limit, which returns 429 when reached.
4. **Do this now.** Today picks one contact:
   - first, the agent's most overdue task about a contact;
   - otherwise, the active contact touched longest ago, or the newest new lead.

   It writes at most one insight per call.
5. **Call and text from the agent's own phone.** REOS never calls or texts.
   - The web shows a QR code (`tel:` or `sms:` with the editable draft) and an "open on this device" link. The phone opens its own app.
   - "I called" or "I sent it" is saved as a note, so it reaches every CRM that knows the person like any note.
6. **Tasks go to one CRM.** A task added on a contact becomes a REOS to-do at once.
   - It goes to the CRM that knows the person and synced most recently. Only CRMs whose driver can create tasks count (`CreatesTasks`):
     - Follow Up Boss: `POST /v1/tasks`;
     - Lofty: `POST /v1.0/tasks`, with the deadline in epoch milliseconds, the end of the day when none is given;
     - Salesforce: a Task on `WhoId`, owned by the person's owner, so the B8.2 owner rule brings it back to the same agent.
   - The `contact_tasks` row is written first with no id; the queued push fills the id in. The next sync then reads the task back as the same to-do. A to-do finished before the push is marked done in the CRM too.
   - BoldTrail's public API doesn't document creating tasks, so for BoldTrail-only people the task stays in REOS (`sent_to` null).

7. **Where new contacts go (B8.3b, Connections and Sync screen 24).**
   - The default CRM is a flag on one of the agent's own CRM connections (`settings.default_for_new`). With exactly one CRM that can create people (`CreatesPeople`), that CRM is the default.
   - "Add contact" saves the person in REOS at once, as source `reos`, matched on email or phone like any source. It then creates them in the default CRM unless that CRM already knows them:
     - Follow Up Boss: `POST /v1/people?deduplicate=true`. These are people the agent adds, not portal leads, which arrive as Follow Up Boss events.
     - Lofty: `POST /v1.0/leads`, with names cut to 30 characters, phones to 20 and the stage to 19.
   - The new link is fingerprinted, so the next sync reads it as the same person.
   - A new task goes to the default CRM when that CRM knows the person.
8. **CSV import, the last resort (B8.3b).**
   - The agent uploads up to 5,000 rows and 5 MB, UTF-8, with commas or semicolons. The file stays on the private disk until it's imported.
   - REOS guesses the mapping from the headers (English and Spanish); the agent can change it.
   - The preview shows who is new, who joins someone already there (same email or phone, including two rows for the same person), and which rows are skipped.
   - The import runs in a job. The rows become source `csv-{id}`, named "CSV import, date".
   - Undo forgets that source: people only the file had are removed, and everyone else just loses the CSV link. The file is deleted once imported.

## Consequences

- BoldTrail can't take new people or tasks through its documented API, so it's never offered as the default; it still syncs both ways.
- One task in one CRM keeps "done in one is done in the other" simple (`contact_tasks` is unique per to-do). An agent whose person is in two task-taking CRMs sees the task in the most recently synced one. The default CRM (B8.3b) takes precedence when it knows the person.
- Fingerprints are computed per page with three grouped queries, so the list stays at a fixed number of queries.
- Stage words are English. A CRM that uses other words shows those people under All until the list grows; the words live in one constant.

## Alternatives considered

- **Generating insights for the whole list:** this costs AI time for people the agent may not open. Rejected: insights are written on request, and one a call for Today.
- **Writing a task to every CRM that knows the person:** this would need several external ids per to-do, and "done" would have to fan out and reconcile. Rejected.
- **REOS sending texts (for example through Twilio):** the journey says texts go from the agent's own phone; carrier registration (10DLC) and consent would also apply. Rejected.
