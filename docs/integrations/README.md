<!-- Generated from register.yaml by scripts/integrations-register.mjs. Do not edit; run `pnpm integrations:render`. -->

# Integrations register

Every integration, platform service, vendor account and outside approval REOS needs for the Texas pilot on **14 Sep 2027**, with who owns it, how we get access, and when it must be filed. Source: [`register.yaml`](register.yaml) (schema: [`register.schema.json`](register.schema.json)). Updated 21 Nov 2026.

Credentials rules: [credentials policy](credentials-policy.md). Lead times are planning estimates until the vendor confirms.

## At a glance

|  | Count |
| --- | --- |
| Integrations users connect (catalogue) | 44 (29 enabled, 1 beta, 14 coming soon at the pilot) |
| Platform services REOS runs on | 18 |
| Vendor accounts | 7 |
| Outside approvals | 2 |
| Access approved | 1 |
| Filed and waiting | 0 |
| Blocked | 0 |
| Not started | 65 |
| Already past their apply-by date on 21 Nov 2026 | **1** |

## Action list: file in this order

"Apply by" = the start of the sprint that needs it, minus the estimated lead time, or the planned date (marked †) when the sprint plan files it earlier because the vendor's approval time is unpredictable. ⚠ marks items whose apply-by date has already passed: file them first.

| Apply by | Item | Owner | How | Lead (est.) | Needed by | Status |
| --- | --- | --- | --- | --- | --- | --- |
| ⚠ 9 Nov 2026 | [Google Calendar](#google-calendar) | Client + Mayura | OAuth verification | 10 wk | S4 (18 Jan 2027) | Not started |
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
| 21 Dec 2026 | [Zoom](#zoom) | Client + Mayura | Vendor review | 4 wk | S4 (18 Jan 2027) | Not started |
| 28 Dec 2026 | [Sign in with Google](#google-sign-in) | Client + Mayura | Self-serve | 1 wk | S3 (4 Jan 2027) | Not started |
| 28 Dec 2026 | [Twilio Verify (phone sign-in codes)](#twilio-verify) | Client | Self-serve | 1 wk | S3 (4 Jan 2027) | Not started |
| 28 Dec 2026 | [Outlook Calendar](#outlook-calendar) | Client + Mayura | Vendor review | 3 wk | S4 (18 Jan 2027) | Not started |
| 4 Jan 2027 | [Sign in with Apple](#apple-sign-in) | Client | Self-serve | 0 wk | S3 (4 Jan 2027) | Not started |
| 11 Jan 2027 | [Amazon Transcribe and Polly (speech)](#aws-speech) | Client | Self-serve | 1 wk | S4 (18 Jan 2027) | Not started |
| 11 Jan 2027 | [Claude on Amazon Bedrock](#aws-bedrock-claude) | Client | Self-serve | 1 wk | S4 (18 Jan 2027) | Not started |
| 11 Jan 2027 | [Cloudflare](#cloudflare) | Client | Self-serve | 1 wk | S4 (18 Jan 2027) | Not started |
| 11 Jan 2027 | [Cloudflare for SaaS (tenant custom domains)](#cloudflare-saas) | Client | Self-serve | 1 wk | S4 (18 Jan 2027) | Not started |
| 11 Jan 2027 | [Product domain (reos.com)](#product-domain) | Client | Self-serve | 1 wk | S4 (18 Jan 2027) | Not started |
| 11 Jan 2027 | [Authorize.net](#authorize-net) | Client | Partner application | 3 wk | S5 (1 Feb 2027) | Not started |
| 18 Jan 2027 | [PayPal](#paypal) | Client | Self-serve | 2 wk | S5 (1 Feb 2027) | Not started |
| 18 Jan 2027 | [Stripe Connect (payouts)](#stripe-connect) | Client | Self-serve | 2 wk | S5 (1 Feb 2027) | Not started |
| 18 Jan 2027 | [Twilio SMS](#twilio-sms) | Client | Vendor review | 4 wk | S6 (15 Feb 2027) | Not started |
| 25 Jan 2027 | [Amazon Titan Text Embeddings V2 on Bedrock](#aws-bedrock-embeddings) | Client | Self-serve | 1 wk | S5 (1 Feb 2027) | Not started |
| 25 Jan 2027 | [Mux Video](#mux) | Mayura | Self-serve | 1 wk | S5 (1 Feb 2027) | Not started |
| 25 Jan 2027 | [Stripe (Checkout, Billing, Tax)](#stripe) | Client | Self-serve | 1 wk | S5 (1 Feb 2027) | Not started |
| 1 Feb 2027 | [BrokerBay](#brokerbay) | Client + Mayura | Partner application | 8 wk | S9 (29 Mar 2027) | Not started |
| 1 Feb 2027 | [ShowingTime](#showingtime) | Client + Mayura | Partner application | 8 wk | S9 (29 Mar 2027) | Not started |
| 8 Feb 2027 | [Amazon SES (email)](#amazon-ses) | Client | Vendor review | 1 wk | S6 (15 Feb 2027) | Not started |
| 8 Feb 2027 | [AWS organisation](#aws-organisation) | Client | Self-serve | 1 wk | S6 (15 Feb 2027) | Not started |
| 8 Feb 2027 | [Expo push notifications (APNs, FCM)](#expo-push) | Mayura | Self-serve | 1 wk | S6 (15 Feb 2027) | Not started |
| 8 Feb 2027 | [LiveKit Cloud](#livekit) | Mayura | Self-serve | 1 wk | S6 (15 Feb 2027) | Not started |
| 8 Feb 2027 | [Synthesia (AI avatar lessons; HeyGen fallback)](#synthesia) | Client | Agreement | 3 wk | S7 (1 Mar 2027) | Not started |
| 15 Feb 2027 | [BoldTrail BackOffice](#boldtrail-backoffice) | Client + Mayura | Partner application | 6 wk | S9 (29 Mar 2027) | Not started |
| 15 Feb 2027 | [ListedKit](#listedkit) | Client + Mayura | Partner application | 6 wk | S9 (29 Mar 2027) | Not started |
| 15 Feb 2027 | [Open to Close](#open-to-close) | Client + Mayura | Partner application | 6 wk | S9 (29 Mar 2027) | Not started |
| 15 Feb 2027 | [Paperless Pipeline](#paperless-pipeline) | Client + Mayura | Partner application | 6 wk | S9 (29 Mar 2027) | Not started |
| 1 Mar 2027 | [LinkedIn](#linkedin) | Client + Mayura | Partner application | 8 wk | S11 (26 Apr 2027) | Not started |
| 15 Mar 2027 | [NMLS Consumer Access](#nmls-lookup) | Mayura | Agreement | 4 wk | S10 (12 Apr 2027) | Not started |
| 15 Mar 2027 | [Instagram and Facebook](#meta) | Client + Mayura | Vendor review | 6 wk | S11 (26 Apr 2027) | Not started |
| 15 Mar 2027 | [TikTok](#tiktok) | Client + Mayura | Vendor review | 6 wk | S11 (26 Apr 2027) | Not started |
| 15 Mar 2027 | [YouTube](#youtube) | Client + Mayura | OAuth verification | 6 wk | S11 (26 Apr 2027) | Not started |
| 29 Mar 2027 | [Texas Department of Insurance licence lookup](#tdi-lookup) | Mayura | Self-serve | 2 wk | S10 (12 Apr 2027) | Not started |
| 29 Mar 2027 | [Google Business Profile](#google-business-profile) | Client + Mayura | Partner application | 4 wk | S11 (26 Apr 2027) | Not started |
| 29 Mar 2027 | [Homes.com leads](#homes-com-leads) | Client + Mayura | Partner application | 10 wk | S14 (7 Jun 2027) | Not started |
| 29 Mar 2027 | [Realtor.com leads](#realtor-com-leads) | Client + Mayura | Partner application | 10 wk | S14 (7 Jun 2027) | Not started |
| 29 Mar 2027 | [Zillow leads](#zillow-leads) | Client + Mayura | Partner application | 10 wk | S14 (7 Jun 2027) | Not started |
| 5 Apr 2027 | [Amazon Textract](#aws-textract) | Client | Self-serve | 1 wk | S10 (12 Apr 2027) | Not started |
| 5 Apr 2027 | [TREC licence lookup](#trec-licence) | Mayura | Self-serve | 1 wk | S10 (12 Apr 2027) | Not started |
| 10 May 2027 | [RESPA counsel review](#respa-review) | Client | Agreement | 6 wk | S15 (21 Jun 2027) | Not started |
| 14 Jun 2027 | [Mapbox](#mapbox) | Mayura | Self-serve | 1 wk | S15 (21 Jun 2027) | Not started |
| 28 Jun 2027 | [QuickBooks Online](#quickbooks-online) | Client + Mayura | Vendor review | 3 wk | S17 (19 Jul 2027) | Not started |
| 19 Jul 2027 | [Apple Developer Program (organisation)](#apple-developer) | Client | Vendor review | 4 wk | S19 (16 Aug 2027) | Not started |
| 2 Aug 2027 | [Google Play Console (organisation)](#google-play) | Client | Vendor review | 2 wk | S19 (16 Aug 2027) | Not started |
| 9 Aug 2027 | [Expo organisation (EAS)](#expo) | Mayura | Self-serve | 1 wk | S19 (16 Aug 2027) | Not started |
| 9 Aug 2027 | [OpenTelemetry and Grafana](#grafana-otel) | Mayura | Self-serve | 1 wk | S19 (16 Aug 2027) | Not started |
| 9 Aug 2027 | [PostHog](#posthog) | Mayura | Self-serve | 1 wk | S19 (16 Aug 2027) | Not started |
| 9 Aug 2027 | [Sentry](#sentry) | Mayura | Self-serve | 1 wk | S19 (16 Aug 2027) | Not started |

## Catalogue at the pilot

What users see in **Connections** on day one. Every row is also a back-office catalogue entry that can be paused, hidden per tenant, or rolled out by plan.

### Calendar and email

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Google Calendar](#google-calendar) | Enabled | B4.1 · S4 | REOS app keys | Yes | Not started |
| [Outlook Calendar](#outlook-calendar) | Enabled | B4.2 · S4 | REOS app keys | Yes | Not started |
| [Apple Calendar (iCloud)](#apple-calendar) | Coming soon | B4.1 · S4 | None | Yes | Not needed |

### Calendar and meetings

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Zoom](#zoom) | Coming soon | B4.2 · S4 | REOS app keys | Yes | Not started |
| [Microsoft Teams meetings](#microsoft-teams) | Coming soon | B4.2 · S4 | None | Yes | Not needed |
| [Google Meet](#google-meet) | Coming soon | B4.2 · S4 | None | Yes | Not needed |
| [ShowingTime](#showingtime) | Coming soon | B9.2 · S9 | REOS app keys | Yes | Not started |
| [BrokerBay](#brokerbay) | Coming soon | B9.2 · S9 | REOS app keys | Yes | Not started |

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
| [Open to Close](#open-to-close) | Coming soon | B9.1 · S9 | Customer's own account | Yes | Not started |
| [Paperless Pipeline](#paperless-pipeline) | Coming soon | B9.1 · S9 | Customer's own account | Yes | Not started |
| [ListedKit](#listedkit) | Coming soon | B9.1 · S9 | Customer's own account | Yes | Not started |
| [BoldTrail BackOffice](#boldtrail-backoffice) | Coming soon | B9.1 · S9 | Customer's own account | Yes | Not started |

### E-signature

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [DocuSign](#docusign) | Enabled | B9.3 · S9 | Customer's own account | Yes | Not started |

### Leads

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Zillow leads](#zillow-leads) | Coming soon | B14.1 · S14 | REOS app keys | Yes | Not started |
| [Realtor.com leads](#realtor-com-leads) | Coming soon | B14.1 · S14 | REOS app keys | Yes | Not started |
| [Homes.com leads](#homes-com-leads) | Coming soon | B14.1 · S14 | REOS app keys | Yes | Not started |

### Contacts

| Integration | Pilot status | Built in | Connects with | Personal data | Access |
| --- | --- | --- | --- | --- | --- |
| [Phone contacts](#phone-contacts) | Coming soon | B7.1 · S7 | None | Yes | Not needed |

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
| [Sign in with Google](#google-sign-in) | One-tap sign-in with a Google account on the phone and the web. | Client + Mayura | S3 (4 Jan 2027) | Not started |
| [Sign in with Apple](#apple-sign-in) | One-tap sign-in with an Apple ID on the phone and the web, with Hide My Email. | Client | S3 (4 Jan 2027) | Not started |
| [Twilio Verify (phone sign-in codes)](#twilio-verify) | Sends and checks the six-digit codes for phone sign-in and two-step checks, with fraud protection. | Client | S3 (4 Jan 2027) | Not started |
| [Cloudflare for SaaS (tenant custom domains)](#cloudflare-saas) | Tenants' own domains (learn.example.com) with automatic SSL, verified from Branding and domain or Brokerage settings. | Client | S4 (18 Jan 2027) | Not started |
| [Amazon Transcribe and Polly (speech)](#aws-speech) | Voice to-dos and notes (speech to text) and audio-only lessons (text to speech). | Client | S4 (18 Jan 2027) | Not started |
| [Expo push notifications (APNs, FCM)](#expo-push) | Push notifications to the iOS and Android apps. | Mayura | S6 (15 Feb 2027) | Not started |
| [Amazon SES (email)](#amazon-ses) | Transactional email, digests and the morning brief. | Client | S6 (15 Feb 2027) | Not started |
| [Twilio SMS](#twilio-sms) | SMS alerts and codes, with quiet hours. | Client | S6 (15 Feb 2027) | Not started |
| [LiveKit Cloud](#livekit) | Mentor sessions and live classes (rooms, whiteboard, consented recording). | Mayura | S6 (15 Feb 2027) | Not started |
| [Mux Video](#mux) | Lesson video streaming and offline download. | Mayura | S5 (1 Feb 2027) | Not started |
| [Synthesia (AI avatar lessons; HeyGen fallback)](#synthesia) | Render AI avatar lesson videos from approved scripts. | Client | S7 (1 Mar 2027) | Not started |
| [Claude on Amazon Bedrock](#aws-bedrock-claude) | AI answers with sources, course drafting, contract review, role-play; first used to turn spoken notes into to-dos (B4.3). | Client | S4 (18 Jan 2027) | Not started |
| [Amazon Titan Text Embeddings V2 on Bedrock](#aws-bedrock-embeddings) | Search course content by meaning (knowledge base passages, 1,024 dimensions in pgvector). | Client | S5 (1 Feb 2027) | Not started |
| [Amazon Textract](#aws-textract) | Read uploaded CE certificates and licences. | Client | S10 (12 Apr 2027) | Not started |
| [Mapbox](#mapbox) | Provider service areas on a map. | Mayura | S15 (21 Jun 2027) | Not started |
| [Sentry](#sentry) | Error tracking for web, API and mobile. | Mayura | S19 (16 Aug 2027) | Not started |
| [PostHog](#posthog) | Product analytics, funnels and session insights. | Mayura | S19 (16 Aug 2027) | Not started |
| [OpenTelemetry and Grafana](#grafana-otel) | Tracing, metrics and dashboards. | Mayura | S19 (16 Aug 2027) | Not started |

## Vendor accounts

Accounts the build and release process needs.

| Item | Purpose | Owner | Needed by | Status |
| --- | --- | --- | --- | --- |
| [GitHub repository and Actions](#github) | Source code, pull requests and CI. | Client | S1 (7 Dec 2026) | Approved |
| [Product domain (reos.com)](#product-domain) | Web app, API, email sender and tenant custom domains. | Client | S4 (18 Jan 2027) | Not started |
| [AWS organisation](#aws-organisation) | Sandbox access for AWS services from Sprint 6 (SES, Bedrock, Textract, speech); staging and production cells in us-east-2 before the pilot. | Client | S6 (15 Feb 2027) | Not started |
| [Cloudflare](#cloudflare) | DNS for reos.com (domain verification, public privacy page) from Sprint 4; WAF and tenant custom domains when staging is built. | Client | S4 (18 Jan 2027) | Not started |
| [Apple Developer Program (organisation)](#apple-developer) | TestFlight and App Store releases; push notification key. | Client | S19 (16 Aug 2027) | Not started |
| [Google Play Console (organisation)](#google-play) | Android internal testing and Play Store releases. | Client | S19 (16 Aug 2027) | Not started |
| [Expo organisation (EAS)](#expo) | Mobile builds, store submission and over-the-air updates. | Mayura | S19 (16 Aug 2027) | Not started |

## Outside approvals

Regulatory and legal sign-offs with fixed lead times.

| Item | Purpose | Owner | Needed by | Status |
| --- | --- | --- | --- | --- |
| [TREC approval of REOS-delivered courses](#trec-course-approvals) | Courses count for qualifying education and CE only once TREC approves them, including AI avatar delivery. | Nelle | S19 (16 Aug 2027) | Not started |
| [RESPA counsel review](#respa-review) | Confirm provider plans and rewards are RESPA-safe before the provider marketplace is built. | Client | S15 (21 Jun 2027) | Not started |

## Details

### Sign in with Google
<a id="google-sign-in"></a>

**Platform service** · Sign-in · built in A3.1 (sprint 3)

One-tap sign-in with a Google account on the phone and the web.

- **Owner:** Client + Mayura
- **Access:** Self-serve, about 1 week; needed by S3 (4 Jan 2027); apply by 28 Dec 2026. Status: Not started.
- **Sandbox:** Any Google account; the OAuth consent screen stays in testing until launch. Only openid, email and profile scopes, so no Google verification is needed.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free.
- **Notes:** Separate OAuth client from Google Calendar (google-calendar), so sign-in never waits for the restricted-scope verification. The phone app asks Google for an ID token for the web client ID; iOS also needs its own iOS client ID.

### Sign in with Apple
<a id="apple-sign-in"></a>

**Platform service** · Sign-in · built in A3.1 (sprint 3)

One-tap sign-in with an Apple ID on the phone and the web, with Hide My Email.

- **Owner:** Client
- **Access:** Self-serve, about 0 weeks; needed by S3 (4 Jan 2027); apply by 4 Jan 2027. Status: Not started.
- **Sandbox:** Any Apple ID on a development build. Needs the Apple Developer account (apple-developer) first.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free with the Apple Developer membership.
- **Notes:** client_id is the Services ID used on the web; the phone app's bundle identifier is also accepted as an audience. App Store guideline 4.8 asks apps that offer Google sign-in to also offer a privacy-friendly login, which this meets.
- **Fallback:** Android and the web launch with Google and phone sign-in; iOS waits for this entry.

### Twilio Verify (phone sign-in codes)
<a id="twilio-verify"></a>

**Platform service** · Sign-in · built in A3.1 (sprint 3)

Sends and checks the six-digit codes for phone sign-in and two-step checks, with fraud protection.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S3 (4 Jan 2027); apply by 28 Dec 2026. Status: Not started.
- **Sandbox:** Twilio test credentials; Verify sends to verified numbers on a trial account.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per successful verification plus the message fee; Fraud Guard included.
- **Notes:** Verify uses Twilio's pre-registered senders, so phone sign-in doesn't wait for the 10DLC registration that twilio-sms needs. Decision 3 in the Sprint 3 spec.

### Cloudflare for SaaS (tenant custom domains)
<a id="cloudflare-saas"></a>

**Platform service** · Platform · built in A4.2 (sprint 4)

Tenants' own domains (learn.example.com) with automatic SSL, verified from Branding and domain or Brokerage settings.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S4 (18 Jan 2027); apply by 11 Jan 2027. Status: Not started.
- **Sandbox:** A staging zone with Cloudflare for SaaS enabled; 100 custom hostnames free.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** 100 custom hostnames free on every plan, then $0.10 each per month (decision 2 in the Sprint 4 spec).
- **Notes:** The API token needs the SSL and Certificates edit permission on the reos.com zone. Runs on the Simulator until the account exists; the Simulator verifies a domain on the first check.
- **Fallback:** Tenants use their <slug>.reos.com address.
- **Sources:** [1](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/plans/)

### Google Calendar
<a id="google-calendar"></a>

**Integration** · Calendar and email · built in B4.1 (sprint 4) · pilot status **Enabled**

Two-way calendar for every role. Appointments, showings, deadlines and to-do due dates made in REOS go to the calendar the person picks; changes in Google come back live.

- **Owner:** Client + Mayura
- **Access:** OAuth verification, about 10 weeks; needed by S4 (18 Jan 2027); apply by 9 Nov 2026. Status: Not started.
- **Sandbox:** Test users on an unverified Google Cloud project (up to 100) until verification is approved.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** Calendar only (Sprint 4 decision 1): REOS doesn't read or send mail. Scopes are calendar.events (read and write events on the person's calendars) and calendar.calendarlist.readonly (the list of calendars to choose from). Both are sensitive scopes: Google's standard OAuth verification, with no security assessment. Push channels (events.watch) last about a week and are renewed a day before they end. The callback address is /integrations/google-calendar/callback.
- **Fallback:** Scheduled sync every 15 minutes when push channels can't reach REOS; ICS calendar feed.
- **Sources:** [1](https://developers.google.com/calendar/api/auth) · [2](https://developers.google.com/calendar/api/guides/push) · [3](https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification)

### Outlook Calendar
<a id="outlook-calendar"></a>

**Integration** · Calendar and email · built in B4.2 (sprint 4) · pilot status **Enabled**

Two-way calendar for Microsoft 365 and Outlook.com. Appointments, showings, deadlines and to-do due dates made in REOS go to the calendar the person picks; changes in Outlook come back live.

- **Owner:** Client + Mayura
- **Access:** Vendor review, about 3 weeks; needed by S4 (18 Jan 2027); apply by 28 Dec 2026. Status: Not started.
- **Sandbox:** Microsoft 365 Developer Program tenant, or a personal Outlook.com account.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API.
- **Notes:** Calendar only (Sprint 4 decision 1): Outlook mail is not read or sent. Microsoft Entra multi-tenant app (the "common" endpoint, so work and personal accounts both sign in) with publisher verification (needs a Microsoft Partner Network ID), so customers' admins see a verified publisher when consenting. Delegated scopes: Calendars.ReadWrite, User.Read and offline_access. Changes come by delta queries per calendar and change-notification subscriptions (up to 7 days, renewed a day early, with lifecycle notifications). Outlook emails guests whenever an event lists them, so REOS adds guests only when the person asks for invitations. The callback address is /integrations/outlook-calendar/callback.
- **Fallback:** Scheduled sync every 15 minutes when change notifications can't reach REOS.
- **Sources:** [1](https://learn.microsoft.com/en-us/entra/identity-platform/publisher-verification-overview) · [2](https://learn.microsoft.com/en-us/graph/delta-query-events) · [3](https://learn.microsoft.com/en-us/graph/change-notifications-delivery-webhooks) · [4](https://learn.microsoft.com/en-us/graph/api/resources/subscription)

### Apple Calendar (iCloud)
<a id="apple-calendar"></a>

**Integration** · Calendar and email · built in B4.1 (sprint 4) · pilot status **Coming soon**

Read events from iCloud calendars.

- **Owner:** Client + Mayura
- **Access:** Self-serve, about 0 weeks; needed by S4 (18 Jan 2027); apply by 18 Jan 2027. Status: Not needed.
- **Sandbox:** Any Apple ID with an app-specific password.
- **Credentials:** None · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free.
- **Notes:** iCloud has no OAuth calendar API: people connect with CalDAV and an app-specific password, so REOS holds no platform keys. Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.

### Zoom
<a id="zoom"></a>

**Integration** · Calendar and meetings · built in B4.2 (sprint 4) · pilot status **Coming soon**

Meeting links for showings, sessions and client calls.

- **Owner:** Client + Mayura
- **Access:** Vendor review, about 4 weeks; needed by S4 (18 Jan 2027); apply by 21 Dec 2026. Status: Not started.
- **Sandbox:** Zoom Marketplace development app; any Zoom account until published.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free API; publishing needs Zoom Marketplace review.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.

### Microsoft Teams meetings
<a id="microsoft-teams"></a>

**Integration** · Calendar and meetings · built in B4.2 (sprint 4) · pilot status **Coming soon**

Teams meeting links on REOS events.

- **Owner:** Client + Mayura
- **Access:** Self-serve, about 0 weeks; needed by S4 (18 Jan 2027); apply by 18 Jan 2027. Status: Not needed.
- **Sandbox:** Uses the Microsoft 365 connection.
- **Credentials:** None · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Included with Microsoft 365.
- **Notes:** Created through the Microsoft 365 (Graph) connection, so it has no keys of its own.

### Google Meet
<a id="google-meet"></a>

**Integration** · Calendar and meetings · built in B4.2 (sprint 4) · pilot status **Coming soon**

Google Meet links on REOS events.

- **Owner:** Client + Mayura
- **Access:** Self-serve, about 0 weeks; needed by S4 (18 Jan 2027); apply by 18 Jan 2027. Status: Not needed.
- **Sandbox:** Uses the Google Calendar and Gmail connection.
- **Credentials:** None · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Included with Google accounts.
- **Notes:** Created through the Google Calendar connection (conference data), so it has no keys of its own.

### ShowingTime
<a id="showingtime"></a>

**Integration** · Calendar and meetings · built in B9.2 (sprint 9) · pilot status **Coming soon**

Showing requests and confirmations on the REOS calendar.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 8 weeks; needed by S9 (29 Mar 2027); apply by 1 Feb 2027. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** Showing confirmation emails forwarded to REOS.

### BrokerBay
<a id="brokerbay"></a>

**Integration** · Calendar and meetings · built in B9.2 (sprint 9) · pilot status **Coming soon**

Showing and appointment bookings on the REOS calendar.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 8 weeks; needed by S9 (29 Mar 2027); apply by 1 Feb 2027. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** Booking emails forwarded to REOS.

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
- **Notes:** B6.2b sends through it (PushChannel; receipts checked by reos:push:receipts). Needs the EAS project id in the app config and the Apple push key and Firebase project from the store accounts; until set up, production sends no push and the simulator keeps an outbox elsewhere. The access token is only needed if push security is turned on in Expo.

### Amazon SES (email)
<a id="amazon-ses"></a>

**Platform service** · Notifications · built in B6.2 (sprint 6)

Transactional email, digests and the morning brief.

- **Owner:** Client
- **Access:** Vendor review, about 1 week; needed by S6 (15 Feb 2027); apply by 8 Feb 2027. Status: Not started.
- **Sandbox:** SES sandbox (verified recipients only).
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per 1,000 emails.
- **Notes:** Request production access; set up SPF, DKIM and DMARC on the product domain. B6.2d sends all mail through it when MAIL_MAILER=reos (keys resolved from here at send time; the log until set up) and reads bounces and complaints from an SNS topic subscribed to https://reos.com/webhooks/ses (SNS-signed; pin the topic ARN here). Hard bounces and complaints stop email to that address until the person resumes it.

### Twilio SMS
<a id="twilio-sms"></a>

**Platform service** · Notifications · built in B6.2 (sprint 6)

SMS alerts and codes, with quiet hours.

- **Owner:** Client
- **Access:** Vendor review, about 4 weeks; needed by S6 (15 Feb 2027); apply by 18 Jan 2027. Status: Not started.
- **Sandbox:** Twilio test credentials and a trial number.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per message plus one-off and monthly 10DLC registration fees.
- **Notes:** US A2P 10DLC brand registration (up to about a week) and campaign vetting (8–10 business days per submission; a rejection restarts it). Needs a live privacy policy and terms on the product domain first. A4.3a already sends invite texts through it (Programmable Messaging); until it is set up, the simulator writes them to the log and invites by text are offered only outside production. B6.2c sends text alerts through it (only with recorded SMS consent) and receives replies at https://reos.com/webhooks/twilio-sms (set as the number's or Messaging Service's incoming webhook): STOP, START and HELP, signed with the auth token. Keep Advanced Opt-Out on.
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
- **Notes:** B6.3a mints room tokens (one person, one room, until the booked end) and closes rooms at the end through the RoomService API. Until set up, rooms are simulated outside production and refused in production. Recording to S3 (egress) arrives with B6.3b.

### Mux Video
<a id="mux"></a>

**Platform service** · Video · built in A5.3 (sprint 5)

Lesson video streaming and offline download.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S5 (1 Feb 2027); apply by 25 Jan 2027. Status: Not started.
- **Sandbox:** Mux test environment.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Per minute encoded, stored and delivered.
- **Notes:** Lessons upload to S3 first; Mux copies each video from a short-lived signed link, plays it with signed playback (the signing key) and writes captions in the lesson's language. The webhook (/webhooks/mux, signed with the webhook secret) says when a video and its captions are ready; the captions become searchable passages. Off: the original file plays from S3.

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

**Platform service** · AI · built in B4.3 (sprint 4)

AI answers with sources, course drafting, contract review, role-play; first used to turn spoken notes into to-dos (B4.3).

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S4 (18 Jan 2027); apply by 11 Jan 2027. Status: Not started.
- **Sandbox:** Staging AWS account, us-east-2.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Per token.
- **Notes:** Request model access and quota increases in us-east-2 (and us-west-2 for recovery). No training on customer data. Voice to-dos (B4.3) use the Converse API with a forced tool for a fixed answer shape; the model id is set in Settings › Integrations. Without keys (Off or Simulator) the built-in date parser runs instead.

### Amazon Titan Text Embeddings V2 on Bedrock
<a id="aws-bedrock-embeddings"></a>

**Platform service** · AI · built in A5.3 (sprint 5)

Search course content by meaning (knowledge base passages, 1,024 dimensions in pgvector).

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S5 (1 Feb 2027); apply by 25 Jan 2027. Status: Not started.
- **Sandbox:** Staging AWS account, us-east-2.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Per input token (about $0.02 per million).
- **Notes:** Request access to amazon.titan-embed-text-v2:0 in us-east-2. Only course content is embedded, never personal data. Off or Simulator: the knowledge base searches by keyword (PostgreSQL full text) only.

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

### Open to Close
<a id="open-to-close"></a>

**Integration** · Transactions · built in B9.1 (sprint 9) · pilot status **Coming soon**

Transaction coordination: deals, tasks and deadlines.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 6 weeks; needed by S9 (29 Mar 2027); apply by 15 Feb 2027. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** CSV export.

### Paperless Pipeline
<a id="paperless-pipeline"></a>

**Integration** · Transactions · built in B9.1 (sprint 9) · pilot status **Coming soon**

Brokerage transactions, documents and checklists.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 6 weeks; needed by S9 (29 Mar 2027); apply by 15 Feb 2027. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** CSV export.

### ListedKit
<a id="listedkit"></a>

**Integration** · Transactions · built in B9.1 (sprint 9) · pilot status **Coming soon**

AI transaction coordinator: deals and deadlines.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 6 weeks; needed by S9 (29 Mar 2027); apply by 15 Feb 2027. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** Deadline emails forwarded to REOS.

### BoldTrail BackOffice
<a id="boldtrail-backoffice"></a>

**Integration** · Transactions · built in B9.1 (sprint 9) · pilot status **Coming soon**

Brokerage back office: deals, commissions and agent billing.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 6 weeks; needed by S9 (29 Mar 2027); apply by 15 Feb 2027. Status: Not started.
- **Sandbox:** Partner sandbox on approval.
- **Credentials:** Customer's own account · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Formerly Brokermint. Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** CSV export.

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

### Zillow leads
<a id="zillow-leads"></a>

**Integration** · Leads · built in B14.1 (sprint 14) · pilot status **Coming soon**

New Zillow Premier Agent leads, sent to the default CRM.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 10 weeks; needed by S14 (7 Jun 2027); apply by 29 Mar 2027. Status: Not started.
- **Sandbox:** Zillow partner test account on approval.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** Lead emails forwarded to REOS.

### Realtor.com leads
<a id="realtor-com-leads"></a>

**Integration** · Leads · built in B14.1 (sprint 14) · pilot status **Coming soon**

New Realtor.com leads, sent to the default CRM.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 10 weeks; needed by S14 (7 Jun 2027); apply by 29 Mar 2027. Status: Not started.
- **Sandbox:** Partner test account on approval.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** Lead emails forwarded to REOS.

### Homes.com leads
<a id="homes-com-leads"></a>

**Integration** · Leads · built in B14.1 (sprint 14) · pilot status **Coming soon**

New Homes.com leads, sent to the default CRM.

- **Owner:** Client + Mayura
- **Access:** Partner application, about 10 weeks; needed by S14 (7 Jun 2027); apply by 29 Mar 2027. Status: Not started.
- **Sandbox:** Partner test account on approval.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Partner terms on application.
- **Notes:** Added from the Connections and Sync journey library (B3.2); API access and key type to be confirmed when the partner application is filed.
- **Fallback:** Lead emails forwarded to REOS.

### Phone contacts
<a id="phone-contacts"></a>

**Integration** · Contacts · built in B7.1 (sprint 7) · pilot status **Coming soon**

Names, numbers and emails from the phone, so a new agent starts with a contact list.

- **Owner:** Client + Mayura
- **Access:** Self-serve, about 0 weeks; needed by S7 (1 Mar 2027); apply by 1 Mar 2027. Status: Not needed.
- **Sandbox:** The phone's own contacts permission.
- **Credentials:** None · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Free.
- **Notes:** Read on the phone with the person's permission; nothing to set up in Settings › Integrations.

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
- **Notes:** Open PRD question which membership system and fields. Data-sharing agreement and security review usually precede access; start the conversation now. Sign-in itself is built generically (A4.2c, SAML 2.0 or OpenID Connect, set up by the association admin on screen 57), so the association only gives its identity provider's metadata or issuer and client; this entry is for its member-data feed.

### Sentry
<a id="sentry"></a>

**Platform service** · Operations · built in 19.1 (sprint 19)

Error tracking for web, API and mobile.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S19 (16 Aug 2027); apply by 9 Aug 2027. Status: Not started.
- **Sandbox:** Separate staging project.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Team plan.
- **Notes:** Scrub personal data before sending; US data region.

### PostHog
<a id="posthog"></a>

**Platform service** · Operations · built in 19.1 (sprint 19)

Product analytics, funnels and session insights.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S19 (16 Aug 2027); apply by 9 Aug 2027. Status: Not started.
- **Sandbox:** Separate staging project.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Usage-based; generous free tier.
- **Notes:** US cloud; respect consent and Texas privacy law (TDPSA).

### OpenTelemetry and Grafana
<a id="grafana-otel"></a>

**Platform service** · Operations · built in 19.1 (sprint 19)

Tracing, metrics and dashboards.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S19 (16 Aug 2027); apply by 9 Aug 2027. Status: Not started.
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

### Product domain (reos.com)
<a id="product-domain"></a>

**Vendor account** · Platform · built in B4.1 (sprint 4)

Web app, API, email sender and tenant custom domains.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S4 (18 Jan 2027); apply by 11 Jan 2027. Status: Not started.
- **Sandbox:** Not needed.
- **Credentials:** None · **Personal data:** no
- **Cost:** Domain registration.
- **Notes:** Decided 20 Nov 2026: reos.com is the working domain; Nelle may change it before launch. Ownership is not confirmed yet. Every four-letter .com is registered, so confirm the client owns or can buy reos.com. Google, Meta and the MLSs verify ownership through DNS, and each needs a live public page with a privacy policy and terms before review.

### AWS organisation
<a id="aws-organisation"></a>

**Vendor account** · Platform · built in 19.1 (sprint 19)

Sandbox access for AWS services from Sprint 6 (SES, Bedrock, Textract, speech); staging and production cells in us-east-2 before the pilot.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S6 (15 Feb 2027); apply by 8 Feb 2027. Status: Not started.
- **Sandbox:** Separate staging account.
- **Credentials:** REOS app keys · **Personal data:** yes (vendor needs a data-processing agreement)
- **Cost:** Usage.
- **Notes:** Billing in the client's name; Mayura gets admin roles through IAM Identity Center, never root keys.

### Cloudflare
<a id="cloudflare"></a>

**Vendor account** · Platform · built in B4.1 (sprint 4)

DNS for reos.com (domain verification, public privacy page) from Sprint 4; WAF and tenant custom domains when staging is built.

- **Owner:** Client
- **Access:** Self-serve, about 1 week; needed by S4 (18 Jan 2027); apply by 11 Jan 2027. Status: Not started.
- **Sandbox:** Staging zone.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Pro or Business plan plus Cloudflare for SaaS per custom hostname.

### Apple Developer Program (organisation)
<a id="apple-developer"></a>

**Vendor account** · Platform · built in 19.2 (sprint 19)

TestFlight and App Store releases; push notification key.

- **Owner:** Client
- **Access:** Vendor review, about 4 weeks; needed by S19 (16 Aug 2027); apply by 19 Jul 2027. Status: Not started.
- **Sandbox:** Not needed.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** Yearly membership fee.
- **Notes:** Organisation enrolment needs a D-U-N-S number for the client's legal entity; request it first.

### Google Play Console (organisation)
<a id="google-play"></a>

**Vendor account** · Platform · built in 19.2 (sprint 19)

Android internal testing and Play Store releases.

- **Owner:** Client
- **Access:** Vendor review, about 2 weeks; needed by S19 (16 Aug 2027); apply by 2 Aug 2027. Status: Not started.
- **Sandbox:** Not needed.
- **Credentials:** REOS app keys · **Personal data:** no
- **Cost:** One-off registration fee.
- **Notes:** Organisation identity verification (D-U-N-S also used here).

### Expo organisation (EAS)
<a id="expo"></a>

**Vendor account** · Platform · built in 19.2 (sprint 19)

Mobile builds, store submission and over-the-air updates.

- **Owner:** Mayura
- **Access:** Self-serve, about 1 week; needed by S19 (16 Aug 2027); apply by 9 Aug 2027. Status: Not started.
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
