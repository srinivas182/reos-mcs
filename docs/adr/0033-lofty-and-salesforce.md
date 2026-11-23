# ADR 0033: Lofty and Salesforce

- Status: Accepted (Lofty, B8.1; Salesforce, B8.2)
- Date: 2026-11-23 (Sprint 8; spec in docs/sprints/S08-spec.md)

## Context

Agents on Lofty need the same two-way contacts, notes and tasks that Follow Up Boss (ADR 0028) and kvCORE (ADR 0029) give, without changing their habits. Lofty's Open API is documented at developer.lofty.com. Personal API keys are made in Lofty under Settings › Integrations › API and sent as `Authorization: token …`.

## Decision: Lofty

1. **Connect** with the agent's own API key through the key flow from B7.2 (`ConnectsWithApiKey`). The key is encrypted with the connection. The consent screen tells the person where to find the key (`key_hint` on the catalogue item, per tool, from the translations). REOS holds no Lofty credentials of its own, so the register entry has no fields.
2. **Reading.** Lofty's lead list (`GET /v1.0/leads`, `limit` and `offset`) has no changed-since filter. A sync therefore pages through the leads and keeps those whose `lastUpdateTime` is after the last sync's mark; the next mark is the newest time seen.
   - Notes (`GET /v1.0/notes?leadId=`) and tasks (`GET /v1.0/tasks?leadId=`) are read for the changed leads, 20 leads to a page.
   - Times come as `yyyy-MM-dd HH:mm:ss` in UTC (epoch milliseconds in webhooks) and are kept as ISO 8601.
3. **Writing.**
   - A note goes to `POST /v1.0/notes` (`leadId`, `content` cut to Lofty's 2,000 characters, `isPin` false).
   - A task is marked done or reopened with `POST /v2.0/tasks/{id}/finish` or `/unfinish`.
   - Stage and tags go to `PUT /v1.0/leads/{id}`. The stage is cut to Lofty's 19 characters, and `tags` replaces the list.
   - Every request sends `X-Error-Status: true`, so failures come back as real HTTP errors rather than 200 with an error body.
4. **Webhooks.** REOS subscribes with `POST /v1.0/webhook` to lists 2 (lead info), 9 (notes) and 10 (tasks), with an HTTPS callback.
   - Lofty doesn't document signed deliveries. Each connection therefore gets its own signed address (`?c=<id>.<hmac>`, signed for Lofty alone), and `Lofty-Webhook-Id` de-duplicates deliveries.
   - Nothing in a delivery is trusted except the ids of deleted leads, which the next people sync removes. A delivery only queues a sync, and the 15-minute schedule is the backstop.
5. **Simulator** on demo data, with Lofty's own field names. Matching to people from other tools works as for every CRM (ADR 0027).

## Decision: Salesforce

1. **Brokerage-owned.** The brokerage connects its org once, from Brokerage integrations (screen 40), with OAuth 2 (web server flow with PKCE, scope `api refresh_token`) through REOS's Connected App (client id, secret and login address in Settings › Integrations). The connection belongs to the organisation, not a person.
   - Salesforce answers each token request with the org's own `instance_url`; REOS keeps it with the session (`"<instance_url> <token>"`, encrypted) and assumes a 100-minute session, since the token response has no expiry.
2. **Each record goes to the agent who owns it.** `Owner.Email` is matched, ignoring case, to a member of the brokerage. That agent's REOS contact is linked to the record by connection (`ContactLink.connection_id`), so the brokerage's one connection feeds many agents' books.
   - A record whose owner has no REOS account is skipped; the owners are listed on screen 40 so the broker can invite them.
   - **The broker sees counts and sync health only, never the agents' contacts** (journey rule). Nothing on screen 40 carries a name or email of a person in the CRM.
3. **Reading** by SOQL on `SystemModstamp` every 15 minutes, per object (Contact, Lead, Task, ContentNote), paged with `nextRecordsUrl`. Deleted records come from `/sobjects/{object}/deleted/`. Field names in SOQL come only from the register's mapping and must match `^[A-Za-z][A-Za-z0-9_]{0,39}$`.
4. **Writing.** A note is a ContentNote (base64 body) linked to the person with a ContentDocumentLink; a task's done state is its `Status`; a stage is written only to the field the brokerage mapped (none for Contacts by default, `Status` for Leads).
5. **Mapping on screen 40.** The brokerage picks the stage fields from the org's describe (updatable picklist or text fields, cached ten minutes), with a one-click "usual setup" (`Stage__c` on Contact, `Status` on Lead), and whether Leads sync. Saving checks the fields exist, is audited and starts a sync.
6. **Hiding tools.** On the same screen a brokerage may hide any tool REOS offers its agents, except those everyone needs (such as calendars), and see how many of its agents connected each. It can't turn on a tool REOS hasn't released.

## Consequences

- Salesforce: Change Data Capture (instant changes) needs a paid edition and stays for later; the 15-minute poll is the only path now. Matching by owner email means an agent must use the same email in REOS and Salesforce, which the unmatched list makes visible.
- A sync reads every lead page even when little changed. That is fine for an agent's book (hundreds to a few thousand leads, 100 per request). If the test account shows a sort on last update, or cursor paging (`scrollId`) that stops early, the driver can switch without changing anything else.
- To confirm against a test account before Live: webhook availability, the subscription id field, and rate limits. They are noted in the register.

## Alternatives considered

- **Salesforce per agent (each agent connects):** most agents in a Salesforce brokerage have no API access of their own, and the broker would end up with many connections to the same org. Rejected.
- **Giving the brokerage a copy of every contact:** breaks the journey rule that brokers don't see agents' contacts. Rejected.
- **Lofty's OAuth partner app:** would need partner approval and gives nothing an agent's own key doesn't. Kept for later if Lofty asks for it.
- **Trusting webhook bodies to update records:** they're unsigned. Rejected; they only say when to look.
