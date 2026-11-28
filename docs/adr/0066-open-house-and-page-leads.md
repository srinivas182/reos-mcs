# ADR 0066: Open house sign-in and page leads go straight into the agent's CRM

- Status: Accepted (B14.3a)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

Open house apps (Curb Hero, Open Home Pro, Spacio) collect visitors on a tablet and push them to a CRM through a separate integration, often a day later. Their weaknesses:

- They need a tablet and a signal at the house.
- The visitor list sits in yet another tool.
- Someone already working with another agent gets the same drip campaign as everyone else.

Lead forms on agent websites have the same gap: the message lands in an inbox, not in the CRM with a next step.

REOS already adds people to the agent's CRM (B8.3b: matched by email or phone, then written to the default CRM), writes notes and tasks back (B7.2, B8.3), and has the agent's public page (B14.1).

## Decision

1. **Starting sign-in.**
   - The agent starts sign-in for an open house on the web (address, city, start and end) or on the phone (from now, for three hours).
   - REOS makes a public link `/open-house/{code}` and its QR code (SVG) to show by the door or print.
   - The code is 8 characters from A–Z and 2–9, unguessable enough for a few hours and easy to type.
   - Sign-in is open from an hour before the start to six hours after the end. Outside that window the link is a 404.
2. **Two ways to sign in, one record.**
   - **QR code:** the visitor fills in a Blade form on their own phone, in the agent's language. It has no JavaScript and is `noindex`.
   - **Kiosk mode:** the agent hands over their phone. Each sign-in is saved on the phone first (MMKV) with its own `client_id`, and sent when the phone is online. The server adds each `client_id` once per open house, so sending again after a dropped connection never doubles a contact.
   - Both forms collect the same details:
     - a name, plus an email or a phone;
     - their timeline;
     - whether they're pre-approved;
     - whether they're working with an agent;
     - consent, naming the agent.
3. **Into the CRM at once** (`App\Leads\Leads`). Each visitor:
   - is added like any contact the agent adds, tagged "Open house";
   - gets a note of the visit, and a note with a suggested first message;
   - gets a to-do for tomorrow, sent to the CRM that takes tasks;
   - triggers a `new_lead` notification for the agent.

   Someone who says they're working with another agent gets no sales follow-up, only a to-do to send them what they asked for. TREC rules bar interfering with another broker's client.
4. **The question form on the agent's page.**
   - `POST /agent/{handle}/contact` takes a name, an email or phone, a question and consent.
   - It goes through the same path, with the note "Wrote from your page" and a drafted reply.
5. **Abuse.**
   - A honeypot field.
   - Rate limits: 20 sign-ins a minute per open house link, and 5 page messages a minute.
   - Consent is required.
   - Only the agent sees their open houses and visitors (row-level security plus an owner check).
   - The QR code only encodes the public link.

## Consequences

- No new vendor: the CRM write-back and notifications already exist. Any CRM REOS connects to receives open house leads the same day.
- Visitor data is the agent's, under the same row-level security as contacts.
- MLS open house schedules (B10) can later prefill the address and times.

## Alternatives considered

- **A separate tablet app:** another install and sign-in. The phone kiosk mode and QR cover it.
- **Syncing to the CRM in a nightly batch:** follow-up speed is the point.
- **One drip campaign for everyone:** it fails the "has an agent" case.
