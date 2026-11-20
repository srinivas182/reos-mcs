#!/usr/bin/env node
/**
 * CI minutes report.
 *
 * Adds up GitHub Actions job time for one calendar month, the way GitHub bills
 * it (each job rounded up to a whole minute), split by workflow and by job.
 *
 *   GITHUB_TOKEN=... node scripts/ci-usage.mjs [YYYY-MM] [owner/repo]
 *
 * Defaults: the previous month and $GITHUB_REPOSITORY. Writes Markdown to
 * stdout, and to the job summary when run inside GitHub Actions.
 */
import { appendFileSync } from 'node:fs';

const token = process.env.GITHUB_TOKEN ?? process.env.GH_TOKEN;
const repo = process.argv[3] ?? process.env.GITHUB_REPOSITORY;

if (!token || !repo) {
  console.error('Set GITHUB_TOKEN and pass owner/repo (or set GITHUB_REPOSITORY).');
  process.exit(1);
}

const month = process.argv[2] ?? (() => {
  const d = new Date();
  d.setUTCDate(1);
  d.setUTCMonth(d.getUTCMonth() - 1);
  return d.toISOString().slice(0, 7);
})();

if (!/^\d{4}-\d{2}$/.test(month)) {
  console.error(`Month must look like 2026-12, got "${month}".`);
  process.exit(1);
}

const [year, mon] = month.split('-').map(Number);
const start = new Date(Date.UTC(year, mon - 1, 1));
const end = new Date(Date.UTC(year, mon, 1));
const range = `${start.toISOString().slice(0, 10)}..${new Date(end - 1).toISOString().slice(0, 10)}`;

async function api(path) {
  const res = await fetch(`https://api.github.com${path}`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/vnd.github+json',
      'X-GitHub-Api-Version': '2022-11-28',
    },
  });
  if (!res.ok) throw new Error(`${res.status} ${res.statusText} for ${path}`);
  return res.json();
}

async function* paged(path, key) {
  for (let page = 1; ; page++) {
    const body = await api(`${path}${path.includes('?') ? '&' : '?'}per_page=100&page=${page}`);
    yield* body[key];
    if (body[key].length < 100) return;
  }
}

const byWorkflow = new Map();
const byJob = new Map();
let runs = 0;
let total = 0;

for await (const run of paged(`/repos/${repo}/actions/runs?created=${range}`, 'workflow_runs')) {
  runs++;
  for await (const job of paged(`/repos/${repo}/actions/runs/${run.id}/jobs?filter=all`, 'jobs')) {
    if (!job.started_at || !job.completed_at) continue;
    const ms = new Date(job.completed_at) - new Date(job.started_at);
    if (ms <= 0) continue;
    const minutes = Math.ceil(ms / 60000);
    total += minutes;
    byWorkflow.set(run.name, (byWorkflow.get(run.name) ?? 0) + minutes);
    const key = `${run.name} › ${job.name}`;
    const entry = byJob.get(key) ?? { minutes: 0, count: 0 };
    entry.minutes += minutes;
    entry.count++;
    byJob.set(key, entry);
  }
}

const rows = (map, fmt) => [...map.entries()].sort((a, b) => (b[1].minutes ?? b[1]) - (a[1].minutes ?? a[1])).map(fmt).join('\n');

const report = `## CI minutes for ${month} (${repo})

**${total} billable minutes** across ${runs} workflow runs (Linux runners, each job rounded up to a whole minute).

| Workflow | Minutes |
| --- | ---: |
${rows(byWorkflow, ([name, m]) => `| ${name} | ${m} |`)}

| Job | Runs | Minutes | Average |
| --- | ---: | ---: | ---: |
${rows(byJob, ([name, e]) => `| ${name} | ${e.count} | ${e.minutes} | ${(e.minutes / e.count).toFixed(1)} |`)}
`;

console.log(report);
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, report);
