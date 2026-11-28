import { areasFrom } from './listings';

describe('the digital card areas (B14.1)', () => {
  it('reads areas typed in one field: six at most, no blanks or repeats', () => {
    expect(areasFrom('Katy, Cinco Ranch, , Katy')).toEqual(['Katy', 'Cinco Ranch']);
    expect(areasFrom('a,b,c,d,e,f,g')).toHaveLength(6);
    expect(areasFrom('   ')).toEqual([]);
  });
});
