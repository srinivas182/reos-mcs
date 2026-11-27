# ADR 0057: One compliance engine for every outbound channel

- Status: Accepted (B13.1; B13.2 extends it)
- Date: 2026-11-27 (Sprint 13; spec in docs/sprints/S13-spec.md)

## Context

B12.1 put advertising checks into social posts. They covered fair housing wording, misleading claims, the brokerage's banned phrases and broker approval. That left gaps:

- **Missing channels.** Studio videos, emails and texts to contacts, and the agent's public page were not checked.
- **No citations or channel scope.** The rules lived in code as phrase lists, with no citation or channel scope.
- **False flags.** Keyword lists flagged "Indian Wells" or "family room". This is the weakness we saw in RealComply and Zillow's stop list.
- **Truncated broker names.** A long agent name could push the broker's name off a design or video band. 22 TAC §535.155 doesn't allow that.

## Decision

1. **`App\Compliance\ComplianceEngine` is the one check.** It runs on posts, videos, emails, texts and the public page, with the content's channel. Rules are data (`TexasAdRules`): each has a code, citation, URL, channels, severity (block or warn) and a fix. The Texas rules are always on:
   - the broker's name in every ad, and at least half the size of the largest contact information, in full (§535.155);
   - team names with the broker's name and without words like "realty" or "brokerage" (§535.154);
   - the IABS and Consumer Protection Notice links on the public page (§531.18, §531.20);
   - no misleading claims, and no sales agent presented as a broker, owner or president (§535.155(d)).

   Fair housing (42 U.S.C. §3604(c)) and the brokerage's phrases apply everywhere. Emails and texts to one contact aren't ads, so the broker-name rules don't apply to them.
2. **Context exceptions.** `Wording` first masks place, street and room names ("Indian Wells", "Christian Street", "family room"), so neither checks nor fixes touch them.
3. **Unclear wording gets a second opinion.** Phrases like "walking distance" or "exclusive" depend on how they're used. Claude judges each phrase in its sentence when it's configured: free to the agent, cached for a day, the text treated as data. A problem blocks and comes with a fix; otherwise it's dropped. Without Claude, it's a warning the agent is asked to check, never a block.
4. **Where the engine runs:**
   - Social checks report each rule with its citation.
   - Videos won't render while the engine finds a problem.
   - The public page won't publish without the broker's name and a clean bio, and shows the brokerage's name when there's no MLS office.
   - Assistant drafts are fixed before the agent sees them, with a note of what changed.
   - Texts from the phone are fixed before Messages opens.
   - The web text dialog has Check before sending and Fix automatically.
5. **The broker's name is never cut off.** Designs and video bands put the broker on its own line, at the same size, when the line is too long; on a video the broker may take two lines.

## Consequences

- Every outbound flow passes the same rules, each with its citation, so B13.2 can route violations and log every check in one place.
- `AdvertisingPolicy` keeps the brokerage's settings and approval rules and uses `Wording` for phrases.
- Social profile bios can't be read through the platforms' APIs, so REOS gives the IABS and Consumer Protection Notice links to copy into them instead of checking them.
