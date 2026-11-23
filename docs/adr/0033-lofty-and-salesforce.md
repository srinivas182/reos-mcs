# ADR 0033: Lofty and Salesforce

- Status: Accepted (Lofty, B8.1; Salesforce follows in B8.2)
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

## Consequences

- A sync reads every lead page even when little changed. That is fine for an agent's book (hundreds to a few thousand leads, 100 per request). If the test account shows a sort on last update, or cursor paging (`scrollId`) that stops early, the driver can switch without changing anything else.
- To confirm against a test account before Live: webhook availability, the subscription id field, and rate limits. They are noted in the register.

## Alternatives considered

- **Lofty's OAuth partner app:** would need partner approval and gives nothing an agent's own key doesn't. Kept for later if Lofty asks for it.
- **Trusting webhook bodies to update records:** they're unsigned. Rejected; they only say when to look.
