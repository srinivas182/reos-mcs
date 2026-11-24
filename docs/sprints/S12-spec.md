# Sprint 12 spec: Virtual broker and AI assistant · content studio

- **Dates:** 10 May – 21 May 2027 (plan); built now, ahead of the calendar.
- **Status:** agreed 24 November 2026 under the standing approval to build the next sprints in order; the decisions in section 6 are all as recommended. **Complete:** A12.1–A12.3 and B12.1–B12.3 are merged (PRs #130–#135).
- **Sources:**
  - REOS Sprint Plan v1.0 (Sprint 12: A12.1–A12.3, B12.1–B12.3).
  - The Licensed Agent Complete Journey:
    - Ask screens: 59 Ask home, 60 Ask anything, 61 virtual broker, 62 broker confirms.
    - Contract screens: 63 review a contract (choose), 64 reviewing, 65 contract review result, 66 ask about this contract.
    - Studio screens: 107 ad studio, 162 profile photo and brand kit, 163 celebration moment, 164 share, 165 create a post, 166 pick a version, 167 before you post, 168 edit video.
  - The Web App Journey: licensed agent screens 15 contract review and 16 virtual broker; broker screens 34 virtual broker (broker side) and 35 advertising policy.
  - The Broker and Team Leader Journey: screens 5 teach the virtual broker, 11 agent questions, 12 answer an agent.
  - The S11 wrap-up.

## 0. What earlier sprints already delivered (scope adjusted)

| Planned for Sprint 12 | Already in place | Sprint 12 now does |
| --- | --- | --- |
| A12.1 Virtual broker | Ask for candidates with the knowledge base and Claude on Bedrock (A10.1); the AI answer order per role and credit costs (A6.1, screen 69); Texas state rules (A5.2) | **Policy knowledge** per brokerage: the manual (PDF, Word or text, every version kept), the FAQ built from the broker's answers, and the **Texas rules library** with citations. **Answers** with confidence and sources. **Escalation** to the broker when the answer is unclear or money or legal risk is involved, with "With your broker" and an expected time. **The broker's side:** questions, an AI draft, Confirmed or Corrected, and Add to policy knowledge. **TREC conflicts** in the manual are flagged; the TREC rule wins |
| A12.2 Contract review | Deals with files and key dates from transaction tools (B9); credits and costs (A6.1) | **Review** of TREC promulgated forms (20-18 first, then the other resale and new-home forms and common addenda): key terms, dated deadlines, findings ranked High and Check with page references. **Compare** with the earlier version. **Deadlines** to the calendar. **Send to broker.** **Ask about this contract.** 20 credits per review |
| A12.3 AI assistant | CRM, deals and calendar synced (B7–B9); Today ranked (A11.1b); CRM insights (B7.3) | **Ask anything** with the agent's own CRM, deal and calendar context, read with the agent's permission. "What should I do today?" answers from Today's real list. **Drafts** emails and texts that are never sent without a tap |
| B12.1 Studio posts | One posting engine on six channels with checks (B11) | **Create a post** from a listing, a sale, a market update or a voice note: three caption versions, designs from **templates**, carousels and ad copy. **Brand kit** (photo, colors, logo from the brokerage). **Advertising policy** (broker screen 35): locked Texas rules, brokerage rules and banned phrases. 5 credits per post |
| B12.2 Studio video | Short-video posting (B11.3) | **30-second listing video** from photos and clips with captions, the brand frame and royalty-free music. **Upload, trim** and caption edit. Rendered with ffmpeg in a queue worker behind a renderer interface (the Simulator in CI). 30 credits per video |
| B12.3 Content calendar | Scheduled posts (B11.1) | **Calendar** of the week and month: plan, move, reuse. **Celebration posts** made automatically for milestones (just listed, under contract, sold, first closing, CE complete, license renewed, anniversary), free |

## 1. Dependencies

| Needs | For | Owner | If late |
| --- | --- | --- | --- |
| Pilot brokerages' policy manuals | A12.1 | Client | REOS's sample manual (demo brokerage); answers fall back to the Texas rules and escalate |
| Claude on Bedrock keys (us-east-2) | A12.1–A12.3, B12.1 | Client with Mayura | The Simulator: answers quote the best source; reviews read the form's fields with rules |
| TREC form layouts (current versions) | A12.2 | REOS | Forms are recognized by their form number and version line |
| Royalty-free music library licence | B12.2 | Client | Three REOS-made tracks |

## 2. Market leaders: what they do, where they fall short, what we do

| Leader | What they do well (we match it) | Where they fall short | REOS answer |
| --- | --- | --- | --- |
| **Rechat (Lucy)** | One AI assistant over CRM, marketing and deals | No brokerage policy; a wrong answer about money has no broker in the loop | **Virtual broker** answers from the brokerage's manual and the Texas rules with citations, and hands money and legal questions to the broker, with status |
| **SkySlope Smart Audit, Paperless Pipeline** | Compliance review of the file after it's submitted | Back office first; the agent learns about problems after the fact | **Contract review** for the agent before they respond: dated deadlines, ranked findings with pages, sent to the broker in one tap |
| **Lofty Social Studio, Canva, Rechat marketing center** | Templates, brand kits, AI captions, scheduling | Texas advertising rules and brokerage policy are left to the agent | **Studio** with the checks built in: broker name at the required size, fair housing wording, banned phrases, and broker approval when the brokerage asks for it |

## 3. Sub-sprints

### A12.1 Virtual broker (Team A)

- **Knowledge** (broker screen 34; broker journey screen 5): the brokerage uploads its policy manual (PDF, Word or text). Each upload is a new version; the old versions are kept. The manual is split into passages with their page and heading. The topics found and any **TREC conflicts** are shown, each with the TREC source; the TREC rule wins and the broker is told.
- **Escalation rule:** who answers (designated broker, or a named broker of the brokerage) and how fast (a reply-time target in hours).
- **Answers** (agent screen 61; web screen 16): from the manual, the brokerage FAQ and the Texas rules library, with a confidence level and citations (manual page or TREC rule).
- **Escalation:** automatic when the sources don't answer clearly, or when money or legal risk is involved; the agent can also tap Send to my broker. The agent sees "With your broker" and the expected time.
- **Broker answers** (broker journey screens 11 and 12; agent screen 62): the question, why it was escalated and an AI draft with sources. Confirmed or Corrected, labelled "From your broker". Add to policy knowledge puts the answer in the FAQ for every agent.
- **Done when:** unclear money or legal questions escalate.
- **As built, A12.1** (ADR 0047):
  - **Knowledge** (`PolicyKnowledge`):
    - Policy manuals: PDF, Word, text or Markdown up to 20 MB. Each is split into passages with page and heading; topics are found (advertising, splits, escrow, coordinators, leads, forms, supervision, fair housing). Every version is kept (`policy_manuals`, `policy_passages`).
    - The brokerage FAQ comes from broker answers, removable with Undo.
    - Texas rules: five new text rules (trust money §535.146, compensation §535.3, supervision §535.2, IABS §531.20, promulgated forms §537.11), plus the four advertising rules and sponsorship.
  - **TREC conflicts** (`TexasConflicts`): trust money after the second working day, agents paid directly, the broker name optional in ads, agents drafting their own clauses. Shown to the broker with the sentence, page and rule; answers on that passage put the rule first.
  - **Search** (`PolicySearch`): explainable word-share ranking (two shared words and 30% of the question at least); FAQ entries rank a little higher.
  - **Answers** (`VirtualBroker`):
    - Claude on Bedrock from the numbered sources, with confidence and "needs broker"; without keys, the Simulator quotes the best sources.
    - Free; within the hourly limit.
  - **Escalation:** money, legal risk, unclear answers, or Send to my broker. The agent sees "With your broker", why, and the expected time. Brokers get a notification (`policy_question`); agents get one when answered (`policy_answer`).
  - **Broker reply:** the draft sent as is is Confirmed, edited is Corrected; labelled "From your broker"; Add to policy knowledge. Who answers (a named broker or all) and how fast (hours, default 4) are set per brokerage. All audited.
  - **API:** `GET /api/v1/virtual-broker`, `POST /api/v1/virtual-broker/questions`, `GET /api/v1/virtual-broker/questions/{id}`, `POST /api/v1/virtual-broker/questions/{id}/escalate`.
  - **Web:**
    - Agent: `/virtual-broker` (screen 16), in the agent sidebar.
    - Broker: `/organisation/virtual-broker` (screen 34, with broker journey screens 5, 11 and 12), in the broker sidebar: questions (waiting and answered), stats, manual and versions, conflicts, FAQ, escalation.
  - **Phone:** Ask → "Ask your virtual broker" (`ask/broker`, screen 61) and a question (`ask/broker/[id]`, screens 61 and 62). Broker phone screens come with the broker app (Sprint 15).
  - **Demo:** Lone Star Realty has a sample manual (with one TREC conflict, on purpose).

### A12.2 Contract review (Team A)

- **Choose** (63): upload a PDF, scan pages, or pick a deal's document.
- **Reviewing** (64): read pages, pull terms and dates, check common problems, compare with the earlier version; a notification when done.
- **Result** (65; web screen 15): key terms with changes, findings ranked High and Check with page references, deadlines ready for the calendar. Not legal advice.
- **Ask about this contract** (66): answers citing pages, and a draft note to the client.
- **Send to broker:** the summary goes to the broker's questions.
- **Done when:** a review of a TREC 20-18 lists dated findings.
- **As built, A12.2** (ADR 0048):
  - **Reading** (`TrecForms`, `ClaudeContractReader`):
    - TREC 20-18 is recognized by its title and form line. Addenda are recognized by a page starting with their title; the Third Party Financing Addendum is read first.
    - Seventeen key terms, each with its page.
    - Claude (Bedrock) reads text or scans and adds findings; without keys, the rule reader alone.
  - **Checks** (`ContractChecks`):
    - Deadlines from the Effective Date: earnest money and option fee, option period (5:00 p.m.), survey, financing approval, then closing.
    - High: no Effective Date, no earnest money, price math, financing without its addendum, closing before a buyer deadline or before the Effective Date.
    - Check: weekend dates, no escrow agent, no option or option fee, legal wording in special provisions (§537.11), and changes since the earlier version.
  - **Earlier version:** the same deal's latest review, or the same property's. Terms show what they were.
  - **Files:** a new PDF for a deal is kept as a deal file, or the deal's own document is reviewed. The pages' text is kept encrypted. Reviews are private (`contract_reviews`, row-level security).
  - **Credits:** 20 per review, charged when done (a file REOS can't read is free). 1 per question.
  - **Background:** the `ReviewContract` job steps through read, terms, checks and compare, then sends a `contract_review` notification.
  - **Then:**
    - Deadlines go to the deal as key dates with to-dos, or as to-dos on the calendar; added once.
    - Send to broker becomes a virtual broker question.
    - Ask about this contract answers with pages and a note for the client.
  - **Sample:** a filled-in TREC 20-18 with its financing addendum, outside production.
  - **API:** `GET` and `POST /api/v1/contracts`, `GET /api/v1/contracts/{id}`, and `POST /api/v1/contracts/{id}/deadlines`, `/broker` and `/questions`.
  - **Web:** `/contracts` (screen 15 with 63; Contract review in the agent sidebar) and `/contracts/{id}`: the contract beside the result, where a page reference opens that page, with the steps while reviewing.
  - **Phone:** Ask → Review a contract (`contracts`, screen 63), `contracts/[id]` (screens 64 and 65) and `contracts/ask/[id]` (66).

### A12.3 AI assistant (Team A)

- **Ask home** (59): six starters, recent chats, the ask bar.
- **Ask anything** (60): answers with sources and dates; Explain simpler, Ask my broker, Save.
- **Context:** the agent's CRM contacts, deals and calendar, read only with the agent's permission (one switch).
- **Drafts:** emails and texts to a contact, sent only by the agent's tap.
- **Done when:** "What should I do today?" uses real CRM data.
- **As built, A12.3** (ADR 0049):
  - **Routing** (`AgentAssistant`): draft (a verb and a name), prep, today, deal, policy (to the virtual broker), general. English and Spanish.
  - **Permission:** one switch for CRM, deals and calendar (`agent_profiles.assistant_context_at`), audited. Until it's on, answers that need the data say so and offer Allow.
  - **Today:** Today's ranked items with sources and deal links, plus the brief's first call. Done when: "What should I do today?" lists the CRM follow-up (Follow Up Boss), the deal deadline (dotloop) and the appointment.
  - **Prep:** the next appointment with the contact's CRM summary, latest note, last activity and deal.
  - **Deal:** stage and open key dates.
  - **Drafts:** email or text from the CRM, by Claude (1 credit) or a template, opened as `mailto:` or `sms:`, never sent by REOS.
  - **Policy:** the virtual broker answers and escalates; the answer links to it.
  - **General:** Claude (2 credits); without keys, what it can do.
  - **Chats** (`assistant_chats`, `assistant_messages`) are private to the agent.
  - **API:** `GET /api/v1/assistant`, `POST /api/v1/assistant/messages`, `GET /api/v1/assistant/chats/{id}`, `PUT /api/v1/assistant/permission`.
  - **Phone:** the Ask tab for agents is Ask home (screen 59): six starters (prepare a contract is marked Coming soon), today, recent chats and the ask bar. Answers (screen 60) show items with sources, drafts with Open in Mail or Messages, and a link to the virtual broker.
  - **Web:** no assistant page, as the Web App Journey has none for agents.

### B12.1 Studio posts (Team B)

- **Create a post** (165) from a listing, a sale, a market update, text or a voice note; outputs: post and caption, carousel, ad copy.
- **Pick a version** (166): designs to swipe, three captions with Shorter, More professional, Español.
- **Before you post** (167): Texas rules, brokerage rules, Fix automatically, Send to broker.
- **Brand kit** (162) and **advertising policy** (broker web screen 35).
- **Ad studio** (107): hooks and ad versions with the checks.
- **Done when:** a listing post is made in under a minute.
- **As built, B12.1** (ADR 0050):
  - **Making** (`StudioGenerator`): from a listing (posts only from the agent's own MLS listings), a closed deal (property only, never the clients), a market update or the agent's words (typed or spoken).
    - **Post:** three captions (professional, warm, short) and three designs.
    - **Carousel:** listing photos and an agent card.
    - **Ad copy:** three headlines, three primary texts and a description.
    - Claude writes when configured; otherwise English and Spanish templates.
    - 5 credits per making; tones (Shorter, More professional, Español) are free.
  - **Designs** (`StudioRenderer`): 1080 × 1080 PNGs with GD and Inter (bundled, OFL) in the brokerage's colour. The agent and broker are on one line at one size. Demo listing photos have PNG copies.
  - **Checks** (`AdvertisingPolicy`):
    - Fair housing wording (HUD guidance), misleading claims, and the brokerage's banned phrases, added to B11's checks.
    - Fix automatically.
    - Scheduled posts are checked again as they go out.
  - **Approval:** for new agents (first 90 days of license) or everyone. The post waits as "approval"; the broker approves it (now or at its time) or sends it back with a note. Notifications go both ways, and every decision is audited.
  - **API:** `GET /api/v1/studio`, `POST /api/v1/studio/generations`, `GET /api/v1/studio/generations/{id}`, `POST /api/v1/studio/generations/{id}/captions`, `POST /api/v1/social/posts/fix`.
  - **Web:**
    - Content studio (screen 17) now has Make content and Pick a version above the composer. The composer shows the new checks, Fix automatically and the approval note.
    - Broker: `/organisation/advertising` (screen 35, Policies in the broker sidebar): approvals, Texas rules, brokerage rules, banned phrases, brand kit.
  - **Phone:** My posts → Make content (`social/create`, screen 165) → `social/versions/[id]` (166) → the post (`social/new`, with the checks and Fix automatically, screen 167). Ask's "Write an ad or post" starter opens it.
  - **Done when:** a test makes a listing post (captions, designs, carousel, ad) and publishes it with the MLS line, all within 60 seconds on the Simulator.

### B12.2 Studio video (Team B)

- **Listing video:** 30 seconds from photos and clips, with captions, the brand frame and music.
- **Edit video** (168): trim, captions, music, brand, hook.
- **Rendering:** ffmpeg in a queue worker behind a renderer interface; the Simulator in CI.
- **Done when:** a 30-second listing video is rendered.
- **As built, B12.2** (ADR 0051):
  - **Making** (`StudioVideos`):
    - From a listing (up to eight postable photos) or the agent's own clips and photos.
    - Clips go after the first photo, and the video closes on the agent's card.
    - It has a hook for the first two seconds and captions per scene (address, beds and baths, price, notes, the call).
    - Photos are stretched to about 30 seconds; the video is capped at 60 seconds and 12 scenes.
    - 30 credits, shown first.
  - **Edit video** (168):
    - **Trim:** scene length, and the start of a clip.
    - Reorder or remove scenes (with undo), and add a clip or photo.
    - **Captions, Music, Brand** (the colour on the frame; the names always show), and **Hook** (three suggestions, free).
    - Every edit is checked again and shown, with Fix automatically.
    - Done renders again (free), and is refused while words are flagged.
  - **Rendering** (`VideoRenderer`):
    - `FfmpegRenderer` makes 1080 × 1920 H.264 at 30 fps: slow zoom on stills, clips cropped, pauses removed (`silencedetect`).
    - Overlays (captions, hook, the frame with agent and broker) are drawn by `VideoFrames` with GD and Inter.
    - Music is REOS-made (`calm`, `bright`) or none.
    - `SimulatedRenderer` runs in CI. The queue job is `RenderStudioVideo`.
    - ffmpeg is added to the preview and dev container images.
  - **API:** `POST /api/v1/studio/videos`, `GET` and `PUT /api/v1/studio/videos/{id}`, `POST /api/v1/studio/videos/{id}/hooks`. `GET /api/v1/studio` adds `video_cost`, `tracks` and `videos`. Signed media now serves byte ranges (local) or a short-lived S3 link.
  - **Web:** the content studio (screen 17) has Videos: make one from a listing, watch it as it finishes, Use in a post.
  - **Phone:** Make content (165) → Listing video → `social/video/[id]` (168) → Use in a post → `social/new` (167).
  - **Done when:** a test renders a real 30-second, 1080 × 1920 MP4 with sound using ffmpeg (skipped where ffmpeg isn't installed). The Simulator test makes, edits, fixes, renders again and posts a 30-second listing video.
  - **Later:** filler-word removal (needs a transcript), and a licensed music library (the client's licence).

### B12.3 Content calendar (Team B)

- **Calendar:** week and month, drag to move, reuse a post.
- **Celebration moment** (163): milestone posts made automatically, at most one a day, free.
- **Done when:** a week of posts is scheduled in one view.
- **As built, B12.3** (ADR 0052):
  - **Calendar** (`SocialCalendar`):
    - A week (Sunday to Saturday) or a month in the agent's time zone: scheduled and approval posts at their time, posts on the day they went out, and drafts planned for a day (`social_posts.planned_on`).
    - Move: a scheduled post keeps its time of day, a draft moves its plan, never into the past, with Undo. Posts that went out don't move.
    - Reuse: a copy as a draft planned for a day.
    - Plan a post: the composer is scheduled for that day at 10 am.
  - **Celebrations** (`Celebrations`, `reos:celebrations` hourly):
    - Milestones from REOS's own data in the last 14 days: just listed, under contract, just sold, first closing, CE complete, license renewed, and the anniversary.
    - Each subject is celebrated once and becomes a free studio making (three captions; three designs with the badge and the agent's photo) and a draft in My posts.
    - At most one is shown and notified a day (`celebration`); the rest wait. Clients are never named.
  - **Profile photo and brand kit** (`BrandKit`, screen 162): the photo is squared, sized to 800 × 800, its lighting evened out, with a quality check (small, dark, bright). Name, brokerage (required), license number, and the brokerage's color and logo are shown.
  - **API:**
    - `GET /api/v1/social/calendar`, `POST /api/v1/social/posts/{id}/move`, `POST /api/v1/social/posts/{id}/reuse`;
    - `GET /api/v1/studio/celebrations`, `GET /api/v1/studio/celebrations/{id}`;
    - `GET /api/v1/me/brand`, `POST` and `DELETE /api/v1/me/brand/photo`.
  - **Web:** `/studio/calendar`: week and month; drag, or Move with a date, with Undo; Reuse; Plan a post; today's celebration. The studio page has a Calendar action and opens scheduled for a planned day.
  - **Phone:**
    - My posts → Content calendar (`social/calendar`) and Celebrations.
    - A celebration (`social/celebrate/[id]`, screen 163) opens Pick a version with its badge, free.
    - Me → Photo and brand (`social/brand`, screen 162).
    - Plan a post opens the composer scheduled for the day.
  - **Done when:** a test schedules seven posts across a week and the calendar shows all seven, one a day, in one view (API, web page and month view).

## 4. Definition of done (whole sprint)

- Each sub-sprint has its own PR with green CI, merged before the next starts.
- Web and phone screens match the journeys side by side, in English and Spanish.
- Every new endpoint is in `/api/v1` (or a web route), documented, with the api-client regenerated and Pest feature tests.
- **Security tests:**
  - an agent sees only their own brokerage's policy and their own questions; a broker sees only their brokerage's;
  - contracts are stored with the deal, private to the agent and their broker, and deleted from the AI after the review;
  - the assistant reads CRM data only with the agent's permission, and never sends anything on its own.
- **ADRs:** 0047 virtual broker; 0048 contract review; 0049 AI assistant; 0050 content studio; 0051 studio video; 0052 content calendar and celebrations.

## 5. Risks

| Risk | Mitigation |
| --- | --- |
| A wrong policy answer about money | Money and legal topics always go to the broker; every answer cites its source; low confidence escalates |
| A manual contradicts TREC | Conflicts are flagged on upload and the TREC rule wins in every answer |
| Contract review read as legal advice | "Not legal advice" on every result; only facts from the document; the broker reviews before the agent responds |
| Video rendering is slow or expensive | A queue worker with a time limit; the Simulator in CI; credits shown before anything is spent |

## 6. Decisions (24 November 2026, all as recommended)

1. **Virtual broker knowledge:** the brokerage's manual (versions kept), its FAQ from broker answers, and REOS's Texas rules library. Answers from these are free; money and legal topics always escalate.
2. **TREC wins:** a manual passage that contradicts a TREC rule is flagged; answers on that topic cite the TREC rule.
3. **Contract review:** TREC promulgated forms first; 20 credits per review; contracts are kept with the deal, not with the AI.
4. **Assistant context:** one permission switch for CRM, deals and calendar; nothing is sent without the agent's tap.
5. **Studio:** templates plus the brand kit; Texas rules locked; brokerage rules and banned phrases set by the broker; broker approval optional per brokerage.
6. **Video:** ffmpeg in a queue worker behind a renderer interface; royalty-free music only.

## Sources

- TREC rules, 22 TAC Chapter 535 (General Provisions): https://www.trec.texas.gov/agency-information/rules-and-laws
- 22 TAC §535.146 Maintaining Trust Money: https://law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-146
- TREC FAQ, depositing earnest money: https://www.trec.texas.gov/node/354
