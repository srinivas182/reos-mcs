# ADR 0028: Follow Up Boss, two-way, and tools connected with an API key

- Status: Accepted
- Date: 2026-11-23 (Sprint 7, B7.2; spec in docs/sprints/S07-spec.md)

## Context

Follow Up Boss is the CRM most Texas teams use. It has no OAuth for third parties: each agent creates an API key, and an integrator registers a system name and key that go with every request. Sprint 7 needs people, notes, tasks and stages in both directions, live when Follow Up Boss says something changed.

## Decision

1. **Connect with a key** (`ConnectsWithApiKey`).
   - The person pastes their Follow Up Boss API key on the consent screen. The catalogue says `connects_with: api_key`, so no tool list is hard-coded in the app.
   - REOS checks the key with the tool (`GET /me`), then saves the connection exactly as a sign-in would (`ConnectionAuthorizer::connectWithKey`). The key is encrypted like a token, never returned, and has no refresh.
   - A refused key gets "check your key". Disconnecting forgets the key and everything synced.
2. **Driver** (`FollowUpBossDriver`, plus a `FollowUpBossSimulator` on demo data).
   - Every request sends REOS's `X-System` and `X-System-Key` from Settings › Integrations.
   - **Streams:** `people` (into contacts, ADR 0027), `notes` and `tasks`. Each is read newest change first from where the last sync stopped, paged with Follow Up Boss's `next`.
   - **Errors:** 401 means reconnect; 429 is honoured.
3. **Two-way** (`CrmDriver`, `CrmWriteBack`, queued `PushToCrm` with retries).
   - **Notes:** a note written in REOS is saved at once and sent to every connected CRM that knows the person. Its id there is kept (`contact_notes.pushed`), so it is never read back as a second note.
   - **Tasks:** a Follow Up Boss task is a REOS to-do about the contact (source `crm`). Done in REOS marks it done in Follow Up Boss, and the reverse.
   - **Stages:** a stage changed in REOS is sent to the CRM. The same value coming back is no change.
   - **Deletes:** a person deleted in Follow Up Boss keeps their REOS contact, with "removed in Follow Up Boss" in Sync activity.
4. **Webhooks.**
   - REOS subscribes per event when it has a public HTTPS address (`ChangeChannels`). The address carries the connection id signed with REOS's key (`?c=12.…`), because Follow Up Boss calls one address for every account.
   - A delivery must carry `FUB-Signature`, the HMAC-SHA256 of the base64 body with the system key. Repeats are refused by `webhook_receipts`.
   - A delivery only queues a sync of that connection. The 15-minute schedule is the backstop.
5. **A note or task can arrive before its person.** A placeholder contact holds it, and joins the matching contact when the person syncs.
6. **Phone screens:**
   - The consent screen takes the API key.
   - **One person, all sources (screen 14)** at `contacts/[id]` shows the sources, the notes from REOS and the CRMs, and Add a note. Sync activity rows open it.

## Consequences

- kvCORE/BoldTrail (B7.3) uses the same key flow, `CrmDriver` and stores, with polling instead of webhooks.
- The default CRM for new contacts (screen 24) is needed once REOS creates contacts itself (sharing a contact from Messages or a showing). It is still to come.
- The request and webhook field names follow Follow Up Boss's public docs. They are confirmed against the trial account before Live (register note).

## Alternatives considered

- **Zapier between REOS and Follow Up Boss.** It brings loops and silent failures, the very problem in section 2 of the spec, and puts a third party between the deployment and its data.
- **One webhook address per connection.** Follow Up Boss registers webhooks per system, not per account, so the signed `c` parameter is the cheaper equivalent.
