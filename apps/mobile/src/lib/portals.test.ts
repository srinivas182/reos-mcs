import { unshared } from './portals';

describe('sharing a deal with a client (B14.2)', () => {
  it('offers the parties with an email who have no link yet', () => {
    const parties = [
      { name: 'Priya Shah', email: 'Priya.Shah@example.com' },
      { name: 'Ravi Shah', email: 'ravi@example.com' },
      { name: 'Title company', email: null },
    ];

    expect(unshared(parties, [{ email: 'priya.shah@example.com' }]).map((p) => p.name)).toEqual(['Ravi Shah']);
    expect(unshared(parties, [])).toHaveLength(2);
  });
});
