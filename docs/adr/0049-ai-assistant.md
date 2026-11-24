# ADR 0049: The agent's AI assistant

- Status: Accepted (A12.3)
- Date: 2026-11-24 (Sprint 12; spec in docs/sprints/S12-spec.md)

## Context

Agents ask the same few kinds of things: what to do now, what to know before an appointment, where a deal stands, how to word a message to a client, and what the brokerage allows. The answers are in their own data (CRM, deals, calendar, plan) and in the brokerage's policies. Rechat's Lucy and Lofty's AI Copilot sit on top of one CRM each. REOS already joins the agent's tools in Today (A11.1b), the morning brief (A11.1c) and the virtual broker (A12.1).

## Decision

1. **One ask bar; the assistant picks the knowledge.** Plain patterns in English and Spanish, checked in this order:
   - **draft:** a drafting verb, then a name ("Text Maria…");
   - **prep:** an appointment or showing;
   - **today:** "what should I do", "my day", "today";
   - **deal:** a deal or closing;
   - **policy:** policy, rules, TREC, commission, earnest money, advertising;
   - **general:** anything else.

   Patterns keep it predictable and cheap. Claude writes text only where text is needed.
2. **The agent's own data, with permission.**
   - Today, prep, deal and draft read the agent's synced contacts, deals and calendar only once the agent has allowed it: one switch, `agent_profiles.assistant_context_at`, audited with from and to.
   - Until then the answer says what it needs and offers Allow; allowing re-asks the last question.
3. **What each answer is made of:**
   - **Today:** Today's ranked list (overdue, deadlines, appointments, follow-ups, plan tasks), each item with its source and a link to its deal, plus the morning brief's first call. This is the done-when: "What should I do today?" answers from real CRM data.
   - **Prep:** the next appointment, with the linked contact's CRM summary, latest notes, last activity and open deal.
   - **Deal:** the named deal (or the next one with a date), with its stage and open key dates.
   - **Draft:** an email or text to the named contact, by Claude from the CRM summary and notes or else from a template, as a mailto: or sms: link. REOS never sends it.
   - **Policy:** handed to the virtual broker (ADR 0047), which cites its sources and escalates money and legal questions. The answer links to that question.
   - **General:** Claude for `general_answer` credits; without keys, a plain list of what the assistant can do.
4. **Costs.** Answers from the agent's data are free. A Claude draft costs `ai_suggestion`, and a general answer costs `general_answer`. All count toward the hourly limit.
5. **Chats** (`assistant_chats`, `assistant_messages`) belong to the agent. An answer keeps its items, draft and virtual broker link, so a chat reopens as it was.
6. **Ask home** (agent screen 59) replaces the candidates' study mate for agents. It has six starters:
   - virtual broker;
   - review a contract;
   - prepare a contract: marked "Coming soon", and built with contract prep;
   - write an ad: opens the studio;
   - reply to an email: starts a draft;
   - prep an appointment.

   Recent chats and the ask bar, typed or spoken, are below the starters.

## Consequences

- The web has no assistant page: the Web App Journey gives agents the dashboard, CRM hub and virtual broker instead. The API serves any surface.
- Pattern routing can misread an unusual question. The general answer explains what the assistant can do, and every data answer shows its sources.
- Inbox triage (agent app screens 7 and 8) needs Gmail and Outlook mail scopes, and comes with the email integration.
