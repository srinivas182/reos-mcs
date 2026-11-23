# 0025: Schools, course approvals and avatar consent

**Status:** accepted for course approvals (A7.1a) and instructors with avatar consent (A7.1b), 23 November 2026.

## Context

A Texas school may call a course CE or qualifying only while it holds TREC's approval for it, and an approval covers one way of delivering the course (classroom, live online, self-paced, AI avatar). Until Sprint 7, REOS kept one approval number per state on the course (A5.3). That was enough to block going live, but it had no hours, delivery, dates, seat-time or attendance rules, and nothing noticed when an approval expired. Schools also need to own their courses (decision of 23 November 2026). A school's courses are sold only by that school, but to learners in any tenant.

## Decision

1. **Course approvals are records of their own** (`course_approvals`), one per course, state and delivery. Each holds:
   - the regulator's number and the approved hours;
   - approved-on and expiry dates;
   - a status: pending (filed and waiting), approved or withdrawn ("expired" is shown once the date passes);
   - seat-time and attendance rules: most hours in one day, check-in interval, presence for the credited hours, and makeup rules (when allowed and within how many days).

   The approval numbers kept on courses moved into these records, and the column is gone.
2. **No label without an approval in force.** A CE or qualifying course carries its label in a state (Learn on the web and phone, `credit` in `GET /learn/courses`) only with an approval that is:
   - approved, with a number;
   - approved on or before today, and not yet expired;
   - for the delivery the course uses.

   Otherwise the course says "Not approved for credit in TX yet". The same rule keeps such a course from going live (A5.3). An AI avatar version of a course needs its own AI avatar approval.
3. **Seat-time defaults come from the state's rules.** A new rule, `courses.seat_time`, is set on screen 62 and versioned like every rule. Texas, from 22 TAC §535.65(g), effective 11 June 2023:
   - at most 12 hours in one daily segment;
   - present for the hours credited;
   - a makeup only after attending two-thirds, within 90 days.

   The check-in interval (15 minutes) is REOS's own engagement check, not a TREC rule. The school can change any value to match what the approval says.
4. **Expiry is watched.** `reos:approvals:check` runs daily at 7:05 AM Central.
   - It reminds the school's admins 60 days and 30 days before an approval expires, once each, through the notification centre (`approval_expiring`, under "Your organization"). For REOS's own courses, the reminders go to super admins.
   - On expiry the course stays visible but loses its label in that state, the expiry is audited, and the school is told.
   - Renewing with a new date starts the reminders again.
5. **Courses have an owner and a delivery.**
   - `courses.organisation_id` is the school (empty for REOS's library), and `courses.delivery` defaults to self-paced.
   - Courses are exempt from row-level security, like conversations: a school's course is taken by learners in other tenants. Only the owner school's admins manage its approvals (404 for anyone else), checked in the controller and in `CourseApprovals`.
6. **Screen 50 (Course catalogue and state approvals)** for school admins, with two-step sign-in:
   - a table of course, type, state, number, hours, expiry, delivery and status, marking which approval labels the course;
   - Add approval, Renew (the expiry starts empty), Edit, and Add state (adds the state to the course);
   - Withdraw, with Undo.

   Every change is audited (`course.approval_added`, `_changed`, `_renewed`, `_withdrawn`, `_expired`).
7. **School dashboard (screen 49)** gains the approvals card: approvals in force, courses, and what needs attention (expiring within 60 days, expired, waiting for the regulator).

8. **Instructors (screen 52)** for school admins:
   - a table of the instructor, approvals in force on the courses they teach, those courses, avatar consent, rating and price (both empty until reviews and the marketplace), and status, with invitations still waiting;
   - Invite uses the existing invitation sheet, starting on Instructor;
   - **Edit** sets the courses they teach (`course_instructors`, the school's own courses only). A7.2 lets instructors edit only those courses;
   - **Revoke** ends the role at the school and stops the avatar, with Undo.
9. **Avatar consent is the instructor's, per school** (`avatar_consents`, under row-level security; consent purpose `avatar_likeness`, subject `school:<id>`).
   - Under Settings › AI avatar, the instructor reads the terms and their version, then agrees with a 2-minute sample (MP4, MOV or WebM, up to 500 MB), stored under `tenants/<school>/avatars/`.
   - The terms say: the avatar is used only by that school; only in lessons they approve; it's made by the school's video provider and deleted there when consent ends; they can withdraw at any time; and payment is between them and the school.
   - The version is the template's date. Counsel's sign-off changes it, and an older agreement then stops the avatar being used until they agree again.
10. **When consent ends, the school has 30 days.**
    - **Withdrawing** (in Settings or from the privacy history) deletes the sample at once, records the withdrawal and tells the school's admins (`avatar_consent_ended`). Lessons using the avatar must be unpublished or re-rendered by the date given; A7.3 lessons carry the avatar they use, so they can be found and flagged.
    - **Revoke** ends use the same way, but keeps the sample for a day so Undo can bring it back. `reos:approvals:check` then deletes it.
    - Deleting an account deletes its samples. Avatar consents are in "Download my data".

## Consequences

- The REOS content library's quick approval-number edit still works: it writes approved records for the course's delivery, and a cleared number withdraws the approval.
- The course player (A8.2) and live classes read seat time and attendance from the approval in force, not from the course.
- CE reporting to TREC (screen 51) can name the approval number that applied on the day of completion.
- A7.3 renders a presenter only while `AvatarConsents::usable()` is true, and deletes the vendor's avatar when consent ends.
