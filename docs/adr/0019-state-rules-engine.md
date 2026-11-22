# 0019: State rules engine

**Status:** accepted (A5.2, 22 November 2026)

## Context

Every state's regulator sets licensing, exam, CE, advertising and brokerage rules, and changes them (a new Legal Update edition, a new CE hour count). Competitors hard-code these into course pages and get them wrong or out of date: Colibri's Texas page gives two different fees and two different first-renewal hour counts, and none of them tells a licensee what *they* still owe. REOS needs one source of truth that courses, the CE card, reminders, broker compliance and post checks all read, and that can change without a code release.

## Decision

1. **Rules as data** (`state_rules`). Each rule is a key from `App\StateRules\RuleCatalogue` (for example `ce.hours`, `ce.mandatory_courses`, `advertising.broker_name_size`) with a typed value (`RuleType`: number, courses, role course, first renewal, fee and days, text), its source (citation and link), the date it takes effect, the date it was checked, and a version.
2. **Versions, never edits.** Super admins change rules on screen 62 as **drafts**; "Publish changes" turns every draft in the state into a new published version. The rule in force on a date is the newest published version whose dates contain it, so a change effective 1 September changes nothing before then, and history is never lost.
3. **Texas seeded from TREC** in the migration (22 TAC §535.92 for CE, §535.155 for advertising, TREC's sales agent and renewal pages), checked on 22 November 2026.
4. **The CE engine** (`CeRequirements`) works out a licence's requirement for the current period from the rules in force today: mandatory courses, Broker Responsibility for sponsoring brokers, designated brokers and delegated supervisors, electives, or the first renewal's SAE hours plus Legal Update I and II. It subtracts completed CE (`ce_completions`), counts a repeated course once, caps commission meeting hours, and names the rule behind every part.
5. **Rule change alerts.** Publishing a CE change emails every licence holder in that state what changes and from when.
6. **Where used** is part of the catalogue, so screen 62 shows which features read each rule and whether each is live yet.
7. The launch checklist's first item ticks itself once a state has published licensing, exam and CE rules.

## Consequences

- Adding a state is data entry with sources, not code; adding a new *kind* of rule is a catalogue entry plus the code that reads it.
- The CE card, the dashboard tile and the phone's licence sheet always agree, because they call the same engine.
- CE completions from other providers and certificate uploads arrive with the CE wallet (Sprint 11); REOS course completions write to the same table when the player arrives (Sprint 8).
- Rule texts are a summary with a link, not legal advice; a second person reviews each state's seed before it goes live.
