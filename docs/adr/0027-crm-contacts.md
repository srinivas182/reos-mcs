# ADR 0027: CRM contacts, one person from all sources

- Status: Accepted
- Date: 2026-11-23 (Sprint 7, B7.1; spec in docs/sprints/S07-spec.md)

## Context

Agents keep the same people in several places: one or two CRMs (Follow Up Boss, kvCORE/BoldTrail) and their phone. Each tool is an island, and two-way syncs between them make duplicates, loops and silent overwrites. B7.2 and B7.3 connect the CRMs; they need one contact model to sync into.

## Decision

1. **One contact per person** (`contacts`), owned by the agent (personal tools) or the brokerage (its CRM). The owner columns follow connections (ADR 0013): `owner_type` + `owner_id`, with a generated `organisation_id` under row-level security (personal rows allowed).
   - Each source's record is a **link** (`contact_links`): the source (`phone` or the integration id), the connection, its id there, its updated-at and the last sync. Ids are unique per owner and source.
   - **Notes** (`contact_notes`), **tasks** linked to REOS to-dos (`contact_tasks`, so done in one is done in the other), **tags** per owner, and **activities** (`contact_activities`).
2. **Matching.** A record joins the contact it is already linked to. Otherwise it joins a contact with the same email or phone, where emails are well-formed and lower-cased and phones are in E.164.
   - A **name alone never merges**: it only adds a suggestion (`contact_suggestions`) that the person can merge or dismiss.
   - "Verified" in the plan means exactly this: a well-formed, normalised address or number, not a typed name.
3. **Conflicts: the newest change wins, field by field** (name, stage, birthday), using each source's updated-at against the time each field last changed (`field_times`).
   - The losing value is written to the activity log in plain English: "Follow Up Boss changed the stage of Maria Lopez to "Active client". The earlier value from Phone contacts was "Lead"." A late, older change is logged as kept back.
   - Emails, phones and tags are added together, never overwritten.
4. **No echoes.** When REOS writes to a source (B7.2), `Contacts::wrote()` fingerprints what it sent on the link. The same values coming back are not a change.
5. **Merge and split.** A merge moves every link, note and task, and records what it added. A split puts the merged contact back with exactly those links, emails, phones and tags. Both are audited (`contact.merged`, `contact.split`).
6. **Sources.**
   - A CRM's people stream saves through `ContactStore`, the connections engine's store. A delete at the source keeps the contact and logs "removed in Follow Up Boss". Disconnecting removes contacts that no other source knows.
   - **Phone contacts are the first source.** They are read on the phone only when the person taps Add (names, emails, phone numbers, birthdays) and sent in batches of 500. They are matched like any other source and **never written back**. Turning them off removes the contacts that only the phone knew.
7. **API** (`/api/v1/contacts`): list, Sync activity with merge suggestions, phone import and removal, merge, split, dismiss.
   - Each contact carries its sources by name. Each activity carries its sentence, so every client says the same thing.
8. **Privacy.** A person's contacts are in "Download my data" and are deleted with the account. A brokerage's contacts go with the brokerage. They are recorded in the data map.

## Consequences

- B7.2 (Follow Up Boss) and B7.3 (kvCORE/BoldTrail) add drivers whose people streams use `ContactStore`, and write back through `Contacts::wrote()`. Notes, tasks and stages ride on the same model.
- The default CRM for new contacts (screen 24) arrives with the first CRM driver.
- Brokerage-owned contacts, and screens for them, arrive with the brokerage CRM connection in B7.2.

## Alternatives considered

- **A unified CRM API vendor (Nango, Merge).** Rejected for the same reason as ADR 0009: a third party would sit between each isolated deployment and its data.
- **Last write wins per record.** Simpler, but one stale field from one tool would overwrite good data in every other. Per-field timestamps keep each tool's newest knowledge.
- **Merging on name plus city.** Too many false merges among common names. People decide, with a suggestion.
