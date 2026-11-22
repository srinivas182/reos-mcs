import { describe, expect, it } from 'vitest';

import { firstName, nameParts } from './person-name';

describe('person names', () => {
    it('greets by first name, skipping a title', () => {
        expect(firstName('Dr. Rachel Hughes')).toBe('Rachel');
        expect(firstName('Sofia Reyes')).toBe('Sofia');
        expect(firstName('Ms Linda Park')).toBe('Linda');
        expect(firstName('Dr.')).toBe('Dr.');
        expect(firstName(null)).toBeNull();
        expect(firstName('  ')).toBeNull();
    });

    it('keeps the rest of the name', () => {
        expect(nameParts('Dr. Rachel Hughes')).toEqual(['Rachel', 'Hughes']);
        expect(nameParts('Drew Barrett')).toEqual(['Drew', 'Barrett']);
    });
});
