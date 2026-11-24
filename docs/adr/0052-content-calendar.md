# ADR 0052: Content calendar and celebration posts

- Status: Accepted (B12.3)
- Date: 2026-11-24 (Sprint 12; spec in docs/sprints/S12-spec.md)

## Context

Agents who post steadily get more business than agents who post when they remember. B11 gave REOS scheduled posts and B12.1–B12.2 the making. What's missing:
- a single view of the week and month, to plan, move and reuse posts;
- the posts agents most want to share (just sold, first closing, CE complete), made for them when it happens (agent screen 163);
- a profile photo and brand kit for those posts (screen 162).

Lofty, Rechat and Canva have content calendars. Few make the milestone post on their own, and none apply the Texas advertising rules to it.

## Decision

1. **One calendar over B11's posts.** `SocialCalendar` shows a week (Sunday to Saturday) or a month in the agent's time zone (their morning brief's, or America/Chicago):
   - scheduled and approval posts at their time;
   - posts that went out on the day they went;
   - drafts planned for a day (`social_posts.planned_on`).
2. **Move and reuse.**
   - A scheduled post moves to another day at the same time of day, never into the past. A draft moves its plan.
   - A post that went out doesn't move: Reuse copies it (words, media, places) as a draft planned for the chosen day.
   - Scheduling a planned draft takes it off the plan.
   - Posts are checked again when they go out (B12.1), so a move never skips a check.
   - On the web, drag a post to a day, or use Move with a date (keyboard and screen readers), with Undo. On the phone, step a post a day earlier or later, with Undo.
   - Plan a post opens the composer scheduled for that day at 10 am.
3. **Celebrations found from what REOS knows.** `reos:celebrations` runs hourly for every licensed user and looks back 14 days for:
   - a listing of theirs that went active (just listed);
   - a deal under contract;
   - a closed deal (just sold, or first closing when it's their only one);
   - CE complete for the period with a course finished lately;
   - a license renewed;
   - the anniversary of being licensed, in the week after it.

   Each subject (`listing:`, `contract:`, `closing:`, `ce:`, `renewal:`, `anniversary:`) is celebrated once. A closing is one celebration, whichever name it got first. A 5-star review joins when reviews are in REOS.
4. **Made free, shown once a day.**
   - Each celebration becomes a studio making (three captions from REOS's English and Spanish templates; three designs with the milestone's badge, the agent's photo in a circle, and the agent and broker at one size) and a draft in My posts.
   - It costs nothing, and tones (Shorter, More professional, Español) stay free.
   - At most one is shown and notified a day (`celebration` notification, inbox and push). The rest wait in My posts.
   - Clients are never named or pictured: deals name the property only. Captions go through the fair housing and claims checks.
5. **Profile photo and brand kit** (screen 162, `agent_profiles.photo_path`):
   - The photo is squared a little above the middle and sized to 800 × 800, and its lighting is moved halfway to a middle grey.
   - The check says when it's under 400 px, too dark or too bright.
   - The brand kit (colour, logo) is the brokerage's, set on the web. The brokerage's name is required and shown.
   - Background removal is left for a vendor (later).

## Consequences

- The calendar adds no scheduling engine: it is a view and two operations over B11's posts, so publishing, checks, approval and results are unchanged.
- Celebrations depend on the data being in REOS (MLS link, deals, CE, licence). Agents who don't connect those get fewer.
- The hourly job is cheap. Each milestone draws three PNGs once, and nothing is redone.
