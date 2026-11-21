import { describe, expect, it } from 'vitest';
import { liveStateWording } from '@/lib/live-states';

describe('liveStateWording', () => {
    it('names the only live state', () => {
        expect(liveStateWording(['Texas'], 'en')).toEqual({
            single: 'Texas',
            list: 'Texas',
            count: 1,
        });
    });

    it('lists several states in the reader’s language, with no single state', () => {
        expect(liveStateWording(['Florida', 'Texas'], 'en')).toEqual({
            single: null,
            list: 'Florida and Texas',
            count: 2,
        });
        expect(
            liveStateWording(['Florida', 'Georgia', 'Texas'], 'es').list,
        ).toBe('Florida, Georgia y Texas');
    });

    it('has nothing to name when no state is live yet', () => {
        expect(liveStateWording([], 'en')).toEqual({
            single: null,
            list: '',
            count: 0,
        });
    });
});
