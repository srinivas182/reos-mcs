#!/usr/bin/env node
/**
 * REOS integrations register: validate docs/integrations/register.yaml, render
 * docs/integrations/README.md from it, and write the product's catalogue
 * (apps/platform/resources/integrations/catalogue.json) that Settings ›
 * Integrations reads, so the docs and the product can't disagree.
 *
 *   node scripts/integrations-register.mjs            # validate and write README.md
 *   node scripts/integrations-register.mjs --check    # validate; fail if README.md is stale (CI)
 *   node scripts/integrations-register.mjs --status   # what's late or due, as of today
 *
 * The rendered README depends only on the YAML (never on today's date), so CI
 * gives the same answer on every machine.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';
import { parse } from 'yaml';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
// REGISTER_DIR lets the tests point at a temporary copy.
const dir = process.env.REGISTER_DIR ? resolve(process.env.REGISTER_DIR) : resolve(root, 'docs/integrations');
const yamlPath = resolve(dir, 'register.yaml');
const readmePath = resolve(dir, 'README.md');
// CATALOGUE_PATH and I18N_PATH let the tests write and read temporary copies.
const cataloguePath = process.env.CATALOGUE_PATH
  ? resolve(process.env.CATALOGUE_PATH)
  : resolve(root, 'apps/platform/resources/integrations/catalogue.json');
const i18nPath = process.env.I18N_PATH ? resolve(process.env.I18N_PATH) : resolve(root, 'packages/i18n/src/locales/en.json');
const mode = process.argv[2] ?? '--write';

const LABELS = {
  kind: { integration: 'Integration', platform_service: 'Platform service', account: 'Vendor account', approval: 'Outside approval' },
  category: {
    calendar_email: 'Calendar and email', voice: 'Voice', payments: 'Payments', notifications: 'Notifications', video: 'Video',
    ai: 'AI', crm: 'CRM', transactions: 'Transactions', esignature: 'E-signature', mls: 'MLS data', lookups: 'Lookups and reporting',
    social: 'Social', maps: 'Maps', accounting: 'Accounting', association: 'Association', operations: 'Operations',
    platform: 'Platform', regulatory: 'Regulatory',
  },
  launch: { enabled: 'Enabled', beta: 'Beta', coming_soon: 'Coming soon', not_applicable: '—' },
  owner: { client: 'Client', mayura: 'Mayura', client_with_mayura: 'Client + Mayura', nelle: 'Nelle', tenant: 'Tenant' },
  route: {
    self_serve: 'Self-serve', partner_application: 'Partner application', oauth_verification: 'OAuth verification',
    app_review: 'Vendor review', data_licence: 'Data licence', contract: 'Agreement', security_assessment: 'Security assessment',
  },
  status: { not_started: 'Not started', applied: 'Applied', in_review: 'In review', approved: 'Approved', blocked: 'Blocked', not_needed: 'Not needed' },
  credentials: { platform: 'REOS app keys', tenant: "Customer's own account", both: 'REOS or customer', none: 'None' },
};

const fail = (message) => {
  console.error(`✘ ${message}`);
  process.exit(1);
};

// ── Load and validate ────────────────────────────────────────────────────────
const register = parse(readFileSync(yamlPath, 'utf8'), { schema: 'core' });
const schema = JSON.parse(readFileSync(resolve(dir, 'register.schema.json'), 'utf8'));

// The core YAML schema keeps dates as strings, which is what JSON Schema expects.
// strictRequired is off only because conditional `required` (applied_on when filed) is intended.
const ajv = new Ajv2020({ allErrors: true, strict: true, strictRequired: false, allowUnionTypes: true });
addFormats(ajv);
const validate = ajv.compile(schema);

if (!validate(register)) {
  const problems = validate.errors.map((e) => {
    const index = e.instancePath.match(/^\/entries\/(\d+)/)?.[1];
    const id = index !== undefined ? register.entries[Number(index)]?.id : undefined;
    return `  ${id ? `[${id}] ` : ''}${e.instancePath || '/'} ${e.message}${e.params?.allowedValues ? `: ${e.params.allowedValues.join(', ')}` : ''}`;
  });
  fail(`register.yaml doesn't match the schema:\n${problems.join('\n')}`);
}

const fieldLabels = JSON.parse(readFileSync(i18nPath, 'utf8'))?.integrations?.fields ?? {};
const errors = [];
const seen = new Set();
for (const entry of register.entries) {
  if (seen.has(entry.id)) errors.push(`duplicate id "${entry.id}"`);
  seen.add(entry.id);
  if (entry.kind !== 'approval' && entry.access.needed_by_sprint > entry.build.sprint) {
    errors.push(`[${entry.id}] access is needed by sprint ${entry.access.needed_by_sprint} but the build is in sprint ${entry.build.sprint}`);
  }
  const { applied_on: applied, approved_on: approved } = entry.access;
  if (applied && approved && approved < applied) errors.push(`[${entry.id}] approved_on is before applied_on`);

  // Every service REOS talks to gets a place in Settings › Integrations.
  const connectable = ['integration', 'platform_service'].includes(entry.kind);
  if (connectable && !entry.connection && entry.access.status !== 'not_needed') {
    errors.push(`[${entry.id}] needs a connection block (how it is set up in Settings › Integrations)`);
  }
  if (!connectable && entry.connection) errors.push(`[${entry.id}] only integrations and platform services have a connection block`);
  if (entry.connection) {
    const { auth, fields } = entry.connection;
    if (auth === 'none' && fields.length) errors.push(`[${entry.id}] auth none cannot have fields`);
    const keys = fields.map((f) => f.key);
    if (new Set(keys).size !== keys.length) errors.push(`[${entry.id}] has a connection field twice`);
    for (const field of fields) {
      if (!fieldLabels[field.key]) errors.push(`[${entry.id}] field "${field.key}" has no label: add integrations.fields.${field.key} to packages/i18n`);
    }
  }
}
if (errors.length) fail(`register.yaml has problems:\n  ${errors.join('\n  ')}`);

// ── Dates ────────────────────────────────────────────────────────────────────
const DAY = 86_400_000;
const toDate = (s) => new Date(`${s}T00:00:00Z`);
const iso = (d) => d.toISOString().slice(0, 10);
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const nice = (d) => `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
const sprintStart = (n) => new Date(toDate(register.sprint_one_starts).getTime() + (n - 1) * 14 * DAY);
const latestApplyBy = (e) => new Date(sprintStart(e.access.needed_by_sprint).getTime() - e.access.lead_time_weeks * 7 * DAY);
/** The planned date wins when it's earlier than the latest safe date. */
const applyBy = (e) => {
  const latest = latestApplyBy(e);
  const planned = e.access.apply_by ? toDate(e.access.apply_by) : undefined;
  return planned && planned < latest ? planned : latest;
};
const open = (e) => !['approved', 'not_needed'].includes(e.access.status);

// ── Status mode (uses today's date; prints only) ─────────────────────────────
if (mode === '--status') {
  const today = toDate(new Date().toISOString().slice(0, 10));
  const rows = register.entries.filter(open).map((e) => ({ e, by: applyBy(e) })).sort((a, b) => a.by - b.by);
  const late = rows.filter((r) => r.by < today && r.e.access.status === 'not_started');
  const soon = rows.filter((r) => r.by >= today && r.by - today <= 28 * DAY && r.e.access.status === 'not_started');
  console.log(`Integrations register status on ${iso(today)}\n`);
  console.log(`Late (should already be filed): ${late.length}`);
  for (const { e, by } of late) console.log(`  ✘ ${e.name} — apply by ${iso(by)} · ${LABELS.owner[e.owner]} · ${LABELS.route[e.access.route]}`);
  console.log(`\nDue in the next 4 weeks: ${soon.length}`);
  for (const { e, by } of soon) console.log(`  • ${e.name} — apply by ${iso(by)} · ${LABELS.owner[e.owner]}`);
  const waiting = rows.filter((r) => ['applied', 'in_review', 'blocked'].includes(r.e.access.status));
  console.log(`\nWaiting on vendors: ${waiting.length}`);
  for (const { e } of waiting) console.log(`  … ${e.name} — ${LABELS.status[e.access.status]} since ${e.access.applied_on}`);
  process.exit(0);
}

// ── Render README.md ─────────────────────────────────────────────────────────
const cell = (s) => String(s).replace(/\|/g, '\\|').replace(/\n/g, ' ');
const table = (head, rows) =>
  [`| ${head.join(' | ')} |`, `| ${head.map(() => '---').join(' | ')} |`, ...rows.map((r) => `| ${r.map(cell).join(' | ')} |`)].join('\n');
const count = (list, pick) => list.reduce((acc, x) => ((acc[pick(x)] = (acc[pick(x)] ?? 0) + 1), acc), {});
const sprintLabel = (n) => `S${n} (${nice(sprintStart(n))})`;
const updated = toDate(register.updated);

const entries = register.entries;
const integrations = entries.filter((e) => e.kind === 'integration');
const byKind = count(entries, (e) => e.kind);
const byLaunch = count(integrations, (e) => e.launch);
const byStatus = count(entries, (e) => e.access.status);

const actions = entries
  .filter(open)
  .map((e) => ({ e, by: applyBy(e) }))
  .sort((a, b) => a.by - b.by || a.e.access.needed_by_sprint - b.e.access.needed_by_sprint || a.e.name.localeCompare(b.e.name));

const late = actions.filter((a) => a.by <= updated && a.e.access.status === 'not_started').length;

const out = [];
out.push(`<!-- Generated from register.yaml by scripts/integrations-register.mjs. Do not edit; run \`pnpm integrations:render\`. -->

# Integrations register

Every integration, platform service, vendor account and outside approval REOS needs for the Texas pilot on **${nice(toDate(register.pilot))}**, with who owns it, how we get access, and when it must be filed. Source: [\`register.yaml\`](register.yaml) (schema: [\`register.schema.json\`](register.schema.json)). Updated ${nice(updated)}.

Credentials rules: [credentials policy](credentials-policy.md). Lead times are planning estimates until the vendor confirms.

## At a glance

${table(['', 'Count'], [
  ['Integrations users connect (catalogue)', `${integrations.length} (${byLaunch.enabled ?? 0} enabled, ${byLaunch.beta ?? 0} beta, ${byLaunch.coming_soon ?? 0} coming soon at the pilot)`],
  ['Platform services REOS runs on', byKind.platform_service ?? 0],
  ['Vendor accounts', byKind.account ?? 0],
  ['Outside approvals', byKind.approval ?? 0],
  ['Access approved', byStatus.approved ?? 0],
  ['Filed and waiting', (byStatus.applied ?? 0) + (byStatus.in_review ?? 0)],
  ['Blocked', byStatus.blocked ?? 0],
  ['Not started', byStatus.not_started ?? 0],
  [`Already past their apply-by date on ${nice(updated)}`, `**${late}**`],
])}

## Action list: file in this order

"Apply by" = the start of the sprint that needs it, minus the estimated lead time, or the planned date (marked †) when the sprint plan files it earlier because the vendor's approval time is unpredictable. ⚠ marks items whose apply-by date has already passed: file them first.

${table(['Apply by', 'Item', 'Owner', 'How', 'Lead (est.)', 'Needed by', 'Status'], actions.map(({ e, by }) => [
  `${by <= updated && e.access.status === 'not_started' ? '⚠ ' : ''}${nice(by)}${e.access.apply_by && by < latestApplyBy(e) ? ' †' : ''}`,
  `[${e.name}](#${e.id})`,
  LABELS.owner[e.owner],
  LABELS.route[e.access.route],
  `${e.access.lead_time_weeks} wk`,
  sprintLabel(e.access.needed_by_sprint),
  LABELS.status[e.access.status] + (e.access.applied_on ? ` (${e.access.applied_on})` : ''),
]))}

## Catalogue at the pilot

What users see in **Connections** on day one. Every row is also a back-office catalogue entry that can be paused, hidden per tenant, or rolled out by plan.

`);

const categories = [...new Set(integrations.map((e) => e.category))];
for (const category of categories) {
  out.push(`### ${LABELS.category[category]}\n`);
  out.push(table(['Integration', 'Pilot status', 'Built in', 'Connects with', 'Personal data', 'Access'], integrations.filter((e) => e.category === category).map((e) => [
    `[${e.name}](#${e.id})`, LABELS.launch[e.launch], `${e.build.item} · S${e.build.sprint}`, LABELS.credentials[e.credentials], e.personal_data ? 'Yes' : 'No', LABELS.status[e.access.status],
  ])));
  out.push('');
}

for (const [kind, title, intro] of [
  ['platform_service', 'Platform services', 'Services REOS runs on. Users never connect these themselves, so they have no catalogue entry.'],
  ['account', 'Vendor accounts', 'Accounts the build and release process needs.'],
  ['approval', 'Outside approvals', 'Regulatory and legal sign-offs with fixed lead times.'],
]) {
  out.push(`## ${title}\n\n${intro}\n`);
  out.push(table(['Item', 'Purpose', 'Owner', 'Needed by', 'Status'], entries.filter((e) => e.kind === kind).map((e) => [
    `[${e.name}](#${e.id})`, e.purpose, LABELS.owner[e.owner], sprintLabel(e.access.needed_by_sprint), LABELS.status[e.access.status],
  ])));
  out.push('');
}

out.push('## Details\n');
for (const e of entries) {
  out.push(`### ${e.name}\n<a id="${e.id}"></a>\n`);
  const lines = [
    `**${LABELS.kind[e.kind]}** · ${LABELS.category[e.category]} · built in ${e.build.item} (sprint ${e.build.sprint})${e.launch !== 'not_applicable' ? ` · pilot status **${LABELS.launch[e.launch]}**` : ''}`,
    '',
    e.purpose,
    '',
    `- **Owner:** ${LABELS.owner[e.owner]}`,
    `- **Access:** ${LABELS.route[e.access.route]}, about ${e.access.lead_time_weeks} week${e.access.lead_time_weeks === 1 ? '' : 's'}; needed by ${sprintLabel(e.access.needed_by_sprint)}; apply by ${nice(applyBy(e))}. Status: ${LABELS.status[e.access.status]}${e.access.applied_on ? `, applied ${e.access.applied_on}` : ''}${e.access.approved_on ? `, approved ${e.access.approved_on}` : ''}${e.access.reference ? ` (ref ${e.access.reference})` : ''}.`,
    `- **Sandbox:** ${e.sandbox}`,
    `- **Credentials:** ${LABELS.credentials[e.credentials]} · **Personal data:** ${e.personal_data ? 'yes (vendor needs a data-processing agreement)' : 'no'}`,
    `- **Cost:** ${e.cost}`,
  ];
  if (e.notes) lines.push(`- **Notes:** ${e.notes}`);
  if (e.fallback) lines.push(`- **Fallback:** ${e.fallback}`);
  if (e.sources?.length) lines.push(`- **Sources:** ${e.sources.map((u, i) => `[${i + 1}](${u})`).join(' · ')}`);
  out.push(lines.join('\n'), '');
}

const rendered = `${out.join('\n').replace(/\n{3,}/g, '\n\n').trimEnd()}\n`;

// ── Product catalogue (read by the platform's Integration Hub) ───────────────
// Only what the product needs; the register stays the place to edit.
const catalogue = `${JSON.stringify(
  {
    generated_from: 'docs/integrations/register.yaml',
    updated: register.updated,
    entries: entries
      .filter((e) => e.connection)
      .map((e) => ({
        id: e.id,
        name: e.name,
        kind: e.kind,
        category: e.category,
        purpose: e.purpose,
        launch: e.launch,
        build_sprint: e.build.sprint,
        owner: e.owner,
        access_status: e.access.status,
        personal_data: e.personal_data,
        sandbox: e.sandbox,
        connection: {
          auth: e.connection.auth,
          accounts: e.connection.accounts,
          fields: e.connection.fields.map((f) => ({
            key: f.key,
            secret: f.secret ?? false,
            required: f.required ?? true,
            kind: f.kind ?? 'text',
            default: f.default ?? null,
          })),
        },
      })),
  },
  null,
  2,
)}\n`;

if (mode === '--check') {
  let current = '';
  try {
    current = readFileSync(readmePath, 'utf8');
  } catch {
    // Missing README is reported below.
  }
  if (current !== rendered) fail('docs/integrations/README.md is out of date. Run `pnpm integrations:render` and commit it.');
  let currentCatalogue = '';
  try {
    currentCatalogue = readFileSync(cataloguePath, 'utf8');
  } catch {
    // Missing catalogue is reported below.
  }
  if (currentCatalogue !== catalogue) fail('apps/platform/resources/integrations/catalogue.json is out of date. Run `pnpm integrations:render` and commit it.');
  console.log(`✔ Integrations register valid (${entries.length} entries); README.md and the product catalogue are up to date.`);
} else if (mode === '--write') {
  writeFileSync(readmePath, rendered);
  mkdirSync(dirname(cataloguePath), { recursive: true });
  writeFileSync(cataloguePath, catalogue);
  console.log(`✔ Rendered docs/integrations/README.md (${entries.length} entries, ${actions.length} open actions) and the product catalogue.`);
} else {
  fail(`Unknown option ${mode}. Use --check, --status or no option.`);
}
