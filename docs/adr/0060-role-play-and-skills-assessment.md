# ADR 0060: Role-play with a score and three fixes, and the skills assessment

- Status: Accepted (A14.1a)
- Date: 2026-11-27 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

Agents learn to win listings and handle objections by practising out loud. The leaders (Maverick RE with Follow Up Boss, PitchMonster) offer AI phone role-play, but:
- they cover prospecting calls only;
- the call must come from the agent's own phone;
- their scores don't feed a plan or a lesson.

The skills assessment (agent screen 7) was moved from Sprint 11 to this sprint so it could be built on role-play. Until now the 90-day plan started from its phases alone.

REOS already has the pieces:
- on-device speech on the phone (A9.3 hands-free) and browser speech on the web (A11.1c brief);
- Claude on Amazon Bedrock with forced tools, and REOS fallbacks (A12, A13.3b).

## Decision

1. **Five skills, eight personas.** Listing presentation, objections, prospecting call, appraisal gap and follow-up. Each has one or two people who push back, for example a seller interviewing three agents, a buyer worried about rates, a FSBO, an expired listing, or a low appraisal.
2. **The conversation.**
   - The persona speaks first.
   - The agent answers by voice or by typing. Speech is recognised on the phone or in the browser, so no audio reaches REOS. The words go into the answer box, so the agent can check them before sending.
   - The persona replies after each answer. Claude plays it when it's configured, with a brief that is never shown, no legal advice, and the agent's words treated as data. Otherwise REOS's scripted lines play it.
   - After four answers (two in an assessment round), the persona agrees to the next step or declines.
3. **Scoring: always 100 points and exactly three fixes.**
   - The score has five parts of 20 points: structure, questions, objections, clarity and the ask.
   - Claude scores when it's configured. If it fails or gives anything but three valid fixes, REOS's own scoring runs instead. REOS's scoring is keyword-based and works in English and Spanish.
   - The three fixes are the weakest parts. Each fix has a sentence, a line to try, and a lesson code (for the course picks in A14.2).
   - The persona agreed (won) when the ask scored 10 or more.
4. **The skills assessment** is five rounds, one per skill.
   - When every round is scored, REOS keeps the results and the two weakest skills.
   - The 90-day plan gets a weekly task to practise the weakest skill. REOS ticks it when a practice of that skill is scored that week.
   - Taking the assessment again starts a new one.
5. **Privacy.** Practice is the agent's own. Rows have no organisation, only the agent can open them (anyone else gets 404), and they move with the agent if they change brokerage (A14.3).

## Consequences

- Practice and its results work without Claude. With Claude, the persona feels real and the fixes are specific to what the agent said.
- REOS's scoring rewards the right moves (questions, acknowledging the concern, a specific ask) but can't judge quality the way Claude can. Agents see which engine scored each practice.
- Brokers can't see agents' practice. A shared view for mentors would need the agent's consent and is out of scope.
