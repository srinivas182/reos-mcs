# ADR 0031: Lesson review and question bank

- Status: Accepted
- Date: 2026-11-23 (Sprint 8, A8.1; spec in docs/sprints/S08-spec.md)

## Context

AI lessons (ADR 0026) arrive as versions with a script in English and Spanish, legal flags, two videos, and drafted check-ins, questions and flashcards. Nothing may reach learners until a person who builds the course has approved it, line by line. TREC can hold the school to every word, and questions feed practice and exams.

## Decision

1. **Line-by-line review on the version** (`lesson_versions.reviews`, read through `LessonVersion::review()`).
   - Each line is *to review*, *approved* or *edited*, with an optional suggestion and comments.
   - An edited line is checked again in both languages and marked to be recorded again.
   - The version carries the review state (`in_review`, `sent_back`, `approved`), the note, and who reviewed it and when.
2. **Who reviews:** the school's admins and the instructors of the course (the course builder's `canEdit`). Anyone else gets a 404.
3. **Flags block.**
   - A line with an open legal flag can't be approved.
   - The flag shows its rule and legal source (`LegalRule::source()`, 22 TAC §535.155 or §535.65).
   - **Suggest a rewrite:** Claude rewrites the line from its source passage and the broken rule, charged to the school as `ai_suggestion` (screen 69). Without Claude, the rule's plain replacement words (`LegalRule::replacement()`) take the flagged words' place.
   - A suggestion is only offered; **Use suggestion** edits the line like any edit.
4. **Re-record.** Lines marked to record again are rendered with **Record again**. That creates a new version (`LessonPipeline::rerender`) with the reviewed script and its review carried over, straight to the vendor without a new draft, and charged to the school. Earlier versions are kept.
5. **Approve and publish** needs:
   - videos ready;
   - every line approved;
   - no open flag;
   - nothing waiting to be recorded again;
   - every check-in and flashcard approved or rejected.

   The lesson then becomes `approved` (learners see it) and is indexed for search. **Send back** returns it to draft with a note, and its author is notified (`content_review`).
6. **Question bank** (`QuestionBank`, screen 45).
   - AI questions are reviewed with tabs: To review, Approved, Rejected and Disputed.
   - **Approving** needs a source, a topic and an exam portion (national or Texas), which together are the outline mapping. It copies the question into the school's bank (`quiz_questions` with the school, course and review item). Editing keeps the copy in step; rejecting retires it.
   - `quiz_questions` gains an owner. Like courses, it is exempt from row-level security, because a school's learners practise across tenants; edits are checked against the owner school here.
7. **Disputes** (`question_disputes`).
   - Any learner can report a question, with one open report per person and question (`POST /api/v1/questions/{id}/report`).
   - The owner keeps it or corrects it with a note. Every open report on it closes, and the learners are notified.
8. **Audit:** every review action is recorded, including approve, edit, suggestion, use, comment, re-record, item decisions, publish, send back and question decisions.

## Consequences

- The course player (A8.2) shows approved check-ins and flashcards. Practice (Sprint 9) draws from the school's approved questions as well as REOS's bank.
- The state-rule citation per line from screen 62 is shown as the legal source of a flag. Mapping every line to a rule needs the rules engine's topic tags and is left for later.
- Disputes on REOS's own questions have no reviewer screen yet. They are stored and will appear on screen 63 with the REOS bank.
