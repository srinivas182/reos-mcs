# ADR 0064: The public agent page and digital card are plain server HTML

- Status: Accepted (B14.1)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

The agent's public page (B10.2b) was an Inertia React page: search engines and AI answer engines got an almost empty HTML shell, and every visitor downloaded the app bundle. Inertia server-side rendering would need a Node process in production for every page in the app. Agents also want a digital card (as Blinq and HiHello offer), but those cards carry no TREC notices and no broker name.

## Decision

1. **Blade, not Inertia.** The public page (`/agent/{handle}`) and the card (`/agent/{handle}/card`) are rendered by Blade (`resources/views/public/*`). They use the same Tailwind tokens (`@vite('resources/css/app.css')` only, no JavaScript), a skip link, semantic headings and 48px buttons. The page is in the agent's language (`lang` set to match).
2. **What engines read.**
   - A title, description, canonical, Open Graph tags, and `noindex` for sample data.
   - JSON-LD for **RealEstateAgent**: name, URL, bio, phone and email only if shown, areas served, languages, the brokerage as `parentOrganization`, licenses as credentials recognized by TREC, and the peer rating only if the agent shows it.
   - A **FAQPage** written from what the page shows: who the agent is with their license and brokerage, the areas they serve, how to reach them, whether they have listings, and where the TREC notices are. The same questions appear on the page.
3. **Contact details are opt-in.** The phone, email and areas (`agent_pages.phone`, `email`, `areas`) show only when the agent adds them. The account's own email and phone never appear. With no areas set, the page uses the cities of the agent's listings.
4. **The card.**
   - Initials, name, title, broker name and licenses.
   - Call, Text, Email, Save contact (a vCard 3.0 at `/agent/{handle}/contact.vcf`) and the page link.
   - A QR code of the page (SVG, `/agent/{handle}/qr.svg`, from `bacon/bacon-qr-code`, now a direct dependency).
   - Short links to the IABS and the Consumer Protection Notice.
5. **Sitemap and robots.** `/sitemap.xml` lists published pages. `/robots.txt` is now a route so it can point to the sitemap at the app's own address; `public/robots.txt` is removed.
6. **In the app.**
   - On the web, the page settings card gains the contact fields and a "Your digital card" block: the QR code, Open, Download contact file, and Copy link.
   - On the phone, the listings page card links to **My digital card**: the QR code, share, open, and the contact fields.
   - The React public page is removed.

## Consequences

- Lighthouse (mobile, local run on 28 November 2026) scored performance 99, accessibility 100, best practices 100 and SEO 100 on both the page and the card.
- The public page no longer shares React components with the app, so a change to the listing card has to be made in Blade too.
- Answers on the page follow from the agent's own data, so an answer engine can't be told anything the page doesn't say.
