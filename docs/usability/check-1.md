# Usability check 1 (Sprint 6 gate)

**Owner:** Nelle (recruits and moderates). **Support:** Mayura (accounts, environment, logging).
**When:** the first week of Sprint 7. The 40 pilot users are a Sprint 6 dependency, so the check moves to Sprint 7 under the rule in `docs/sprints/S06-spec.md` §1.
**Output:** every finding becomes a GitHub issue labelled `usability-check-1`, triaged into Sprint 7.

## 1. What we want to learn

1. Can each role do its everyday jobs without help on the web and the phone?
2. Do the Sprint 6 features make sense to people who haven't seen them? These are Messages, notifications and quiet hours, texts, sessions with the whiteboard, and recording consent.
3. Does the design standard hold for real people? It asks for text that is easy to read, plain words, and controls that are easy to tap.
4. Does the Spanish version read naturally to Spanish-speaking agents and candidates? It uses formal "usted" and everyday US Latin American terms.

## 2. Who takes part

| Role | People | Persona (demo account) | Platform |
| --- | --- | --- | --- |
| Pre-licensing candidate | 6–8 (at least 2 in Spanish) | Sofia Reyes | Phone first, web second |
| Licensed agent | 6–8 (at least 2 in Spanish) | James Miller | Phone first, web second |
| Broker or team leader | 5–6 | Linda Park | Web first |
| Mentor | 5 | Dana Brooks | Phone and web |
| Instructor | 5 | Dr. Rachel Hughes | Web (sessions), phone |
| School administrator | 5 | Paul Diaz | Web |
| Association administrator | 3 | Karen Webb | Web |
| Service provider | 3 | Ana Ruiz | Phone |

That makes 40 people. Each person gets their own pilot account from a copy of the persona, never the shared demo login, so their messages, sessions and consent records stay their own.

## 3. Setting up (Mayura, the week before)

- [ ] **Somewhere to open REOS.** Pilot users need a private environment seeded with the personas. This is the "small preview environment" in the S02 spec; the AWS staging stack stays at the end. Until it exists, each session runs moderated, with the participant controlling a Mayura laptop over screen share.
- [ ] Phone: an EAS development build installed on each phone participant's device (iOS or Android), pointed at the same environment. Expo Go doesn't work: the app needs native modules for video and push.
- [ ] Pilot accounts created under Lone Star Realty and Texas Realty School, with two-step set up for admin roles.
- [ ] Features turned on for the two pilot tenants on screen 70. The demo seed turns on `messages` (Lone Star Realty and Texas Realty School) and `video_rooms`.
- [ ] Integrations left on the simulators (texts, push, email and video), so nothing reaches a real phone number or inbox by accident. The practice room shows people as name tiles.
- [ ] Sessions booked for "today" so the instructor and candidate tasks can join a live room. The demo seed already has last week's session with its notes and a recording both people agreed to.
- [ ] Recording consent for the usability session itself: ask before recording the screen and voice, note the answer on the sheet, and delete recordings after Sprint 7.

## 4. The session (45 minutes)

1. **Welcome (5 min).** "We're testing REOS, not you. Please think aloud. Nothing you do can break it." Ask for consent to record. Note the person's role, years in real estate, phone (iOS or Android) and language.
2. **Tasks (30 min).** Read each task aloud, then stay quiet. Don't point or name buttons. After two minutes stuck, give one hint and mark the task "helped". After each task, ask: "How easy or hard was that, from 1 (very hard) to 7 (very easy)?"
3. **Wrap-up (10 min).** Run the 10-question System Usability Scale (SUS), then ask: "What would you change first?" and "What did you expect to find that wasn't there?"

## 5. Tasks by role

Each task has a success check. Timing starts when the task is read.

### Candidate (Sofia), phone then web
1. You have a class with your instructor today. Find out when it starts and join it. *Success: in the room.*
2. In the class, your instructor asks to record. Decide whether you agree. *Success: answers the request and understands what happens next (unprompted explanation).*
3. Send your instructor a question about the option fee. *Success: message sent to Dr. Hughes.*
4. Don't get alerts after 10 PM, except urgent ones. *Success: quiet hours set to 10 PM.*
5. Find tonight's practice questions the instructor shared and answer one. *Success: an answer chosen and the explanation seen.*
6. (Spanish speakers) Switch REOS to Spanish and find your next session. *Success: language changed, next session found.*

### Agent (James), phone then web
1. Your broker sent an announcement. Find it and read it. *Success: opened, now marked read.*
2. Turn on texts from REOS for your phone number. *Success: number verified, texts on.*
3. Someone keeps messaging you. Block them, then check they can't message you. *Success: blocked.*
4. Get push notifications on this phone for new messages only. *Success: push on and other kinds off.*
5. Download a copy of your data. *Success: export requested.*

### Broker (Linda), web
1. Tell everyone on the Okoye Team about Friday's sales meeting. *Success: announcement sent to the team only.*
2. How many of them have read it? *Success: reads "Read by X of Y" correctly.*
3. An agent reported a message. Find out what happened. *Success: reaches the report, or knows it goes to REOS and the tenant admins.*
4. Message James and attach the listing agreement PDF. *Success: sent with attachment.*

### Mentor (Dana), phone and web
1. Book a 30-minute one-on-one with James for tomorrow at 10 AM. *Success: booked; James notified.*
2. Start it, draw the offer timeline on the whiteboard, and end it. *Success: drawing saved, session ended.*
3. After the session, add notes for James. *Success: notes saved and visible to James.*

### Instructor (Rachel), web
1. Schedule a 60-minute live class for your students next Tuesday at 7 PM. *Success: class scheduled.*
2. In today's session, share the "Earnest money and the option fee" lesson, then send practice questions on agency. *Success: both shared.*
3. Ask to record the session. Explain to us what the students see. *Success: asks, and describes the consent request.*
4. Find last week's recording, say who agreed to it, then delete it. *Success: finds it on the past session, names both people, deletes it.*

### School administrator (Paul), web
1. Send an announcement to all candidates about the exam-prep weekend. *Success: sent to candidates only.*
2. Which instructors have sessions this week? *Success: finds them or says where they'd expect to.*

### Association administrator (Karen), web
1. Find where Texas REALTORS® members' training and announcements are managed. *Success: reaches the right screen or names what's missing (input for Sprint 7+).*

### Provider (Ana), phone
1. An agent accepted your connection. Send them your rate sheet. *Success: message with attachment, or names what's blocking (providers join Messages later; note expectations).*
2. Stop all notifications between 8 PM and 8 AM. *Success: quiet hours set.*

## 6. What to write down

Use one sheet per person (`docs/usability/check-1-sheet.csv`):

| Column | Meaning |
| --- | --- |
| participant | P01–P40 (no names) |
| role, platform, language | as in section 2 |
| task | role and task number, e.g. `candidate-2` |
| result | done, helped, failed |
| seconds | time to success or giving up |
| ease | 1–7 answer |
| notes | what they said and where they hesitated |

After the session, add the SUS score (0–100).

## 7. From notes to issues

- **Severity:** 4 = can't finish the task; 3 = finishes with a hint or a big detour; 2 = slowed down or confused; 1 = cosmetic; 0 = not a problem.
- One issue per problem, not per participant: add each further person to the same issue and count them.
- File on GitHub with the **Usability finding** form (`.github/ISSUE_TEMPLATE/usability-finding.yml`). It adds the `usability-check-1` label and asks for the journey screen, role, platform and language.
- Severity 3–4 issues go into Sprint 7. Severity 1–2 issues are triaged with the client at Sprint 7 planning.
- **Pass mark for the gate:** every role completes its core tasks (1–2) with at most one hint. Overall SUS is 68 or above (the industry average). No severity-4 finding stays open into Sprint 8.

## 8. Already checked before the sessions (Sprint 6 wrap-up)

- Every Sprint 6 web screen was opened as each persona, in English and Spanish, at desktop (1,440 px) and phone (390 px) widths. No untranslated text, no sideways scrolling and no errors were found. The only error was the live-message server not running in the check environment.
- Found and fixed before the check: the instructor's dashboard greeted "Hi Dr." and her avatar showed "DH". Names now skip titles, so it shows "Hi Rachel" and "RH".
- Found and fixed: Messages was on only for Lone Star Realty in the demo, so instructors, candidates and school admins got "not found". It's now on for Texas Realty School too.
