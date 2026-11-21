import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

import { compare, flatten, placeholders, toLaravel, type Tree } from '../scripts/lib.ts';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const load = (locale: string): Tree => JSON.parse(readFileSync(resolve(root, `src/locales/${locale}.json`), 'utf8')) as Tree;

test('Spanish is complete, with matching placeholders, and Laravel files are current', () => {
  execFileSync(process.execPath, [resolve(root, 'scripts/check.ts')], { stdio: 'pipe' });
});

test('flatten turns nested keys into dotted keys', () => {
  assert.deepEqual(flatten({ a: { b: 'x', c: { d: 'y' } }, e: 'z' }), { 'a.b': 'x', 'a.c.d': 'y', e: 'z' });
});

test('placeholders finds {{names}}, sorted', () => {
  assert.deepEqual(placeholders('Step {{ step }} of {{total}}'), ['step', 'total']);
  assert.deepEqual(placeholders('No placeholders'), []);
});

test('compare reports missing, extra, empty and placeholder problems', () => {
  const problems = compare(
    { greet: 'Hi {{name}}', bye: 'Bye', only: 'Only in English' },
    { greet: 'Hola {{nombre}}', bye: ' ', extra: 'Sobra' },
    'es',
  );
  assert.equal(problems.length, 4);
  assert.match(problems.join('\n'), /missing "only"/);
  assert.match(problems.join('\n'), /"bye" is empty/);
  assert.match(problems.join('\n'), /"greet" has placeholders \[nombre\] but English has \[name\]/);
  assert.match(problems.join('\n'), /"extra" doesn't exist in English/);
});

test('toLaravel converts {{name}} to :name and sorts keys', () => {
  assert.deepEqual(toLaravel({ 'b.x': 'Paso {{step}}', 'a.y': 'Hola' }), { 'a.y': 'Hola', 'b.x': 'Paso :step' });
});

test('language names are written in their own language in every locale', () => {
  for (const locale of ['en', 'es']) {
    const flat = flatten(load(locale));
    assert.equal(flat['appearance.languages.en.description'], 'Use REOS in English');
    assert.equal(flat['appearance.languages.es.description'], 'Use REOS en español');
  }
});

test('createI18n returns a working instance with a fallback to English', async () => {
  const { createI18n, toLocale } = await import('../src/index.ts');
  const es = createI18n('es');
  assert.equal(es.t('tour.stepOf', { step: 2, total: 5 }), 'Paso 2 de 5');
  assert.equal(createI18n('en').t('common.undo'), 'Undo');
  assert.equal(toLocale('es-MX'), 'es');
  assert.equal(toLocale('fr-FR'), 'en');
  assert.equal(toLocale(undefined), 'en');
});
