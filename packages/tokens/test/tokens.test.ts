/**
 * Design-token rules that must never regress: contrast, minimum text size,
 * touch targets, theme completeness, and generated files in sync.
 */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

import {
  contrastPairs,
  contrastRatio,
  fontSize,
  lineHeight,
  minTouchTarget,
  textScale,
  themes,
  type ThemeName,
} from '../src/index.ts';

const pkg = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const hex = /^#[0-9A-F]{6}$/i;

for (const name of Object.keys(themes) as ThemeName[]) {
  test(`${name} theme passes WCAG AA for every text and control pair`, () => {
    const t = themes[name];
    const failures = contrastPairs
      .map((p) => ({ ...p, ratio: contrastRatio(t[p.fg], t[p.bg]) }))
      .filter((p) => p.ratio < p.min)
      .map((p) => `${p.fg} on ${p.bg}: ${p.ratio.toFixed(2)} (needs ${p.min})`);
    assert.deepEqual(failures, []);
  });
}

test('every theme defines the same semantic tokens', () => {
  const keys = Object.keys(themes.light).sort();
  for (const name of Object.keys(themes) as ThemeName[]) {
    assert.deepEqual(Object.keys(themes[name]).sort(), keys, name);
  }
});

test('every colour except the scrim is a #RRGGBB value', () => {
  for (const [name, t] of Object.entries(themes)) {
    for (const [key, value] of Object.entries(t)) {
      if (key === 'scrim') continue;
      assert.match(value, hex, `${name}.${key}`);
    }
  }
});

test('no text is smaller than 14 and body text is 16', () => {
  assert.ok(Math.min(...Object.values(fontSize)) >= 14);
  assert.equal(fontSize.body, 16);
});

test('body line heights are at least 1.4 × the font size', () => {
  for (const key of ['caption', 'body', 'bodyLarge'] as const) {
    assert.ok(lineHeight[key] / fontSize[key] >= 1.4, key);
  }
});

test('touch targets are at least 48 points', () => {
  assert.ok(minTouchTarget >= 48);
});

test('text sizes only ever enlarge, from Standard upwards', () => {
  assert.equal(textScale.standard, 1);
  assert.ok(textScale.large > textScale.standard && textScale.largest > textScale.large);
});

test('contrast maths matches known WCAG values', () => {
  assert.equal(contrastRatio('#000000', '#FFFFFF').toFixed(1), '21.0');
  assert.equal(contrastRatio('#FFFFFF', '#FFFFFF').toFixed(1), '1.0');
  assert.equal(contrastRatio('#767676', '#FFFFFF').toFixed(2), '4.54');
});

test('generated CSS and swatches are up to date', () => {
  execFileSync(process.execPath, [resolve(pkg, 'scripts/build.ts'), '--check'], { stdio: 'pipe' });
});
