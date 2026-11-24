# ADR 0050: Content studio

- Status: Accepted (posts, carousels, ad copy and advertising policy, B12.1)
- Date: 2026-11-24 (Sprint 12; spec in docs/sprints/S12-spec.md)

## Context

Agents need content for listings, sales and market updates every week. Canva and Lofty's Social Studio make good-looking posts, but they leave out Texas advertising rules, fair housing wording and the brokerage's own policy. A post that breaks them is the broker's problem. B11 gave REOS one posting engine with the broker's name on every post. B12.1 adds the making, and the checks a broker would want.

## Decision

1. **Make from what the agent already has.** The sources are:
   - a listing the MLS lets them post (B10.2);
   - a closed deal, which names the property and never the clients;
   - a market update in their words, with a city;
   - their own words, typed or spoken.

   The outputs the agent can ask for are:
   - **post:** three captions (professional, warm, short) with hashtags, and three designs;
   - **carousel:** listing photos, then the agent's card;
   - **ad copy:** three headlines, three primary texts and a description.
2. **Words by Claude, or by templates.**
   - With Bedrock keys, Claude writes from the facts only. It describes the property, never who should live there, and promises nothing.
   - Without keys, REOS's English and Spanish templates write the same shapes.
   - Changing a caption's tone (Shorter, More professional, Español) is free.
   - One making costs `content_post` (5 credits), shown before anything is spent.
3. **Designs drawn on the server.**
   - Square 1080 × 1080 PNGs drawn with GD and Inter, bundled under `resources/fonts`, OFL.
   - Three layouts: a band at the bottom, a band at the top, and a split.
   - They use the brokerage's colour from its brand (A4.2), or REOS navy.
   - The agent and broker sit on one line at one size, so the broker's name is never less than half the largest contact information (22 TAC §535.155(a)(2)).
   - Photos come from the MLS (or the demo listings' PNG copies) or the agent's uploads; a photo that can't be read leaves the brand colour.
   - Files live with the agent's social media and are linked by signed URLs. A design made from a listing posts with the listing's MLS line.
4. **Checks on every post** (screen 167), added to B11's broker name, accounts and title checks:
   - **Fair housing:** HUD advertising guidance, as phrases with neutral replacements ("perfect for families" → "roomy layout").
   - **Misleading claims:** guarantees, "best price".
   - **The brokerage's banned phrases.**

   A failing post can't go out. "Fix automatically" swaps each flagged phrase, or takes it out. The made versions are cleaned before they're shown.
5. **Advertising policy** (broker web screen 35, `organisations.advertising`):
   - The Texas rules are shown locked.
   - The brokerage sets approval for agents in their first 90 days (counted from the newest license date) or for everyone, and its banned phrases.
   - Changes apply at once and are audited with before and after.
   - Scheduled posts are checked again as they go out; one that now fails returns to drafts with the reason.
6. **Approval.**
   - When the brokerage wants to see an agent's posts, a post that passes its checks waits as "approval" and every broker is notified (`post_approval`).
   - Approve sends it out now, or at its time. Send back returns it to the agent's drafts with the broker's note (`post_decision`).
   - The agent can still edit or withdraw it while it waits. Every decision is audited.

## Consequences

- Designs are deliberately simple and consistent. Richer templates can be added as layouts without changing callers.
- The phrase lists catch common problems, not every one. Brokers add their own, and Claude's writing rules avoid the rest.
- Video came with B12.2 (ADR 0051). The profile photo and brand kit (162), celebration posts (163) and the content calendar came with B12.3 (ADR 0052). Celebration makings are studio makings with `source.type = celebration`, free.
