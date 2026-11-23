# 0025: Schools, course approvals and avatar consent

**Status:** course approvals accepted (A7.1a), 23 November 2026. Instructors and avatar consent follow in A7.1b.

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

## Consequences

- The REOS content library's quick approval-number edit still works: it writes approved records for the course's delivery, and a cleared number withdraws the approval.
- The course player (A8.2) and live classes read seat time and attendance from the approval in force, not from the course.
- CE reporting to TREC (screen 51) can name the approval number that applied on the day of completion.
