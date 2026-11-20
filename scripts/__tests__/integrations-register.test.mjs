/**
 * Tests for scripts/integrations-register.mjs (run with `pnpm test:scripts`).
 * Each test copies the real register into a temp folder, changes it, and runs the CLI.
 */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const script = join(root, 'scripts/integrations-register.mjs');
const source = join(root, 'docs/integrations');

function workspace(edit = (yaml) => yaml) {
  const dir = mkdtempSync(join(tmpdir(), 'reos-register-'));
  copyFileSync(join(source, 'register.schema.json'), join(dir, 'register.schema.json'));
  writeFileSync(join(dir, 'register.yaml'), edit(readFileSync(join(source, 'register.yaml'), 'utf8')));
  return dir;
}

function run(dir, ...args) {
  try {
    const stdout = execFileSync(process.execPath, [script, ...args], { env: { ...process.env, REGISTER_DIR: dir }, encoding: 'utf8', stdio: 'pipe' });
    return { ok: true, out: stdout };
  } catch (error) {
    return { ok: false, out: `${error.stdout}${error.stderr}` };
  }
}

test('the committed register is valid and its README is up to date', () => {
  const result = run(source, '--check');
  assert.ok(result.ok, result.out);
});

test('renders a README with the action list and every entry', () => {
  const dir = workspace();
  assert.ok(run(dir).ok);
  const readme = readFileSync(join(dir, 'README.md'), 'utf8');
  assert.match(readme, /## Action list: file in this order/);
  assert.match(readme, /<a id="stripe"><\/a>/);
  assert.match(readme, /⚠ 9 Nov 2026/, 'items past their apply-by date are flagged');
});

test('rendering ignores today\'s date, so CI is deterministic', () => {
  const a = workspace();
  const b = workspace();
  run(a);
  run(b);
  assert.equal(readFileSync(join(a, 'README.md'), 'utf8'), readFileSync(join(b, 'README.md'), 'utf8'));
});

test('--check fails when the README is stale', () => {
  const dir = workspace();
  run(dir);
  writeFileSync(join(dir, 'README.md'), 'edited by hand\n');
  const result = run(dir, '--check');
  assert.equal(result.ok, false);
  assert.match(result.out, /out of date/);
});

test('rejects duplicate ids', () => {
  const dir = workspace((y) => y.replace('  - id: paypal\n', '  - id: stripe\n'));
  const result = run(dir, '--check');
  assert.equal(result.ok, false);
  assert.match(result.out, /duplicate id "stripe"/);
});

test('rejects unknown values with the allowed list', () => {
  const dir = workspace((y) => y.replace('    launch: beta\n', '    launch: soon\n'));
  const result = run(dir, '--check');
  assert.equal(result.ok, false);
  assert.match(result.out, /\[linkedin\].*enabled, beta, coming_soon/s);
});

test('an approved item must say when it was applied for and approved', () => {
  const dir = workspace((y) => y.replace('      applied_on: 2026-11-19\n      approved_on: 2026-11-20\n', ''));
  const result = run(dir, '--check');
  assert.equal(result.ok, false);
  assert.match(result.out, /\[github\].*applied_on/s);
});

test('access cannot be needed after the sprint that builds it', () => {
  const dir = workspace((y) => y.replace(/(  - id: stripe\n[\s\S]*?needed_by_sprint: )5/, '$19'));
  const result = run(dir, '--check');
  assert.equal(result.ok, false);
  assert.match(result.out, /\[stripe\] access is needed by sprint 9 but the build is in sprint 5/);
});

test('services users never connect cannot appear in the catalogue', () => {
  const dir = workspace((y) => y.replace(/(  - id: mux\n[\s\S]*?launch: )not_applicable/, '$1enabled'));
  const result = run(dir, '--check');
  assert.equal(result.ok, false);
  assert.match(result.out, /\[mux\]/);
});

test('--status reports late items using today\'s date', () => {
  const result = run(source, '--status');
  assert.ok(result.ok, result.out);
  assert.match(result.out, /Late \(should already be filed\)/);
});
