# 0026: Course builder and AI lessons

**Status:** course builder accepted (A7.2), 23 November 2026. The AI lesson pipeline (A7.3) follows.

## Context

Schools want to turn what they already teach (scripts, slide decks, forms, recorded classes) into online lessons, and later into AI avatar lessons, without hand-building every page. Two rules frame this:
- Schools own the courses they build (decision of 23 November 2026).
- The journey says "nothing published without approval": an instructor checks every lesson before learners see it.

The A5.3 content library already handles uploads safely (signed upload links, size and first-byte checks, virus scan) and reads slides, documents and captions.

## Decision

1. **Who builds.** School admins build any of their school's courses. Instructors build only the courses they teach (`course_instructors`, set on screen 52); anyone else gets a 404. An instructor who creates a course teaches it. Courses a school creates belong to it (`courses.organisation_id`), and their files live under `tenants/<school>/`.
2. **Module source material** (`module_sources`): scripts, slides, forms, recorded classes and captions files.
   - They go through the same path as lesson files: a signed upload straight to storage, then a size and file-type check, a virus scan, and reading on the `content` queue.
   - Scripts and forms are read by heading and page, slides page by page, and captions line by line. Each source records its pages and word count.
   - A recorded class is kept as it is; its captions file gives its words.
   - Sources are never shown to learners. `ModuleSources::passages()` is what A7.3 drafts from.
3. **What AI will make** is planned from the words read, at 140 spoken words a minute:
   - minutes of teaching; lessons at the course's lesson length;
   - check-ins at the approval's check-in interval;
   - five questions and eight flashcards per lesson;
   - credits at screen 69's `ai_lesson_minute` cost, doubled when the lesson is voiced in both languages (decision 4, 23 November 2026).
4. **Builder settings per course:** language (English and Spanish by default), minutes per lesson (3 to 30), and presenter. The presenter is a stock avatar, or an instructor of the course whose avatar consent is in force (ADR 0025).
5. **Lesson review status.** A school's lessons carry `review_status`: draft, generating, in review, or approved. Library lessons have none.
   - Learners (Learn, the lesson screen, search) see a school's lessons only once approved, enforced in one query scope (`Lesson::learnable`) and in knowledge search.
   - A hand-written lesson is approved by the people who build the course. Editing its text or language sends it back to draft.
   - AI lessons are approved line by line in the lesson review (A8.1), never on this screen.
6. **Seat time belongs to the regulator.** While an approval is in force, an instructor can't change a course's hours. "Ask to change hours" notifies the school's admins with the reason, and they file the change with TREC and record it on the approval (screen 50).
7. **Publishing** a school course is the school admin's step. It uses the A5.3 checks: an approval in force in each state for the course's delivery, and no open legal flags.
8. **Instructor dashboard (screen 42)** shows:
   - lessons to review and drafts on their courses;
   - sessions this week and today's list;
   - the practice pass rate of the school's candidates over 30 days, where a pass is 70% (TREC's exam passing score, 22 TAC §535.65(h));
   - the three most-missed topics. A7.3 drafts a lesson from them.
9. **Every change is audited** with the school as the tenant: modules, sources, lessons (added, moved, approved, removed), builder settings and seat-time requests.

## Consequences

- A7.3 renders and drafts into these modules. Its lessons arrive as `generating`, then `in_review`.
- A8.1 adds line-by-line review, versions, and the question bank on top of `in_review`.
