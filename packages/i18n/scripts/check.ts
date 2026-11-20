/**
 * Checks every language against English and keeps Laravel's copies current.
 *
 *   node scripts/check.ts          check (CI): fail on missing, extra, empty or
 *                                  placeholder-mismatched strings, or stale Laravel files
 *   node scripts/check.ts --write  check, then write apps/platform/lang/{locale}.json
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { compare, flatten, toLaravel, type Tree } from './lib.ts';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const localesDir = resolve(root, 'src/locales');
const laravelDir = resolve(root, '../../apps/platform/lang');
const locales = ['en', 'es'] as const;
const write = process.argv.includes('--write');

const read = (locale: string): Record<string, string> =>
  flatten(JSON.parse(readFileSync(resolve(localesDir, `${locale}.json`), 'utf8')) as Tree);

const source = read('en');
const problems = locales.filter((l) => l !== 'en').flatMap((locale) => compare(source, read(locale), locale));

if (problems.length) {
  console.error(`✘ Translation problems (${problems.length}):\n  ${problems.join('\n  ')}`);
  process.exit(1);
}

const stale: string[] = [];
for (const locale of locales) {
  const path = resolve(laravelDir, `${locale}.json`);
  const content = `${JSON.stringify(toLaravel(read(locale)), null, 4)}\n`;
  if (write) {
    writeFileSync(path, content);
    continue;
  }
  let current = '';
  try {
    current = readFileSync(path, 'utf8');
  } catch {
    // reported below
  }
  if (current !== content) stale.push(`apps/platform/lang/${locale}.json`);
}

if (stale.length) {
  console.error(`✘ Out of date: ${stale.join(', ')}. Run 'pnpm --filter @reos/i18n build' and commit.`);
  process.exit(1);
}

console.log(`✔ ${Object.keys(source).length} strings, complete in ${locales.join(' and ')}${write ? '; Laravel files written' : ''}.`);
