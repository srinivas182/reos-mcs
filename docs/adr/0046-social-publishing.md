# ADR 0046: Social publishing

- Status: Accepted (posting engine with Instagram and Facebook, B11.1; LinkedIn and Google Business Profile, B11.2; YouTube Shorts and TikTok, B11.3)
- Date: 2026-11-24 (Sprint 11; spec in docs/sprints/S11-spec.md)

## Context

Agents post listings and market updates to Instagram, Facebook, LinkedIn, Google Business Profile, YouTube and TikTok. Buffer and Hootsuite schedule posts well, but leave the rules that matter to the agent:
- the broker's name on every advertisement (TREC 22 TAC §535.155);
- no title a sales agent may not use;
- the MLS's display terms for listing photos.

Sprint 11 also has to work before Meta, LinkedIn, Google and TikTok approve the app.

## Decision

1. **One posting engine for every channel.**
   - A post (`social_posts`) holds the text, the media, an optional link, an attribution line and when it goes: now, a time, or a draft.
   - It goes to one or more places (`social_post_targets`), such as a Facebook Page or an Instagram account. Each place has its own status, tries, the post's id and link there, and insights.
   - The post's status follows its places: published, partly or failed.
2. **Channels plug into the connection engine** (ADR 0013).
   - Each channel is a `ConnectorDriver` that also implements `PublishesPosts`, which has three jobs: list where the account can post, publish, and read insights. It also says each channel's needs, such as Instagram needing a photo or video, and its text limits.
   - Every channel has a Simulator, so the whole flow runs on demo data until the approvals arrive.
   - Tokens are stored per agent and encrypted, as for every connection.
3. **Attribution and checks before anything is scheduled.**
   - Every post carries "Agent · Broker". The broker is the agent's brokerage, or the sponsoring broker on their license.
   - A listing post also carries the MLS's line and may use only the photos the MLS allows (B10.2).
   - A post that fails a check (no broker name, no account, a channel's limits, or a sales agent called broker, owner or president) can't be published or scheduled. It can be saved as a draft.
4. **Publishing and insights.**
   - Each place is a queued job, retried with backoff (1, 5 and 15 minutes). A lapsed connection fails at once with "reconnect" and marks the connection for reconnecting.
   - Due posts go out every minute.
   - Insights are read hourly for 30 days after posting.
5. **Media the channels can fetch.** Uploaded photos and videos stay on REOS's storage. Channels receive a signed link that lasts 48 hours, never a public bucket. Listing photos use the MLS's own URLs.
6. **Meta specifics.**
   - Facebook Login gives a long-lived user token, which is swapped again before it lapses. Meta has no refresh tokens.
   - A Page's token is fetched when needed and never stored.
   - Instagram publishes in two steps: a media container, then `media_publish`. A video waits for processing.

7. **LinkedIn** (B11.2) uses the versioned REST API (`LinkedIn-Version` header):
   - **Profile posts:** "Share on LinkedIn" posting from the agent's own profile is self-serve.
   - **Company pages** the agent administers, and their statistics, need the Community Management API. Until LinkedIn approves it, they are skipped quietly, and the catalogue keeps LinkedIn as a beta that a brokerage joins.
   - **Images** are uploaded first, and a link becomes an article card.
   - **Reconnecting:** LinkedIn gives refresh tokens only to approved partners, so a connection asks to reconnect when its 60-day token ends.
8. **Google Business Profile** (B11.2):
   - **Places:** each location the agent manages.
   - **Posts:** "What's new" local posts of up to 1,500 characters, with one photo and a Learn more button for a link.
   - **Insights:** views and button clicks, where Google still reports them. Missing numbers aren't an error.
9. **Which social channels a person can connect** follows the connections catalogue (`canConnect`): built, running, and for a beta, their organisation joined.

10. **Short video** (B11.3). YouTube Shorts and TikTok take a video, and the checks require one (`video`).
   - **YouTube:**
     - Uses a resumable upload. The video streams from REOS's storage to Google through a temporary file, never whole in memory.
     - The title is the text's first line plus #Shorts, and the text is the description.
     - Until the API project passes Google's audit, YouTube keeps the uploads private itself.
   - **TikTok:**
     - TikTok pulls the video from REOS's signed link, so REOS's domain is verified with TikTok.
     - Posts are "only me" until the Content Posting audit. `TIKTOK_PUBLIC_POSTS` turns on public posting once it's passed.
     - REOS waits briefly for TikTok's post id and otherwise keeps the publish id.
   - **Insights:** views, likes, comments and, on TikTok, shares.

## Consequences

- LinkedIn and Google Business Profile (B11.2), and YouTube and TikTok (B11.3), add a driver and a Simulator each. The posts, checks, schedule and results screens don't change.
- "Leads from posts" on screen 169 waits for lead sources (Sprint 14). Until then the page shows reach, engagement and clicks.
- AI-written posts arrive with the content studio in Sprint 12. The studio's composer is where they will land.

## Alternatives considered

- **A third-party scheduler (Buffer, Ayrshare):** faster to start, but it would cost per post, hold agents' tokens outside REOS, and have no place for TREC and MLS rules.
- **Posting through the phone's share sheet only:** this stays available for anything else, but it can't schedule or read results.
