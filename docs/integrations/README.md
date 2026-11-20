<!-- Generated from register.yaml by scripts/integrations-register.mjs. Do not edit; run `pnpm integrations:render`. -->

# Integrations register

Every integration, platform service, vendor account and outside approval REOS needs for the Texas pilot on **14 Sep 2027**, with who owns it, how we get access, and when it must be filed. Source: [`register.yaml`](register.yaml) (schema: [`register.schema.json`](register.schema.json)). Updated 20 Nov 2026.

Credentials rules: [credentials policy](credentials-policy.md). Lead times are planning estimates until the vendor confirms.

## At a glance

|  | Count |
| --- | --- |
| Integrations users connect (catalogue) | 30 (29 enabled, 1 beta, 0 coming soon at the pilot) |
| Platform services REOS runs on | 13 |
| Vendor accounts | 7 |
| Outside approvals | 2 |
| Access approved | 1 |
| Filed and waiting | 0 |
| Blocked | 0 |
| Not started | 50 |
| Already past their apply-by date on 20 Nov 2026 | **1** |

## Action list: file in this order

"Apply by" = the start of the sprint that needs it, minus the estimated lead time, or the planned date (marked †) when the sprint plan files it earlier because the vendor's approval time is unpredictable. ⚠ marks items whose apply-by date has already passed: file them first.

| Apply by | Item | Owner | How | Lead (est.) | Needed by | Status |
| --- | --- | --- | --- | --- | --- | --- |
| ⚠ 9 Nov 2026 | [Google Calendar and Gmail](#google-workspace) | Client + Mayura | OAuth verification | 10 wk | S4 (18 Jan 2027) | Not started |
| 23 Nov 2026 | [Apple Developer Program (organisation)](#apple-developer) | Client | Vendor review | 4 wk | S2 (21 Dec 2026) | Not started |
| 7 Dec 2026 | [Google Play Console (organisation)](#google-play) | Client | Vendor review | 2 wk | S2 (21 Dec 2026) | Not started |
| 14 Dec 2026 | [AWS organisation](#aws-organisation) | Client | Self-serve | 1 wk | S2 (21 Dec 2026) | Not started |
| 14 Dec 2026 | [Cloudflare](#cloudflare) | Client | Self-serve | 1 wk | S2 (21 Dec 2026) | Not started |
| 14 Dec 2026 | [Expo organisation (EAS)](#expo) | Mayura | Self-serve | 1 wk | S2 (21 Dec 2026) | Not started |
| 14 Dec 2026 | [OpenTelemetry and Grafana](#grafana-otel) | Mayura | Self-serve | 1 wk | S2 (21 Dec 2026) | Not started |
| 14 Dec 2026 | [PostHog](#posthog) | Mayura | Self-serve | 1 wk | S2 (21 Dec 2026) | Not started |
| 14 Dec 2026 | [Product domain](#product-domain) | Client | Self-serve | 1 wk | S2 (21 Dec 2026) | Not started |
| 14 Dec 2026 | [Sentry](#sentry) | Mayura | Self-serve | 1 wk | S2 (21 Dec 2026) | Not started |
| 18 Dec 2026 † | [Follow Up Boss](#follow-up-boss) | Client + Mayura | Partner application | 4 wk | S7 (1 Mar 2027) | Not started |
| 18 Dec 2026 † | [kvCORE / BoldTrail](#boldtrail) | Client + Mayura | Partner application | 8 wk | S7 (1 Mar 2027) | Not started |
| 18 Dec 2026 † | [Lofty](#lofty) | Client + Mayura | Partner application | 6 wk | S8 (15 Mar 2027) | Not started |
| 18 Dec 2026 † | [Salesforce](#salesforce) | Mayura | Self-serve | 2 wk | S8 (15 Mar 2027) | Not started |
| 18 Dec 2026 † | [DocuSign](#docusign) | Mayura | Vendor review | 2 wk | S9 (29 Mar 2027) | Not started |
| 18 Dec 2026 † | [dotloop](#dotloop) | Client + Mayura | Partner application | 6 wk | S9 (29 Mar 2027) | Not started |
| 18 Dec 2026 † | [Lone Wolf Transactions](#lone-wolf) | Client + Mayura | Partner application | 10 wk | S9 (29 Mar 2027) | Not started |
| 18 Dec 2026 † | [SkySlope](#skyslope) | Client + Mayura | Partner application | 6 wk | S9 (29 Mar 2027) | Not started |
| 18 Dec 2026 † | [HAR MLS (Houston)](#mls-har) | Client | Data licence | 8 wk | S10 (12 Apr 2027) | Not started |
| 18 Dec 2026 † | [NTREIS (North Texas)](#mls-ntreis) | Client | Data licence | 8 wk | S10 (12 Apr 2027) | Not started |
| 18 Dec 2026 † | [SABOR MLS (San Antonio)](#mls-sabor) | Client | Data licence | 8 wk | S10 (12 Apr 2027) | Not started |
| 18 Dec 2026 † | [Unlock MLS / ACTRIS (Austin)](#mls-unlock) | Client | Data licence | 6 wk | S10 (12 Apr 2027) | Not started |
| 18 Dec 2026 † | [TREC CE completion reporting](#trec-ce-reporting) | Nelle | Agreement | 6 wk | S11 (26 Apr 2027) | Not started |
| 18 Dec 2026 † | [Texas REALTORS® single sign-on and member data (NRDS)](#texas-realtors-membership) | Client | Agreement | 16 wk | S18 (2 Aug 2027) | Not started |
| 18 Dec 2026 † | [TREC approval of REOS-delivered courses](#trec-course-approvals) | Nelle | Agreement | 16 wk | S19 (16 Aug 2027) | Not started |
| 28 Dec 2026 | [Microsoft 365 (Outlook calendar and mail)](#microsoft-365) | Client + Mayura | Vendor review | 3 wk | S4 (18 Jan 2027) | Not started |
| 11 Jan 2027 | [Amazon Transcribe and Polly (speech)](#aws-speech) | Client | Self-serve | 1 wk | S4 (18 Jan 2027) | Not started |
| 11 Jan 2027 | [Authorize.net](#authorize-net) | Client | Partner application | 3 wk | S5 (1 Feb 2027) | Not started |
| 18 Jan 2027 | [PayPal](#paypal) | Client | Self-serve | 2 wk | S5 (1 Feb 2027) | Not started |
| 18 Jan 2027 | [Stripe Connect (payouts)](#stripe-connect) | Client | Self-serve | 2 wk | S5 (1 Feb 2027) | Not started |
| 18 Jan 2027 | [Twilio SMS](#twilio-sms) | Client | Vendor review | 4 wk | S6 (15 Feb 2027) | Not started |
| 25 Jan 2027 | [Mux Video](#mux) | Mayura | Self-serve | 1 wk | S5 (1 Feb 2027) | Not started |
| 25 Jan 2027 | [Stripe (Checkout, Billing, Tax)](#stripe) | Client | Self-serve | 1 wk | S5 (1 Feb 2027) | Not started |
| 8 Feb 2027 | [Amazon SES (email)](#amazon-ses) | Client | Vendor review | 1 wk | S6 (15 Feb 2027) | Not started |
| 8 Feb 2027 | [Expo push notifications (APNs, FCM)](#expo-push) | Mayura | Self-serve | 1 wk | S6 (15 Feb 2027) | Not started |
| 8 Feb 2027 | [LiveKit Cloud](#livekit) | Mayura | Self-serve | 1 wk | S6 (15 Feb 2027) | Not started |
| 8 Feb 2027 | [Synthesia (AI avatar lessons; HeyGen fallback)](#synthesia) | Client | Agreement | 3 wk | S7 (1 Mar 2027) | Not started |
| 22 Feb 2027 | [Claude on Amazon Bedrock](#aws-bedrock-claude) | Client | Self-serve | 1 wk | S7 (1 Mar 2027) | Not started |
| 1 Mar 2027 | [LinkedIn](#linkedin) | Client + Mayura | Partner application | 8 wk | S11 (26 Apr 2027) | Not started |
| 15 Mar 2027 | [NMLS Consumer Access](#nmls-lookup) | Mayura | Agreement | 4 wk | S10 (12 Apr 2027) | Not started |
| 15 Mar 2027 | [Instagram and Facebook](#meta) | Client + Mayura | Vendor review | 6 wk | S11 (26 Apr 2027) | Not started |
| 15 Mar 2027 | [TikTok](#tiktok) | Client + Mayura | Vendor review | 6 wk | S11 (26 Apr 2027) | Not started |
| 15 Mar 2027 | [YouTube](#youtube) | Client + Mayura | OAuth verification | 6 wk | S11 (26 Apr 2027) | Not started |
| 29 Mar 2027 | [Texas Department of Insurance licence lookup](#tdi-lookup) | Mayura | Self-serve | 2 wk | S10 (12 Apr 2027) | Not started |
| 29 Mar 2027 | [Google Business Profile](#google-business-profile) | Client + Mayura | Partner application | 4 wk | S11 (26 Apr 2027) | Not started |
| 5 Apr 2027 | [Amazon Textract](#aws-textract) | Client | Self-serve | 1 wk | S10 (12 Apr 2027) | Not started |
| 5 Apr 2027 | [TREC licence lookup](#trec-licence) | Mayura | Self-serve | 1 wk | S10 (12 Apr 2027) | Not started |
| 10 May 2027 | [RESPA counsel review](#respa-review) | Client | Agreement | 6 wk | S15 (21 Jun 2027) | Not started |
| 14 Jun 2027 | [Mapbox](#mapbox) | Mayura | Self-serve | 1 wk | S15 (21 Jun 2027) | Not started |
| 28 Jun 2027 | [QuickBooks Online](#quickbooks-online) | Client + Mayura | Vendor review | 3 wk | S17 (19 Jul 2027) | Not started |

## Catalogue at the pilot

What users see in **Connections** on day one. Every row is also a back-office catalogue entry that can be paused, hidden per tenant, or rolled out by plan.

### Calendar and email

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Google Calendar and Gmail](#google-workspace) | Enabled | B4.1 · S4 | REOS app keys | Yes | Not started |
| [Microsoft 365 (Outlook calendar and mail)](#microsoft-365) | Enabled | B4.2 · S4 | REOS app keys | Yes | Not started |

### Payments

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Stripe (Checkout, Billing, Tax)](#stripe) | Enabled | B5.1 · S5 | REOS app keys | Yes | Not started |
| [Stripe Connect (payouts)](#stripe-connect) | Enabled | B5.2 · S5 | REOS or customer | Yes | Not started |
| [PayPal](#paypal) | Enabled | B5.2 · S5 | REOS app keys | Yes | Not started |
| [Authorize.net](#authorize-net) | Enabled | B5.2 · S5 | REOS or customer | Yes | Not started |

### CRM

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Follow Up Boss](#follow-up-boss) | Enabled | B7.2 · S7 | Customer's own account | Yes | Not started |
| [kvCORE / BoldTrail](#boldtrail) | Enabled | B7.3 · S7 | Customer's own account | Yes | Not started |
| [Lofty](#lofty) | Enabled | B8.1 · S8 | Customer's own account | Yes | Not started |
| [Salesforce](#salesforce) | Enabled | B8.2 · S8 | Customer's own account | Yes | Not started |

### Transactions

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Lone Wolf Transactions](#lone-wolf) | Enabled | B9.1 · S9 | REOS or customer | Yes | Not started |
| [dotloop](#dotloop) | Enabled | B9.2 · S9 | Customer's own account | Yes | Not started |
| [SkySlope](#skyslope) | Enabled | B9.2 · S9 | Customer's own account | Yes | Not started |

### E-signature

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [DocuSign](#docusign) | Enabled | B9.3 · S9 | Customer's own account | Yes | Not started |

### MLS data

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [HAR MLS (Houston)](#mls-har) | Enabled | B10.1 · S10 | REOS app keys | No | Not started |
| [NTREIS (North Texas)](#mls-ntreis) | Enabled | B10.1 · S10 | REOS app keys | No | Not started |
| [Unlock MLS / ACTRIS (Austin)](#mls-unlock) | Enabled | B10.1 · S10 | REOS app keys | No | Not started |
| [SABOR MLS (San Antonio)](#mls-sabor) | Enabled | B10.1 · S10 | REOS app keys | No | Not started |

### Lookups and reporting

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [TREC licence lookup](#trec-licence) | Enabled | B10.3 · S10 | None | Yes | Not started |
| [TREC CE completion reporting](#trec-ce-reporting) | Enabled | A11.3 · S11 | Customer's own account | Yes | Not started |
| [NMLS Consumer Access](#nmls-lookup) | Enabled | B10.3 · S10 | None | Yes | Not started |
| [Texas Department of Insurance licence lookup](#tdi-lookup) | Enabled | B10.3 · S10 | None | Yes | Not started |
| [Pearson VUE (Texas exam booking)](#pearson-vue) | Enabled | A10.3 · S10 | None | No | Not needed |

### Social

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Instagram and Facebook](#meta) | Enabled | B11.1 · S11 | REOS app keys | Yes | Not started |
| [LinkedIn](#linkedin) | Beta | B11.2 · S11 | REOS app keys | Yes | Not started |
| [Google Business Profile](#google-business-profile) | Enabled | B11.2 · S11 | REOS app keys | Yes | Not started |
| [YouTube](#youtube) | Enabled | B11.3 · S11 | REOS app keys | Yes | Not started |
| [TikTok](#tiktok) | Enabled | B11.3 · S11 | REOS app keys | Yes | Not started |

### Accounting

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [QuickBooks Online](#quickbooks-online) | Enabled | B17.3 · S17 | Customer's own account | Yes | Not started |

### Association

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Texas REALTORS® single sign-on and member data (NRDS)](#texas-realtors-membership) | Enabled | A18.1 · S18 | Customer's own account | Yes | Not started |

## Platform services

Services REOS runs on. Users never connect these themselves, so they have no catalogue entry.

| Item | Purpose | Owner | Needed by | Status |
| --- | --- | --- | --- | --- |
| [Amazon Transcribe and Polly (speech)](#aws-speech) | Voice to-dos and notes (speech to text) and audio-only lessons (text to speech). | Client | S4 (18 Jan 2027) | Not started |
| [Expo push notifications (APNs, FCM)](#expo-push) | Push notifications to the iOS and Android apps. | Mayura | S6 (15 Feb 2027) | Not started |
| [Amazon SES (email)](#amazon-ses) | Transactional email, digests and the morning brief. | Client | S6 (15 Feb 2027) | Not started |
| [Twilio SMS](#twilio-sms) | SMS alerts and codes, with quiet hours. | Client | S6 (15 Feb 2027) | Not started |
| [LiveKit Cloud](#livekit) | Mentor sessions and live classes (rooms, whiteboard, consented recording). | Mayura | S6 (15 Feb 2027) | Not started |
| [Mux Video](#mux) | Lesson video streaming and offline download. | Mayura | S5 (1 Feb 2027) | Not started |
| [Synthesia (AI avatar lessons; HeyGen fallback)](#synthesia) | Render AI avatar lesson videos from approved scripts. | Client | S7 (1 Mar 2027) | Not started |
| [Claude on Amazon Bedrock](#aws-bedrock-claude) | AI answers with sources, course drafting, contract review, role-play. | Client | S7 (1 Mar 2027) | Not started |
| [Amazon Textract](#aws-textract) | Read uploaded CE certificates and licences. | Client | S10 (12 Apr 2027) | Not started |
| [Mapbox](#mapbox) | Provider service areas on a map. | Mayura | S15 (21 Jun 2027) | Not started |
| [Sentry](#sentry) | Error tracking for web, API and mobile. | Mayura | S2 (21 Dec 2026) | Not started |
| [PostHog](#posthog) | Product analytics, funnels and session insights. | Mayura | S2 (21 Dec 2026) | Not started |
| [OpenTelemetry and Grafana](#grafana-otel) | Tracing, metrics and dashboards. | Mayura | S2 (21 Dec 2026) | Not started |

## Vendor accounts

Accounts the build and release process needs.

| Item | Purpose | Owner | Needed by | Status |
| --- | --- | --- | --- | --- |
| [GitHub repository and Actions](#github) | Source code, pull requests and CI. | Client | S1 (7 Dec 2026) | Approved |
| [Product domain](#product-domain) | Web app, API, email sender and tenant custom domains. | Client | S2 (21 Dec 2026) | Not started |
| [AWS organisation](#aws-organisation) | Staging and production cells in us-east-2, recovery in us-west-2. | Client | S2 (21 Dec 2026) | Not started |
| [Cloudflare](#cloudflare) | DNS, WAF and custom domains for tenants (Cloudflare for SaaS). | Client | S2 (21 Dec 2026) | Not started |
| [Apple Developer Program (organisation)](#apple-developer) | TestFlight and App Store releases; push notification key. | Client | S2 (21 Dec 2026) | Not started |
| [Google Play Console (organisation)](#google-play) | Android internal testing and Play Store releases. | Client | S2 (21 Dec 2026) | Not started |
| [Expo organisation (EAS)](#expo) | Mobile builds, store submission and over-the-air updates. | Mayura | S2 (21 Dec 2026) | Not started |

## Outside approvals

Regulatory and legal sign-offs with fixed lead times.

| Item | Purpose | Owner | Needed by | Status |
| --- | --- | --- | --- | --- |
| [TREC approval of REOS-delivered courses](#trec-course-approvals) | Courses count for qualifying education and CE only once TREC approves them, including AI avatar delivery. | Nelle | S19 (16 Aug 2027) | Not started |
| [RESPA counsel review](#respa-review) | Confirm provider plans and rewards are RESPA-safe before the provider marketplace is built. | Client | S15 (21 Jun 2027) | Not started |

## Details

### Google Calendar and Gmail
<a id="google-workspace"></a>

**Integration** · Calendar and email · built in B4.1 (sprint 4) · pilot status **Enabled**

Two-way calendar and email for every role.

- **Owner:** Client + Mayura
- **Access:** OAuth verification, about 10 weeks; needed by S4 (18 Jan 2027); apply by 9 Nov 2026. Status: Not started.
- **Sandbox:** Test users on an unverified Google Cloud project (up to 100) until verification is approved.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API. Reading the inbox (restricted Gmail scopes) needs a yearly CASA security assessment by a Google-approved assessor, a paid third-party service.
- **Notes:** Calendar and sending mail (gmail.send) need standard OAuth verification only. Reading or labelling the inbox (gmail.readonly, gmail.modify) is a restricted scope: Google requires a CASA Tier 2 assessment, renewed every 12 months, which takes several weeks. Recommendation: launch with calendar + send + a forwarding address for inbound mail, and file for restricted scopes in parallel.
- **Fallback:** Forward-to-REOS address for inbound mail; ICS calendar feed.
- **Sources:** [1](https://developers.google.com/identity/protocols/oauth2/production-readiness/restricted-scope-verification) · [2](https://developer.nylas.com/docs/cookbook/use-cases/build/google-oauth-scopes/)

### Microsoft 365 (Outlook calendar and mail)
<a id="microsoft-365"></a>

**Integration** · Calendar and email · built in B4.2 (sprint 4) · pilot status **Enabled**

Two-way Outlook calendar and mail.

- **Owner:** Client + Mayura
- **Access:** Vendor review, about 3 weeks; needed by S4 (18 Jan 2027); apply by 28 Dec 2026. Status: Not started.
- **Sandbox:** Microsoft 365 Developer Program tenant.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** Microsoft Entra multi-tenant app with publisher verification (needs a Microsoft Partner Network ID), so customers' admins see a verified publisher when consenting.
- **Sources:** [1](https://learn.microsoft.com/en-us/entra/identity-platform/publisher-verification-overview)

### Amazon Transcribe and Polly (speech)
<a id="aws-speech"></a>

**Platform service** · Voice · built in B4.3 (sprint 4)

Voice to-dos and notes (speech to text) and audio-only lessons (text to speech).

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S4 (18 Jan 2027); apply by 11 Jan 2027. Status: Not started.
- **Sandbox:** Same AWS account (staging).
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Pay per minute / per character.

### Stripe (Checkout, Billing, Tax)
<a id="stripe"></a>

**Integration** · Payments · built in B5.1 (sprint 5) · pilot status **Enabled**

Web checkout, subscriptions, invoices and Texas sales tax.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S5 (1 Feb 2027); apply by 25 Jan 2027. Status: Not started.
- **Sandbox:** Stripe test mode.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per-transaction fees; Stripe Tax and Billing add a percentage.
- **Notes:** Account must be in the client's Texas entity. Card data never touches REOS (Stripe Checkout).

### Stripe Connect (payouts)
<a id="stripe-connect"></a>

**Integration** · Payments · built in B5.2 (sprint 5) · pilot status **Enabled**

Payouts to schools, instructors and mentors; tenants can connect their own Stripe account.

- **Owner:** Client
- **Access:** Self-serve, about 2 weeks; needed by S5 (1 Feb 2027); apply by 18 Jan 2027. Status: Not started.
- **Sandbox:** Stripe test mode with test connected accounts.
- **Credentials:** REOS or customer · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per-payout and per-active-account fees.
- **Notes:** Platform profile and connected-account onboarding review by Stripe before live payouts.

### PayPal
<a id="paypal"></a>

**Integration** · Payments · built in B5.2 (sprint 5) · pilot status **Enabled**

Alternative web checkout, switchable per state and product.

- **Owner:** Client
- **Access:** Self-serve, about 2 weeks; needed by S5 (1 Feb 2027); apply by 18 Jan 2027. Status: Not started.
- **Sandbox:** PayPal developer sandbox.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per-transaction fees.
- **Notes:** PayPal Business account; advanced card processing needs PayPal's approval.

### Authorize.net
<a id="authorize-net"></a>

**Integration** · Payments · built in B5.2 (sprint 5) · pilot status **Enabled**

Gateway for associations and brokerages that already bank through Authorize.net.

- **Owner:** Client
- **Access:** Partner application, about 3 weeks; needed by S5 (1 Feb 2027); apply by 11 Jan 2027. Status: Not started.
- **Sandbox:** Authorize.net sandbox account (free, instant).
- **Credentials:** REOS or customer · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Monthly gateway fee plus per-transaction fees; needs a merchant account.
- **Notes:** Use Accept Hosted so card data never touches REOS.

### Expo push notifications (APNs, FCM)
<a id="expo-push"></a>

**Platform service** · Notifications · built in B6.2 (sprint 6)

Push notifications to the iOS and Android apps.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S6 (15 Feb 2027); apply by 8 Feb 2027. Status: Not started.
- **Sandbox:** Development builds.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Included with Expo.
- **Notes:** Needs the Apple push key and Firebase project from the store accounts.

### Amazon SES (email)
<a id="amazon-ses"></a>

**Platform service** · Notifications · built in B6.2 (sprint 6)

Transactional email, digests and the morning brief.

- **Owner:** Client
- **Access:** Vendor review, about 1 week; needed by S6 (15 Feb 2027); apply by 8 Feb 2027. Status: Not started.
- **Sandbox:** SES sandbox (verified recipients only).
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per 1,000 emails.
- **Notes:** Request production access; set up SPF, DKIM and DMARC on the product domain.

### Twilio SMS
<a id="twilio-sms"></a>

**Platform service** · Notifications · built in B6.2 (sprint 6)

SMS alerts and codes, with quiet hours.

- **Owner:** Client
- **Access:** Vendor review, about 4 weeks; needed by S6 (15 Feb 2027); apply by 18 Jan 2027. Status: Not started.
- **Sandbox:** Twilio test credentials and a trial number.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per message plus one-off and monthly 10DLC registration fees.
- **Notes:** US A2P 10DLC brand registration (up to about a week) and campaign vetting (8–10 business days per submission; a rejection restarts it). Needs a live privacy policy and terms on the product domain first.
- **Sources:** [1](https://help.justcall.io/en/articles/15516614-what-are-the-timelines-and-processes-for-10dlc-approval)

### LiveKit Cloud
<a id="livekit"></a>

**Platform service** · Video · built in B6.3 (sprint 6)

Mentor sessions and live classes (rooms, whiteboard, consented recording).

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S6 (15 Feb 2027); apply by 8 Feb 2027. Status: Not started.
- **Sandbox:** Free tier project.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per participant-minute.

### Mux Video
<a id="mux"></a>

**Platform service** · Video · built in A5.3 (sprint 5)

Lesson video streaming and offline download.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S5 (1 Feb 2027); apply by 25 Jan 2027. Status: Not started.
- **Sandbox:** Mux test environment.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Per minute encoded, stored and delivered.

### Synthesia (AI avatar lessons; HeyGen fallback)
<a id="synthesia"></a>

**Platform service** · AI · built in A7.3 (sprint 7)

Render AI avatar lesson videos from approved scripts.

- **Owner:** Client
- **Access:** Agreement, about 3 weeks; needed by S7 (1 Mar 2027); apply by 8 Feb 2027. Status: Not started.
- **Sandbox:** Trial workspace; API needs an Enterprise plan.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Enterprise plan (quote).
- **Notes:** Instructor avatar consent template must be signed before any instructor likeness is used.
- **Fallback:** HeyGen API; human-recorded video if TREC approval of avatar courses is delayed.

### Claude on Amazon Bedrock
<a id="aws-bedrock-claude"></a>

**Platform service** · AI · built in A7.3 (sprint 7)

AI answers with sources, course drafting, contract review, role-play.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S7 (1 Mar 2027); apply by 22 Feb 2027. Status: Not started.
- **Sandbox:** Staging AWS account, us-east-2.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per token.
- **Notes:** Request model access and quota increases in us-east-2 (and us-west-2 for recovery). No training on customer data.

### Amazon Textract
<a id="aws-textract"></a>

**Platform service** · AI · built in B10.3 (sprint 10)

Read uploaded CE certificates and licences.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S10 (12 Apr 2027); apply by 5 Apr 2027. Status: Not started.
- **Sandbox:** Staging AWS account.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per page.

### Follow Up Boss
<a id="follow-up-boss"></a>

**Integration** · CRM · built in B7.2 (sprint 7) · pilot status **Enabled**

Two-way contacts, notes and tasks; newest change wins.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 4 weeks; needed by S7 (1 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Free trial account; register a system key.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API; agents use their own Follow Up Boss plan.
- **Fallback:** CSV import; email forwarding.

### kvCORE / BoldTrail
<a id="boldtrail"></a>

**Integration** · CRM · built in B7.3 (sprint 7) · pilot status **Enabled**

Two-way contacts, notes and tasks.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 8 weeks; needed by S7 (1 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Fallback:** CSV import.

### Lofty
<a id="lofty"></a>

**Integration** · CRM · built in B8.1 (sprint 8) · pilot status **Enabled**

Two-way contacts, notes and tasks.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 6 weeks; needed by S8 (15 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Fallback:** CSV import.

### Salesforce
<a id="salesforce"></a>

**Integration** · CRM · built in B8.2 (sprint 8) · pilot status **Enabled**

Contacts, leads, tasks and activities for brokerages on Salesforce.

- **Owner:** Mayura
- **Access:** Self-serve, about 2 weeks; needed by S8 (15 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Free Developer Edition org.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free to connect; AppExchange listing (optional, later) needs a paid security review.

### Lone Wolf Transactions
<a id="lone-wolf"></a>

**Integration** · Transactions · built in B9.1 (sprint 9) · pilot status **Enabled**

Deals, documents and deadlines; deadline alerts; coordinator sharing.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 10 weeks; needed by S9 (29 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** REOS or customer · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Fallback:** Email forwarding of deal documents.

### dotloop
<a id="dotloop"></a>

**Integration** · Transactions · built in B9.2 (sprint 9) · pilot status **Enabled**

Loops, documents and deadlines.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 6 weeks; needed by S9 (29 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.

### SkySlope
<a id="skyslope"></a>

**Integration** · Transactions · built in B9.2 (sprint 9) · pilot status **Enabled**

Files, documents and deadlines.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 6 weeks; needed by S9 (29 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.

### DocuSign
<a id="docusign"></a>

**Integration** · E-signature · built in B9.3 (sprint 9) · pilot status **Enabled**

Send, track and store signatures from REOS.

- **Owner:** Mayura
- **Access:** Vendor review, about 2 weeks; needed by S9 (29 Mar 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Free developer (demo) account.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free to integrate; signers use the customer's DocuSign plan.
- **Notes:** Production go-live review after enough successful calls in the demo environment.

### HAR MLS (Houston)
<a id="mls-har"></a>

**Integration** · MLS data · built in B10.1 (sprint 10) · pilot status **Enabled**

Agents' listings for posts, CMAs, public pages and open houses.

- **Owner:** Client
- **Access:** Data licence, about 8 weeks; needed by S10 (12 Apr 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Bridge test dataset.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** MLS licence fees per feed type (IDX, VOW, back office) plus Bridge terms.
- **Notes:** Delivered through the Bridge API (dataset `har`). Needs a licensed broker of record as sponsor. HAR and SABOR share listings with each other's subscribers (since May 2023).
- **Sources:** [1](https://www.reso.org/web-api-examples/mls/houston-assoc-of-realtors) · [2](https://sabor.com/News-Events-and-Education/News/SABOR-News/SABOR-and-HAR-Datashare)

### NTREIS (North Texas)
<a id="mls-ntreis"></a>

**Integration** · MLS data · built in B10.1 (sprint 10) · pilot status **Enabled**

Agents' listings for posts, CMAs, public pages and open houses.

- **Owner:** Client
- **Access:** Data licence, about 8 weeks; needed by S10 (12 Apr 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Trestle test feed.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** MLS licence fees plus Trestle terms.
- **Notes:** NTREIS presents Trestle (Cotality) as its Web API feed. Needs a broker sponsor.
- **Sources:** [1](https://ntreis.net/?p=10255)

### Unlock MLS / ACTRIS (Austin)
<a id="mls-unlock"></a>

**Integration** · MLS data · built in B10.1 (sprint 10) · pilot status **Enabled**

Agents' listings for posts, CMAs, public pages and open houses.

- **Owner:** Client
- **Access:** Data licence, about 6 weeks; needed by S10 (12 Apr 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** On approval.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Pricing given by MLS Grid after registration.
- **Notes:** Register with MLS Grid first (Unlock MLS's admin tool), with at least one active Unlock subscriber signed up; then deliver through MLS Grid, Trestle or Bridge. Contact mlsdata@unlockmls.com.
- **Sources:** [1](https://www.unlockmls.com/data-licensing)

### SABOR MLS (San Antonio)
<a id="mls-sabor"></a>

**Integration** · MLS data · built in B10.1 (sprint 10) · pilot status **Enabled**

Agents' listings for posts, CMAs, public pages and open houses.

- **Owner:** Client
- **Access:** Data licence, about 8 weeks; needed by S10 (12 Apr 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** To confirm with SABOR.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** SABOR IDX data feed fee plus vendor terms.
- **Notes:** Delivery vendor not published; confirm with SABOR's MLS department (SABOR runs connectMLS and publishes a third-party vendor agreement).
- **Sources:** [1](https://SABOR.COM/-/media/Project/SABOR/SABOR/SABOR/PDFs/MLS/3RD-PARTY-VENDOR.pdf)

### TREC licence lookup
<a id="trec-licence"></a>

**Integration** · Lookups and reporting · built in B10.3 (sprint 10) · pilot status **Enabled**

Verify agent, broker and inspector licences in seconds.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S10 (12 Apr 2027); apply by 5 Apr 2027. Status: Not started.
- **Sandbox:** Public data.
- **Credentials:** None · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free public data.
- **Notes:** Use TREC's published licence-holder data files rather than scraping the search pages.

### TREC CE completion reporting
<a id="trec-ce-reporting"></a>

**Integration** · Lookups and reporting · built in A11.3 (sprint 11) · pilot status **Enabled**

Report CE completions to TREC (by the school, or REOS on its behalf).

- **Owner:** Nelle
- **Access:** Agreement, about 6 weeks; needed by S11 (26 Apr 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** None; test with TREC's guidance before go-live.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per TREC's provider rules.
- **Notes:** Open question in the PRD (school vs REOS submits). Needs the school's education-provider account.

### NMLS Consumer Access
<a id="nmls-lookup"></a>

**Integration** · Lookups and reporting · built in B10.3 (sprint 10) · pilot status **Enabled**

Verify mortgage loan officer licences for service providers.

- **Owner:** Mayura
- **Access:** Agreement, about 4 weeks; needed by S10 (12 Apr 2027); apply by 15 Mar 2027. Status: Not started.
- **Sandbox:** Public lookup site.
- **Credentials:** None · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** To confirm.
- **Notes:** Confirm an allowed automated route with NMLS before building; the public lookup's terms may restrict automated queries.
- **Fallback:** Manual verification by REOS staff with a screenshot on file.

### Texas Department of Insurance licence lookup
<a id="tdi-lookup"></a>

**Integration** · Lookups and reporting · built in B10.3 (sprint 10) · pilot status **Enabled**

Verify insurance agent licences for service providers.

- **Owner:** Mayura
- **Access:** Self-serve, about 2 weeks; needed by S10 (12 Apr 2027); apply by 29 Mar 2027. Status: Not started.
- **Sandbox:** Public data.
- **Credentials:** None · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free public data (to confirm).
- **Fallback:** Manual verification.

### Pearson VUE (Texas exam booking)
<a id="pearson-vue"></a>

**Integration** · Lookups and reporting · built in A10.3 (sprint 10) · pilot status **Enabled**

Hand-off link to book the Texas licence exam; no API.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S10 (12 Apr 2027); apply by 5 Apr 2027. Status: Not needed.
- **Sandbox:** Not needed.
- **Credentials:** None · **Personal data:** no
- **Cost:** None.

### Instagram and Facebook
<a id="meta"></a>

**Integration** · Social · built in B11.1 (sprint 11) · pilot status **Enabled**

Post, schedule and read insights.

- **Owner:** Client + Mayura
- **Access:** Vendor review, about 6 weeks; needed by S11 (26 Apr 2027); apply by 15 Mar 2027. Status: Not started.
- **Sandbox:** Development-mode app with test users.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** Business verification of the client's company, then app review for each publishing permission with screencasts.

### LinkedIn
<a id="linkedin"></a>

**Integration** · Social · built in B11.2 (sprint 11) · pilot status **Beta**

Post to profiles and company pages; insights.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 8 weeks; needed by S11 (26 Apr 2027); apply by 1 Mar 2027. Status: Not started.
- **Sandbox:** Development app.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** Posting as a member is self-serve; company pages and analytics need Community Management API approval. Launches as beta per the PRD.

### Google Business Profile
<a id="google-business-profile"></a>

**Integration** · Social · built in B11.2 (sprint 11) · pilot status **Enabled**

Posts and insights on the agent's Google listing.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 4 weeks; needed by S11 (26 Apr 2027); apply by 29 Mar 2027. Status: Not started.
- **Sandbox:** Same Google Cloud project as Google Workspace.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** API access request form; covered by the same OAuth verification as Google Workspace.

### YouTube
<a id="youtube"></a>

**Integration** · Social · built in B11.3 (sprint 11) · pilot status **Enabled**

Short video posting and insights.

- **Owner:** Client + Mayura
- **Access:** OAuth verification, about 6 weeks; needed by S11 (26 Apr 2027); apply by 15 Mar 2027. Status: Not started.
- **Sandbox:** Same Google Cloud project; unverified apps upload as private.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API; request a quota increase for scale.
- **Notes:** Uploads stay private until the API audit is passed.

### TikTok
<a id="tiktok"></a>

**Integration** · Social · built in B11.3 (sprint 11) · pilot status **Enabled**

Short video posting and insights.

- **Owner:** Client + Mayura
- **Access:** Vendor review, about 6 weeks; needed by S11 (26 Apr 2027); apply by 15 Mar 2027. Status: Not started.
- **Sandbox:** Sandbox app; unaudited apps can only post privately.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** Content Posting API audit before public posting.

### Mapbox
<a id="mapbox"></a>

**Platform service** · Maps · built in B15.2 (sprint 15)

Provider service areas on a map.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S15 (21 Jun 2027); apply by 14 Jun 2027. Status: Not started.
- **Sandbox:** Free tier.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Free tier, then per map load.

### QuickBooks Online
<a id="quickbooks-online"></a>

**Integration** · Accounting · built in B17.3 (sprint 17) · pilot status **Enabled**

Export split sheets and payouts for brokerages.

- **Owner:** Client + Mayura
- **Access:** Vendor review, about 3 weeks; needed by S17 (19 Jul 2027); apply by 28 Jun 2027. Status: Not started.
- **Sandbox:** Intuit developer sandbox company.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** Production keys need Intuit's app assessment questionnaire.

### Texas REALTORS® single sign-on and member data (NRDS)
<a id="texas-realtors-membership"></a>

**Integration** · Association · built in A18.1 (sprint 18) · pilot status **Enabled**

Members sign in with their association login; member records, CE and programs stay in sync.

- **Owner:** Client
- **Access:** Agreement, about 16 weeks; needed by S18 (2 Aug 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Ask for a test environment of the membership system.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Contract.
- **Notes:** Open PRD question which membership system and fields. Data-sharing agreement and security review usually precede access; start the conversation now.

### Sentry
<a id="sentry"></a>

**Platform service** · Operations · built in 2.3 (sprint 2)

Error tracking for web, API and mobile.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S2 (21 Dec 2026); apply by 14 Dec 2026. Status: Not started.
- **Sandbox:** Separate staging project.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Team plan.
- **Notes:** Scrub personal data before sending; US data region.

### PostHog
<a id="posthog"></a>

**Platform service** · Operations · built in 2.4 (sprint 2)

Product analytics, funnels and session insights.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S2 (21 Dec 2026); apply by 14 Dec 2026. Status: Not started.
- **Sandbox:** Separate staging project.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Usage-based; generous free tier.
- **Notes:** US cloud; respect consent and Texas privacy law (TDPSA).

### OpenTelemetry and Grafana
<a id="grafana-otel"></a>

**Platform service** · Operations · built in 2.4 (sprint 2)

Tracing, metrics and dashboards.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S2 (21 Dec 2026); apply by 14 Dec 2026. Status: Not started.
- **Sandbox:** Staging stack.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Grafana Cloud free tier or self-hosted.

### GitHub repository and Actions
<a id="github"></a>

**Vendor account** · Platform · built in 1.1 (sprint 1)

Source code, pull requests and CI.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S1 (7 Dec 2026); apply by 30 Nov 2026. Status: Approved, applied 2026-11-19, approved 2026-11-20.
- **Sandbox:** Not needed.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Free; GitHub Pro or Team needed for rulesets to be enforced once the repository is private.
- **Notes:** Repository srinivas182/reos-mcs with CI, Lefthook, Renovate and a `main` ruleset.

### Product domain
<a id="product-domain"></a>

**Vendor account** · Platform · built in 2.1 (sprint 2)

Web app, API, email sender and tenant custom domains.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S2 (21 Dec 2026); apply by 14 Dec 2026. Status: Not started.
- **Sandbox:** Not needed.
- **Credentials:** None · **Personal data:** no
- **Cost:** Domain registration.
- **Notes:** Decide the name (for example reos.com); SES, Twilio 10DLC and Meta verification all need it live with a privacy policy.

### AWS organisation
<a id="aws-organisation"></a>

**Vendor account** · Platform · built in 2.1 (sprint 2)

Staging and production cells in us-east-2, recovery in us-west-2.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S2 (21 Dec 2026); apply by 14 Dec 2026. Status: Not started.
- **Sandbox:** Separate staging account.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Usage.
- **Notes:** Billing in the client's name; Mayura gets admin roles through IAM Identity Center, never root keys.

### Cloudflare
<a id="cloudflare"></a>

**Vendor account** · Platform · built in 2.1 (sprint 2)

DNS, WAF and custom domains for tenants (Cloudflare for SaaS).

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S2 (21 Dec 2026); apply by 14 Dec 2026. Status: Not started.
- **Sandbox:** Staging zone.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Pro or Business plan plus Cloudflare for SaaS per custom hostname.

### Apple Developer Program (organisation)
<a id="apple-developer"></a>

**Vendor account** · Platform · built in 2.3 (sprint 2)

TestFlight and App Store releases; push notification key.

- **Owner:** Client
- **Access:** Vendor review, about 4 weeks; needed by S2 (21 Dec 2026); apply by 23 Nov 2026. Status: Not started.
- **Sandbox:** Not needed.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Yearly membership fee.
- **Notes:** Organisation enrolment needs a D-U-N-S number for the client's legal entity; request it first.

### Google Play Console (organisation)
<a id="google-play"></a>

**Vendor account** · Platform · built in 2.3 (sprint 2)

Android internal testing and Play Store releases.

- **Owner:** Client
- **Access:** Vendor review, about 2 weeks; needed by S2 (21 Dec 2026); apply by 7 Dec 2026. Status: Not started.
- **Sandbox:** Not needed.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** One-off registration fee.
- **Notes:** Organisation identity verification (D-U-N-S also used here).

### Expo organisation (EAS)
<a id="expo"></a>

**Vendor account** · Platform · built in 2.3 (sprint 2)

Mobile builds, store submission and over-the-air updates.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S2 (21 Dec 2026); apply by 14 Dec 2026. Status: Not started.
- **Sandbox:** Not needed.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** EAS Production plan.

### TREC approval of REOS-delivered courses
<a id="trec-course-approvals"></a>

**Outside approval** · Regulatory · built in A7.1 (sprint 7)

Courses count for qualifying education and CE only once TREC approves them, including AI avatar delivery.

- **Owner:** Nelle
- **Access:** Agreement, about 16 weeks; needed by S19 (16 Aug 2027); apply by 18 Dec 2026. Status: Not started.
- **Sandbox:** Not applicable.
- **Credentials:** None · **Personal data:** no
- **Cost:** TREC course filing fees.
- **Notes:** Biggest schedule risk in the PRD; start with Nelle's existing approved courses in phase 0.
- **Fallback:** Launch with human-recorded video for approved courses.

### RESPA counsel review
<a id="respa-review"></a>

**Outside approval** · Regulatory · built in B15.2 (sprint 15)

Confirm provider plans and rewards are RESPA-safe before the provider marketplace is built.

- **Owner:** Client
- **Access:** Agreement, about 6 weeks; needed by S15 (21 Jun 2027); apply by 10 May 2027. Status: Not started.
- **Sandbox:** Not applicable.
- **Credentials:** None · **Personal data:** no
- **Cost:** Legal fees.
