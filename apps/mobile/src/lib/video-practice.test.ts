import type { TFunction } from 'i18next';

import { clock, fillerLine } from './video-practice';

const t = ((key: string, values?: Record<string, unknown>) => (values ? `${String(values.word)}×${String(values.count)}` : key)) as unknown as TFunction;

describe('video practice on the phone (A14.1b)', () => {
  it('shows the recording time as m:ss', () => {
    expect(clock(0)).toBe('0:00');
    expect(clock(45.7)).toBe('0:45');
    expect(clock(120)).toBe('2:00');
    expect(clock(-3)).toBe('0:00');
  });

  it('lists filler words, or says there were none', () => {
    expect(
      fillerLine(
        [
          { word: 'um', count: 3 },
          { word: 'basically', count: 1 },
        ],
        t,
      ),
    ).toBe('um×3 · basically×1');
    expect(fillerLine([], t)).toBe('videopractice.noFillers');
  });
});
