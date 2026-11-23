# ADR 0045: CE wallet and reporting

- Status: Accepted (CE wallet, A11.2)
- Date: 2026-11-24 (Sprint 11; spec in docs/sprints/S11-spec.md)

## Context

Agents have three kinds of CE completion:
- completions from REOS's own courses (A8);
- certificates from other providers, read by OCR (B10.3b);
- commission meetings.

What hours are still missing comes from the state's rules (A5.2). An agent also needs to know three more things:
- whether the hours will count;
- whether TREC has them;
- what renewing actually involves.

TREC publishes its licence holder file every night. For each licence it gives an "MCE status" and an "Education status" (0 none, 1 outstanding, 2 met), plus the expiry date and the sponsoring broker. It does not list individual courses.

## Decision

1. **Completion statuses.** Each completion has one of three statuses, worked out when the wallet is shown rather than stored as a separate state:
   - **REOS-reported:** a REOS course.
   - **Self-reported:** another provider's course, from the moment the agent saves it. It counts toward the renewal straight away.
   - **On TREC's record:** set when `confirmed_at` is set.
2. **Confirmation by period, not by course.** TREC's file has no course list. So when the record shows the requirement met, every completion in the period is confirmed. The requirement is the MCE status, or the education (SAE) status on a first renewal.
3. **Renewals are confirmed from the expiry date.** When TREC's record shows a later expiry than REOS has:
   - the renewal for the old date is confirmed;
   - the licence moves to its new period, so the wallet starts counting toward the next renewal.

   The agent can say "I've renewed with TREC" in the meantime, with Undo.
4. **The checklist lives in the state rules.** `ce.renewal_steps` holds the renewal steps, their wording and links, and is edited on screen 62 like any other rule. Texas has four steps: CE reported, sponsoring broker, renew in REALM, confirmed. Each step is marked done from data REOS has. Renewal fees are paid to TREC; REOS never takes them.
5. **Reminders.** CE reminders go 60, 30 and 7 days before the renewal date while hours are missing, and "Renewal ready" goes once the hours are complete.
   - Each reminder is sent once per licence period (`ce_reminders`).
   - If the job misses days, only the nearest reminder goes.
6. **Buy CE.** Buy CE uses the existing checkout. The wallet offers the cheapest live agent plan in the state that unlocks CE courses (`learn.ce`), or "Take CE in Learn" when the plan is already held. The phone never shows a price: it opens Membership, which follows the store rules (A6).
7. **REOS completions name their course** (`course_id`), so reporting (A11.3) can follow each one to TREC.

## Consequences

- The confirmation runs at 06:15 Chicago time, after TREC's import at 05:30. The reminders run at 09:10.
- A self-reported course that TREC never accepts still shows "self-reported" until the period's requirement is met. REOS can't tell which course TREC rejected, so the wallet says plainly what each status means.
- States other than Texas show the wallet and reminders from their rules, but have no TREC-style confirmation until their regulator's data is connected.

## Alternatives considered

- **Matching completions to TREC's course roster one by one:** TREC doesn't publish one per licensee, and scraping the search pages is ruled out (ADR 0043).
- **A REOS-run renewal:** renewal happens in REALM and fees go to TREC, so REOS guides and confirms rather than acting for the agent.
