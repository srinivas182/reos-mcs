# ADR 0059: A free candidate studio with pre-license rules

- Status: Accepted (B13.3)
- Date: 2026-11-27 (Sprint 13; spec in docs/sprints/S13-spec.md)

## Context

Candidates have no tools to share their progress. Schools post generic congratulations, and some candidates advertise real estate services before they're licensed. That's unlicensed activity in Texas (Tex. Occ. Code §1101.351).

REOS already has three building blocks:
- **The data:** the candidate's study streak, practice exam scores and exam result (A9, A10.3b).
- **The design engine:** the celebration design and caption engine (B12.3).
- **The checks:** the compliance engine (B13.1).

## Decision

1. **The moments.** Three moments, each made into a post once and kept as a `celebration`:
   - exam passed (`licence_journeys.passed_on`);
   - a study streak of 7, 14, 30, 60 or 100 days;
   - a practice exam score of 80% or more.
2. **The posts.** Each moment gets three captions from templates (English or Spanish) and three designs. The designs use the candidate's school's brand color (or REOS's) and the line "Name · Future agent" instead of a broker. They're free: no AI credits.
3. **The engine knows candidates.** For someone in the Candidate role:
   - the broker-name, broker-size and team-name rules don't apply;
   - a new rule, `unlicensed` (Tex. Occ. Code §1101.351), blocks service advertising on ads: listings, prices ($ amounts), "call me", "buy or sell", "list your home", and brokerage or team recruiting;
   - "Future agent" wording is allowed.

   The same engine runs when the candidate edits a caption (Check), posts to connected accounts, or shares it themselves, and each check is logged (ADR 0058).
4. **Posting.** Candidates post to their own connected accounts through the posting engine, or share the design and caption from the phone's share sheet or the web (download and copy).

## Consequences

- Candidates celebrate their progress safely, and schools get organic reach.
- The pre-license rule is phrase-based. Wording it can't recognize as offering services still gets through. The rule is a guardrail and doesn't replace the school's guidance.
- Once licensed and switched to an agent (A10.3c), the agent rules apply, including the broker's name.
