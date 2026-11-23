# ADR 0039: Ask, the study mate, and instructor questions

- Status: Accepted (the Ask tab, A10.1a)
- Date: 2026-11-23 (Sprint 10; spec in docs/sprints/S10-spec.md)

## Context

Candidates already ask the instructor inside a lesson (Sprint 8 wrap-up), but only about that course. The journeys add Ask as a tab of its own (candidate screen 13): any question about the exam, typed or spoken, with the source. The prospect's feedback set the money rule: candidates are never charged for answers (screens k10 and k11).

## Decision

1. **Where answers come from, in order** (decision 1):
   1. **Course content:** the knowledge base across every live course in the person's state, best passages first. Claude answers only from those passages and cites them; on demo data the best passage is given word for word. Free, logged at 0 credits.
   2. **Beyond the course:** when the content doesn't cover it, a general answer from Claude only when someone pays for it: a candidate's **sponsor** (their school, brokerage or association, from that organisation's credit pool) or an **agent's own credits**. The answer is labelled "Beyond your course" with the sponsor's name.
   3. **Otherwise:** a plain "not covered in your course" with the closest passage, and the instructor (A10.1b) or a session (A10.2). Never a balance, price or purchase.
2. **Conversations** (`ask_threads`, `ask_messages`) are the person's own, across organisations. Follow-ups (Quiz me, Explain simpler, In Spanish) act on the last answer.
3. **On every answer:** the source with **Watch at** (the lesson at that second, or its page), **Save as flashcard** (A9.2d), and **Try a similar one** in the math lab for a real estate math question.
4. **Limits:** the hourly AI limit applies to everyone; the prompt treats passages, questions and earlier answers as data.
5. **Screens:** phone `/ask` (from the Ask bar on Today), web Ask in the candidate sidebar.

## Consequences

- A sponsor's pool now funds two things for its candidates: Ask beyond the course and AI elsewhere; the pool's owner sees the use in its ledger.
- Answer quality depends on the knowledge base; instructor answers added to knowledge (A10.1b) close the gaps.

## Alternatives considered

- **General AI for everyone, paid by REOS:** the cost grows with every candidate and invites legal answers outside the approved content.
- **Charging candidates credits:** ruled out by the prospect.
