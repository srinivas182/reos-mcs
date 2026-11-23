# ADR 0029: kvCORE / BoldTrail, polled two-way

- Status: Accepted
- Date: 2026-11-23 (Sprint 7, B7.3; spec in docs/sprints/S07-spec.md)

## Context

kvCORE (now part of BoldTrail) is the CRM of many Texas brokerages' agents. Its V2 public API takes a per-user API token, which the agent generates in Lead Engine › Lead Dropbox. It offers integrators no webhooks. Agents also lean on its smart campaigns: automated drips that REOS must not disturb.

## Decision

1. **Connect with the agent's token**, through the same flow as Follow Up Boss (ADR 0028, `ConnectsWithApiKey`). The register entry changes from OAuth to `api_key`. The only platform setting is the API address, which defaults to `https://api.kvcore.com/v2/public`.
2. **Polling, not webhooks** (`BoldTrailDriver`; every 15 minutes, plus Sync now).
   - **Contacts** are read newest change first and stop at the last sync's mark, so a quiet account costs one request.
   - **Notes** are read only for contacts that changed since the mark (`/contact/{id}/notes`), 20 contacts per page.
   - **Tasks** are read in pages.
   - Everything is saved through the same contact, note and task stores as Follow Up Boss.
3. **Two-way:**
   - Notes go out with `POST /contact/{id}/action/note`.
   - Task completion goes out with `PUT /task/{id}`.
   - A stage goes out as kvCORE's status number, mapped New, Client, Closed, Sphere, Active, Contract, Archived, Prospect. A stage kvCORE has no status for is not sent.
4. **Smart campaigns are read, never changed.** The campaign and its status are kept on the contact's kvCORE link (`contact_links.details`) and shown on the contact screen. The consent screen says REOS never starts, stops or changes them.
5. **One person, all sources** (ADR 0027): the same person in kvCORE and Follow Up Boss is matched on email or phone and kept once, with both links.
6. **Simulator** on demo data, including a contact on a smart campaign, used until the client's test token arrives.

## Consequences

- The endpoint paths and fields follow kvCORE's public V2 docs. They are confirmed against the test token before Live (register note).
- If kvCORE later offers webhooks, the driver adds `verifyWebhook` and nothing else changes.
- Reading notes per changed contact costs requests on busy accounts. The per-tool allowance (120 a minute) and the five-page job limit keep this fair.
