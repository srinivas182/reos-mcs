import assert from 'node:assert/strict';
import { test } from 'node:test';
import phrases from '../src/phrases.json' with { type: 'json' };
import { type Language, parse } from '../src/index.ts';

for (const sample of phrases.cases) {
    test(`${sample.language}: ${sample.text}`, () => {
        assert.deepEqual(parse(sample.text, sample.language as Language, phrases.now), sample.expect);
    });
}
