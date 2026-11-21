import { describe, expect, it } from 'vitest';
import {
    contrastWithWhite,
    MIN_CONTRAST,
    normaliseHex,
    palette,
    readableShade,
} from '@/lib/colour';

describe('brand colours (same rule as the server)', () => {
    it('measures contrast with white text', () => {
        expect(contrastWithWhite('#000000')).toBeCloseTo(21, 1);
        expect(contrastWithWhite('#FFFFFF')).toBeCloseTo(1, 1);
        expect(contrastWithWhite('#2952CC')).toBeGreaterThan(MIN_CONTRAST);
    });

    it('suggests the nearest darker shade that reads well', () => {
        const shade = readableShade('#7FB3FF');
        expect(contrastWithWhite(shade)).toBeGreaterThanOrEqual(MIN_CONTRAST);
        expect(readableShade('#1E3A8A')).toBe('#1E3A8A');
    });

    it('accepts hex with or without #, and builds the pressed and tint shades', () => {
        expect(normaliseHex('1e3a8a')).toBe('#1E3A8A');
        expect(normaliseHex('blue')).toBeNull();
        expect(palette('#1E3A8A')).toEqual({
            action: '#1E3A8A',
            pressed: '#193071',
            tint: '#E9EBF3',
        });
    });
});
