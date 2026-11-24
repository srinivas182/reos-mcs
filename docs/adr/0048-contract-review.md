# ADR 0048: Contract review

- Status: Accepted (A12.2)
- Date: 2026-11-24 (Sprint 12; spec in docs/sprints/S12-spec.md)

## Context

Agents respond to offers and counteroffers on TREC's promulgated forms, mostly the One to Four Family Residential Contract (Resale), TREC 20-18. The expensive mistakes are predictable: a missed option period, earnest money that arrives late, financing without its addendum, numbers that don't add up, special provisions an agent shouldn't have written. SkySlope and Paperless Pipeline audit the file after it's submitted. Agents need the check before they respond, and their broker in the loop.

## Decision

1. **TREC forms first, read with the form's own wording.**
   - `TrecForms` recognizes form 20-18 by its title and its "TREC NO." line, and addenda by a page that starts with their title.
   - It reads the key terms, each with its page: parties, property, the price split, earnest money and option fee with the escrow agent, the day counts (earnest money, option, survey, financing approval), closing date, possession, special provisions and the Effective Date.
   - Anything else gets a general review, said plainly.
2. **Claude when it's on, with REOS's rules always.**
   - With Bedrock keys, Claude reads the pages' text, or the PDF or photo itself for a scan; Bedrock keeps nothing. It returns the same term codes and its own findings.
   - On a known form, REOS's reading wins and Claude fills gaps.
   - Without keys, the Simulator is the rule reader alone, and photos need Claude.
3. **REOS's checks** (`ContractChecks`), each with its page, and with a date and a rule where there is one:
   - **Deadlines** from the Effective Date: earnest money and option fee, the option period (5:00 p.m.), survey, financing approval, and the closing date.
   - **High:**
     - no Effective Date;
     - no earnest money;
     - a sales price that isn't cash plus financing;
     - financing without a financing addendum;
     - closing on or before the option or financing deadline, or before the Effective Date.
   - **Check:**
     - a deadline that falls on a weekend (confirm how it's counted; REOS doesn't move dates);
     - no escrow agent;
     - no option period, or an option period without a fee;
     - special provisions that read like legal drafting (22 TAC §537.11);
     - every watched term that changed since the earlier version.
   - Findings are ranked High first, then by page.
4. **The earlier version.** This is the latest done review of the same deal, or of the same property when there's no deal. Each term shows what it was, and a changed watched term is a finding.
5. **Files stay with the deal.**
   - A new PDF for a deal becomes a deal file. A deal's existing document can be reviewed in place. Other uploads are kept with the agent.
   - The pages' text is stored encrypted for questions.
   - Reviews are private to the agent: row-level security, with personal rows allowed.
6. **Credits.** A review costs `contract_review` (20 by default, set on screen 69). It's charged only when the review is done, so a file REOS can't read costs nothing. A question costs `ai_suggestion` (1).
7. **Background, with a notification.** The review runs as a queued job (`ReviewContract`) through four steps: read pages, pull terms and dates, check common problems, compare with the earlier version. `contract_review` notifies the agent when it's done, naming the file only.
8. **Then:**
   - Deadlines become key dates on the deal (each with a to-do) or, without a deal, to-dos on the calendar. They're added once.
   - Send to broker becomes a virtual broker question (ADR 0047) holding the findings and deadlines, for the broker to confirm or correct.
   - Ask about this contract answers from the best page (a second only if nearly as good), naming the pages, with a note for the client.
9. **The sample contract.** Outside production, a filled-in sample TREC 20-18 with its financing addendum (fictional names) can be reviewed, so the flow can be tried without a real contract.

## Consequences

- The rule reader handles TREC's own wording. Contracts typed differently, or scans, need Claude, which is configured per environment.
- Weekend dates are flagged, not moved, until REOS has the state's holiday calendar and the form's own counting rule as data.
- More forms (condominium, new home, farm and ranch) are added to `TrecForms` as patterns, with tests from their samples.
