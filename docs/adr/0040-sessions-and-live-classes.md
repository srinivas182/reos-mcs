# ADR 0040: Booking 1-on-1 sessions and live classes

- Status: Accepted (booking 1-on-1 sessions, A10.2a; live classes, A10.2b)
- Date: 2026-11-23 (Sprint 10; spec in docs/sprints/S10-spec.md)

## Context

Live 1-on-1 sessions run in REOS's LiveKit rooms since B6.3, but only an instructor could schedule one, with their own students. The journeys add the other side: a candidate books an instructor from Ask or Learn (screen 18), pays when the session isn't included, joins in the app, and rates it; the instructor sets hours and prices and turns the session's notes into the student's tasks (i14–i16).

## Decision

1. **Offers** (`session_offers`): each instructor or mentor sets weekly hours in their own time zone, 30- and 60-minute prices (0 means included), languages, topics and a short bio. A paid length is a **session product** (`ProductKind::Session`, never listed in the catalogue) sold by their school, with the instructor's share at 100% of what's left after REOS's platform fee, paid out through the existing payouts (decision 3). Prices exist for every live state.
2. **Slots:** free half-hour starts in the next 14 days, at least 2 hours ahead, inside the hours, clear of their bookings and other sessions. Booking locks the instructor's bookings while it checks the slot, so two students can't take the same time.
3. **Booking** (`session_bookings`): an included session is confirmed at once into a LiveKit session (the booking is the permission, so the host's contacts aren't checked). A paid one is **held for 30 minutes** and paid through the existing checkout (`/checkout/{price}?booking=`): the price can't be bought without its own held booking, and once paid, `Checkout::fulfil` confirms the booking. A hold that lapses frees the slot; a payment after that is refunded. Sponsor discounts on the product apply at checkout as for any product.
4. **Rules**, checked every minute (`reos:sessions:bookings`):
   - the student may cancel until 24 hours before with a full refund, and later without one; the instructor may cancel any time with a full refund;
   - if the instructor hasn't joined 10 minutes after the start, the session is called off and the student refunded in full and told;
   - the room ends at the booked time (B6.3); an ended session the instructor joined is complete.
   Session refunds follow these rules, not the course refund policy on screen 66.
5. **After:** the student rates once (stars, tags, comment), shown on the profile from 24 hours later. The instructor sends **follow-up tasks**, suggested from the action lines in the shared notes, which become the student's to-dos (`source = session`), since study plans are rebuilt and would drop them.
6. **Tenancy:** a booking spans a candidate and a school's instructor, so neither table has an `organisation_id`; only the two people can see or act on it.
7. **Screens:** web **Book an instructor** (candidate sidebar), one instructor's page, and **Hours and prices** for instructors; phone **Book an instructor** (from Today and from Ask), the instructor's page (checkout opens on reos.com in the browser: a live person-to-person service, App Review guideline 3.1.3(d)), **Hours and prices**, and **Send tasks**.

## Decision: live classes (A10.2b)

8. **A live class** is a video session (`kind = live_class`) with a `live_classes` row: its **course** (one the instructor teaches, or their school owns), a **capacity** (up to 100) and, when the course has a **live delivery approval** in force (classroom or live online), that approval's **attendance rules** copied onto the class: the check interval (`checkin_minutes`, default 15) and the share of seat time (`presence_percent`). Without one it's a review class: no CE label and no automatic checks.
9. **Registration:** students whose courses include it (Learn's courses for their state) register from **Live classes** or the course's link, up to the capacity (the class row is locked while a seat is taken). They may cancel until it starts, with Undo. Reminders go out an hour before (new) and 10 minutes before (B6.3). They join from Today or the class.
10. **In class** (L12, i12): students raise a hand, and the host **brings them on**: LiveKit lets them publish (live class guests can't otherwise). Students also send **questions** to the host's queue, where classmates' likes move them up and the host marks them answered. The host runs **polls**, one open at a time; a student sees the results after answering or when it closes. Everyone can **chat**. Chat is REOS's own (stored per class), so it works the same on the web, on the phone and on the simulator. Every change is sent as `class.changed` to the people in the room, and their screens fetch the class again. They also refresh every 15 seconds as a fallback.
11. **Attendance checks:**
    - In a CE class, a check opens every `checkin_minutes` after the start, only while it can still close before the end. The host can also send one at any time, one open at a time.
    - Each check stays open **3 minutes**.
    - A student who joined and hasn't answered after a minute gets **one reminder**, an urgent notification (`class_check`).
12. **Seat time** is counted for every session from joins and leaves, only between the booked start and end (`seconds_present`). A reconnect counts the time up to it first.
13. Scheduling is on the web (w505); the phone lists the instructor's week (i11) and hosts the class (i12), as the journeys say.

## Consequences

- Any person-to-person service can reuse session products and holds (brokerage mentors in A10.3b).
- An instructor needs a school to be paid; REOS-employed instructors would need REOS as the seller (not yet).
- The CE class report (who earned CE, borderline cases, Confirm and send to school) and recordings going to the course when the approval allows build on the checks and seat time kept here (A10.2c).

## Alternatives considered

- **In-app purchase on iPhone:** not needed for live 1-on-1 services, and REOS keeps purchases on the web.
- **A per-booking price without products:** would bypass tax, receipts, refunds and payouts that products already have.
