# ADR 0047: Virtual broker

- Status: Accepted (A12.1)
- Date: 2026-11-24 (Sprint 12; spec in docs/sprints/S12-spec.md)

## Context

Agents ask their broker the same policy questions again and again: advertising, splits, earnest money, showings. A Texas broker is responsible for the agents they sponsor (22 TAC §535.2) and keeps written policies, but can't answer every question at once. A chatbot that answers from general knowledge would be wrong about the brokerage's own rules, and dangerous about money and legal risk.

Rechat's Lucy and similar assistants answer from CRM and marketing data, but they don't know the brokerage's manual and they don't bring the broker in.

## Decision

1. **Three sources, all cited.**
   - **The brokerage's policy manual.** A PDF, Word or text file, split into passages of a few paragraphs, each with its page and heading. Each upload is a new version; older versions are kept (file and record) but no longer used for answers.
   - **The brokerage FAQ.** Built from the broker's answers when they switch on "Add to policy knowledge". Entries can be removed, with Undo.
   - **The Texas rules library.** Text state rules (A5.2) used by `virtual_broker`: advertising (§535.155), trust money (§535.146), compensation (§535.3), supervision (§535.2), IABS (§531.20) and promulgated forms (§537.11). For these new rules, `effective_from` is the day REOS checked the text against the source; the rule's own history is on the source page.
   - Every answer lists its sources: manual page, FAQ, or rule citation with its link and date.
2. **Search is explainable.**
   - A passage's score is the share of the question's meaningful words it holds (a stop list for English and Spanish, light stemming).
   - It needs at least two shared words, and at least 30% of the question.
   - FAQ entries rank a little above the manual for the same words.
   - This works the same on PostgreSQL and SQLite, and the broker can see why an answer came up. Meaning search with embeddings (ADR 0020) can be added behind `PolicySearch` without changing callers.
3. **Answers.**
   - With Bedrock keys, Claude answers only from the numbered sources, cites them, rates its own confidence and can say "needs broker". An answer that cites nothing is low confidence.
   - Without keys, the Simulator quotes the best one or two sources, with confidence taken from the score: high at 0.6 or more, medium at 0.34 or more, otherwise low.
   - Answers are free (`knowledge_answer` costs 0): they come from the brokerage's own knowledge. They count toward the hourly abuse limit.
4. **Escalation.** A question goes to the broker:
   - always when it's about **legal** risk (lawsuits, attorneys, discrimination, terminations…);
   - always when it's about **money** (earnest money, commissions, splits, refunds, fees…);
   - when the answer is **unclear** (low confidence, no sources, or Claude says "needs broker");
   - when the agent taps **Send to my broker**.

   The agent sees "With your broker", why, and the time the brokerage aims to reply by. The broker is notified; the question's text stays in REOS.
5. **The broker's reply.**
   - The broker sees the question, why it came, its age and the AI draft with sources.
   - Sending the draft unchanged marks the answer **Confirmed**; editing it marks it **Corrected**. Either way it's labelled "From your broker".
   - Drafts are never sent without the broker's tap.
   - Every reply is audited, and the agent is notified.
6. **TREC wins.**
   - On upload, each sentence of the manual is checked against patterns for the rules above: trust money later than the second working day, agents paid directly, the broker name optional in ads, agents drafting their own clauses.
   - Each match is listed as a conflict with the sentence, the page and the rule.
   - When the passage an answer would quote is a conflicting one, the rule goes first and the answer says TREC's rule wins.
7. **Who answers and how fast.** Per brokerage (`organisations.virtual_broker`): a named broker or every broker, and a reply time in hours (default 4). Changes are audited with before and after.
8. **Isolation.** `policy_manuals`, `policy_passages` and `broker_questions` are protected by row-level security. Agents see only their own questions in their own brokerage; brokers only their brokerage's.

## Consequences

- A brokerage with no manual still gets Texas rule answers, and everything else goes to the broker.
- Pattern-based conflict checks catch the common contradictions, not every one. Brokers still review their manual, and the list grows with new rules.
- Phone screens for the broker's side (broker journey screens 11 and 12) arrive with the broker phone app (Sprint 15). Until then brokers answer on the web, and notifications link there.
