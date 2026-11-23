# ADR 0043: Licence lookups and certificate OCR

- Status: Accepted (regulator data, TREC lookup and provider licences, B10.3a)
- Date: 2026-11-24 (Sprint 10; spec in docs/sprints/S10-spec.md)

## Context

Licence checks have run on the Simulator since A5.1. Before agents, candidates switching to agent (A10.3c) and service providers can rely on them, REOS needs the regulators' real data:
- **TREC** publishes its license holders daily: agents and brokers, inspectors, and easement or right-of-way agents. The files carry type, number, name, status, dates, education and MCE status and the sponsoring broker. Phones, emails and addresses are withheld (SB 510).
- **TDI** publishes its individual licensees on the Texas Open Data Portal.
- **NMLS** has no public API, and Consumer Access's terms restrict automated queries.

## Decision

1. **Import, don't scrape** (decision 8). A `regulator` block in the integrations register names each authority's files. TREC has two files: agents and brokers (`s7ft-44qi`) and inspectors (`pkr9-9isv`), both on the Texas Open Data Portal. TDI has one: licensees (`kxv3-diwf`).
   - `reos:regulators:import` runs nightly at 05:30 Central, before the morning's licence watch (A10.3c), and loads them into `regulator_records`.
   - Each import replaces the authority's records. If a file has fewer than half of the last import's rows, the last import is kept and the error is recorded, so a truncated download can't wipe the table.
2. **One reader for both formats** (`RegulatorFile`):
   - TREC's own tab-delimited layout: no header, 33 fields, in the order of TREC's instructions.
   - The portal's CSV exports: columns matched by name, so "License Number", `license_number` and `licensenumber` are the same column.
   - Dates may be CCYYMMDD, MM/DD/YYYY or ISO. Statuses may be codes or words.
   - Only public licence facts are kept.
3. **TREC lookup** (`TrecLicenceLookup`): once the integration is live and imported, a Texas number is a single indexed query, so it is verified in milliseconds. Numbers are compared as digits without leading zeros.
   - SALE is a sales agent; BRK, BLLC, BCRP and partnership brokers are brokers.
   - Statuses 20 and 30 are active and 21 and 31 inactive. Expired, suspended, relinquished, revoked and deceased don't count, so they read as expired.
   - The sponsor is TREC's related licence.
   - The Simulator keeps its demo rules outside production.
4. **Provider licences** (`provider_licences`), on the provider's Settings › Profile:
   - **TREC inspector** and **TDI** numbers (a TDI number or NPN) are checked against the imported data at once. An active record in the provider's name is verified; an inactive one shows as not active.
   - **Review cases:** another name, or no record, goes to a reviewer.
   - **NMLS IDs** always go to a reviewer. The People screen links each to its NMLS Consumer Access page. Approvals and rejections are audited.
5. **Simulator:** demo files in both formats (TREC's tab layout for agents, CSVs for inspectors and TDI) run through the same import, so parsing is tested exactly as it runs live.

## Consequences

- Done when a licence number is verified in seconds: met once `trec-licence` is live; it needs no keys (public data).
- TDI's column names are matched by name and should be confirmed on the first live import.
- The certificate OCR (B10.3b) is recorded here when it lands.

## Alternatives considered

- **Scraping TREC's search pages:** fragile and slow, and against the intent of the published files.
- **Calling a lookup per check:** TREC offers files, not an API; a local table answers in milliseconds and keeps working when the portal is slow.
- **Automating NMLS Consumer Access:** its terms restrict automated queries; a reviewer with a direct link is the allowed route until NMLS offers one.
