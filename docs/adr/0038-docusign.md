# ADR 0038: Signatures from deals through DocuSign

- Status: Accepted (B9.3)
- Date: 2026-11-23 (Sprint 9; spec in docs/sprints/S09-spec.md)

## Context

Agents already sign with DocuSign, but sending is a separate app and the signed copy has to be filed on the transaction by hand. Sprint 9 lets an agent send for signature from a deal, follow each signer, and have the signed copy filed on the deal and in the transaction tool (decision 5 of the sprint covers only exam scripts; signing follows the spec as written).

## Decision

1. **The agent's own DocuSign.** Each agent connects their DocuSign account from Connections (OAuth 2 authorization code with PKCE, `signature` scope, about eight-hour tokens that refresh). Their branding, templates and billing stay theirs.
   - REOS's integration key and secret live in Settings › Integrations (register: `integration_key`, `secret_key`), with the demo or live account server chosen by the integration's mode.
   - The account's API base URI comes from `userinfo` once and is kept on the connection.
2. **Send: REOS prepares, the agent sends.**
   - From a deal, the agent chooses the deal's PDFs (or adds one, up to 25 MB, kept on the content disk as the deal's file) and the signers, pre-filled from the deal's parties with their signing order.
   - REOS creates a **draft** envelope (documents and signers, no fields) and opens DocuSign's **sender view**, where the agent places the fields and presses Send. REOS never sends anything itself.
   - DocuSign returns the agent to REOS (`event=Send`, or `Cancel` and others); a draft left unsent can be continued from the deal.
3. **Follow: Connect, then poll.**
   - Each envelope asks DocuSign Connect to report envelope and recipient events to `/webhooks/docusign`. The address carries a sealed connection id (as for Follow Up Boss and dotloop); when Settings › Integrations holds the account's Connect HMAC key (`webhook_secret`), deliveries must carry a matching `X-DocuSign-Signature-1` (HMAC-SHA256, base64).
   - A delivery only queues a sync of the connection's `envelopes` stream; its body is never trusted. The sync reads envelopes whose status changed since the last pass, with their signers, and updates the envelopes REOS prepared (`EnvelopeStore`); the agent's other envelopes are passed over and never stored.
   - The 15-minute poll is the backstop, and deliveries are deduplicated by envelope, event and time.
4. **Statuses.** Envelope: draft, sent, delivered (opened), completed, declined, voided. Signer: waiting (not their turn), sent, delivered (opened), signed, declined, each with its time.
5. **File: once, on completion.**
   - The combined signed PDF and the certificate of completion are downloaded and filed on the deal (`deal_files`, kinds `signed` and `certificate`), opened with a 30-minute signed link.
   - When the deal comes from a tool that takes documents (`AcceptsDocuments`), the signed copy is uploaded there too: **dotloop** (into a "Signed (REOS)" folder in the loop, found among the chosen profiles) and **SkySlope** (onto the sale or listing). Lone Wolf's published API has no upload, so its deals keep the copy in REOS only. A failed upload is reported and doesn't undo the filing.
   - The agent gets a push and inbox entry (`signature_completed`, in the Deals group): "Signed: Seller's Disclosure, 2207 Bellaire".
6. **Virus scanning.** Every PDF kept on a deal (an upload, a signed copy, a certificate) is scanned with ClamAV, as lesson files and message attachments are. Until it's clean it can't be opened or sent; an infected file is deleted and marked so.
7. **Privacy and tenancy.** Envelopes and kept files are the agent's own (`organisation_id` null, row-level security allowing personal rows); brokers never see them. Filing runs as the platform because the deal may belong to a brokerage's tool, and every query in it is scoped to the agent's own envelope.
8. **Screens.** The web deal panel (screen 14) gains Signatures (Send for signature, each envelope with its signers, Continue in DocuSign for a draft) and Files in REOS. The phone's deal screen shows the envelopes and opens the signed copies; sending stays on the website, where DocuSign's sender view has room. On demo data, a REOS page stands in for the sender view and each sync moves an envelope one step (opened, then signed).

## Consequences

- Any e-signature tool can be added by implementing `SendsForSignature`; any transaction tool that takes files by implementing `AcceptsDocuments`.
- DocuSign's go-live review needs enough successful calls in the demo environment before production keys are issued.
- To confirm in the partner sandboxes: SkySlope's document upload body and dotloop's folder creation response.

## Alternatives considered

- **Embedded signing for the signers:** signers sign from DocuSign's own email, which they trust; REOS doesn't host their signing.
- **Placing fields in REOS:** DocuSign's sender view already does this well, with the agent's templates and tabs; rebuilding it adds risk with no gain.
- **Trusting Connect's payload:** a sync re-reads DocuSign, so a forged or replayed delivery can at most cause an extra read.
