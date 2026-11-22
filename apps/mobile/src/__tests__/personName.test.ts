import { initialsOf, nameParts } from '@/lib/personName';

describe('person names', () => {
  it('skips a courtesy title for initials', () => {
    expect(initialsOf('Dr. Rachel Hughes')).toBe('RH');
    expect(initialsOf('James Miller')).toBe('JM');
    expect(initialsOf('Sofia')).toBe('S');
    expect(initialsOf('Ana María de la Cruz')).toBe('AC');
    expect(nameParts('Drew Barrett')).toEqual(['Drew', 'Barrett']);
  });
});
