# ADR 0087: QuickBooks Online journal entries for split sheets and payouts

- Status: Accepted (B17.3)
- Date: 2026-11-28 (Sprint 17; spec in docs/sprints/S17-spec.md)

## Context

Brokerages keep their books in QuickBooks Online. Team split sheets are now signed in REOS (A17.3), and REOS pays mentors and instructors through Stripe Connect (B5.2b, A17.1). Without an export, someone types every split into QuickBooks by hand. The register already had `quickbooks-online`: OAuth 2.0, tenant accounts, and Intuit's app assessment needed before production keys.

## Decision

1. **One company per brokerage** (`accounting_connections`, RLS on the brokerage):
   - connected by OAuth 2.0 with the `com.intuit.quickbooks.accounting` scope;
   - tokens are encrypted, and the hour-long access token is refreshed as needed;
   - the sign-in `state` is tied to the brokerage and the broker for 10 minutes.
2. **Map the accounts once**, from the company's own chart of accounts:
   - commission received (clearing);
   - brokerage share (income);
   - team share (payable);
   - agent share (payable);
   - payouts (expense);
   - payouts paid (clearing).
3. **A signed split sheet is a journal entry:**
   - debit the clearing account with the gross;
   - credit the brokerage, team and agent shares to their accounts;
   - the entry must balance or it isn't sent;
   - it posts when the agent signs. When the brokerage isn't connected and mapped yet, it waits for Export now or the daily `reos:accounting:export`.
   The sheet keeps `exported_at` and the QuickBooks id (`export_ref`).
4. **Payouts** for sales the brokerage made (mentor sessions) are a journal entry each: debit payouts expense, credit payouts clearing.
5. **Once each:** `accounting_exports` is unique per brokerage, kind and subject. A failure keeps its reason, shows on the page, and is tried again. Signing never fails because of QuickBooks.
6. **Modes** follow the register and the integrations hub:
   - the **simulator** connects at once and returns predictable entry ids, so the whole flow runs before Intuit approves the app;
   - **sandbox** and **live** use `IntuitQuickBooks`, with the keys from the credential vault.
7. **Who:** the brokerage's broker, with the admin two-step check on the web. Every step is audited on the brokerage:
   - `accounting.connect_started`, `accounting.connected`;
   - `accounting.accounts_mapped` (before and after);
   - `accounting.exported`, `accounting.disconnected`.

## Alternatives considered

- **Invoices or bills per agent.** Splits are internal allocations of commission already received; a journal entry records them without creating vendors for every agent.
- **A CSV for the bookkeeper.** Still typed in by hand, and nothing stops posting twice.
- **One entry per day.** Harder to trace a sheet to its entry; one entry per sheet keeps the REOS id in the memo and the document number.

## Consequences

- Production needs Intuit's app assessment (register: `access.route: app_review`, 3 weeks). Until then, brokerages see "Simulated".
- Disbursing commission to agents stays with the brokerage's bank and accounting; REOS records the split, it doesn't pay it.
