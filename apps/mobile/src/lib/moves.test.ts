import type { TFunction } from 'i18next';

import { cleanCode, moveLines } from './moves';

const t = ((key: string, values?: { count?: number }) => `${key}:${values?.count ?? ''}`) as unknown as TFunction;

describe('joining another brokerage (A14.3)', () => {
  it('reads an invite code typed any way', () => {
    expect(cleanCode('abcd efgh')).toBe('ABCD-EFGH');
    expect(cleanCode(' ABCD-EFGH ')).toBe('ABCD-EFGH');
    expect(cleanCode('abc')).toBe('ABC');
  });

  it('names what moves, stays and ends', () => {
    expect(
      moveLines(
        [
          { key: 'ce_hours', count: 7 },
          { key: 'licenses', count: 1 },
        ],
        t,
      ),
    ).toEqual(['moves.keys.ce_hours:7', 'moves.keys.licenses:1']);
  });
});
