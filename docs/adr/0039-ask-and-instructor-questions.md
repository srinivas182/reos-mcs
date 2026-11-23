# ADR 0039: Ask, the study mate, and instructor questions

- Status: Accepted (the Ask tab, A10.1a; credits beyond the course and instructor questions, A10.1b)
- Date: 2026-11-23 (Sprint 10; spec in docs/sprints/S10-spec.md)

## Context

Candidates already ask the instructor inside a lesson (Sprint 8 wrap-up), but only about that course. The journeys add Ask as a tab of its own (candidate screen 13): any question about the exam, typed or spoken, with the source. Answers from the course are always free; the client set the rule for answers beyond it on 23 November 2026: a free monthly allowance, then the sponsor, then the candidate's own credits.

## Decision

1. **Where answers come from, in order** (decision 1):
   1. **Course content:** the knowledge base across every live course in the person's state, best passages first. Claude answers only from those passages and cites them; on demo data the best passage is given word for word. Free, logged at 0 credits.
   2. **Beyond the course:** when the content doesn't cover it, a general answer from Claude (costing the `general_answer` credits, 2 by default), paid in this order (the client's decision of 23 November 2026): the candidate's **free monthly credits** (1,000 by default, `candidate_monthly_credits` on screen 69), then their **sponsor's pool** (their school, brokerage or association), then **credits they bought** on reos.com. Agents use their own credits. The answer is labelled "Beyond your course", with the sponsor's name when the sponsor paid.
   3. **Otherwise:** a plain "not covered in your course" with the closest passage, and the instructor (A10.1b) or a session (A10.2).
2. **Conversations** (`ask_threads`, `ask_messages`) are the person's own, across organisations. Follow-ups (Quiz me, Explain simpler, In Spanish) act on the last answer.
3. **On every answer:** the source with **Watch at** (the lesson at that second, or its page), **Save as flashcard** (A9.2d), and **Try a similar one** in the math lab for a real estate math question.
4. **Limits:** the hourly AI limit applies to everyone; the prompt treats passages, questions and earlier answers as data.
5. **Screens:** phone `/ask` (from the Ask bar on Today), web Ask in the candidate sidebar. Both show the free credits left this month, who sponsors more, and credits bought (screen k10).
6. **Instructor questions** (A10.1b, screens i09 and i10):
   - **Ask my instructor** sends the conversation's last question, with what the content said, to the instructors of the course the answer came from (or the course the student studied last). One waiting question per conversation; instructors are notified.
   - The queue is scoped to the courses an instructor teaches (`course_instructors`); students and schools are in different tenants, so access is checked in the app and the table has no `organisation_id`.
   - **Answer:** the student is notified and the answer joins their conversation as "Your instructor, Nelle Hart". **Add to course knowledge** (on by default) files the question and answer as a passage of the course (kind `instructor`, with the lesson the content answer came from), searchable at once and embedded for meaning search; re-indexing the lesson keeps it. **Make a flashcard** adds it to the student's deck.
   - The queue also shows how many questions Ask answered from the instructor's courses in the last 30 days.

## Consequences

- A sponsor's pool now funds two things for its candidates: Ask beyond the course and AI elsewhere; the pool's owner sees the use in its ledger.
- Answer quality depends on the knowledge base; instructor answers added to knowledge (A10.1b) close the gaps.

## Alternatives considered

- **General AI for everyone, paid by REOS:** the cost grows with every candidate and invites legal answers outside the approved content.
- **Never charging candidates (sponsor only):** the client preferred a free monthly allowance so every candidate can ask beyond the course.
