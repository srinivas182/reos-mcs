import { areaBody, canAdd, sendUpdate, statRows } from './farming';

const mockApi = { GET: jest.fn(), POST: jest.fn(), DELETE: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const stats = { active: 4, pending: 1, new_listings: 3, sold: 4, median_price: 427000, median_days: 12, median_sold_price: null, months_supply: 3, from: '2026-08-23', to: '2026-11-23' };

describe('farming on the phone (B14.3c)', () => {
  it('reads one place field as a ZIP code or a city', () => {
    const form = { name: ' Cinco Ranch ', place: '77494', tag: 'Katy farm', address: '1200 Grand Pkwy, Katy, TX 77494' };
    expect(areaBody(form)).toEqual({ name: 'Cinco Ranch', city: null, postal_code: '77494', tag: 'Katy farm', mailing_address: '1200 Grand Pkwy, Katy, TX 77494' });
    expect(areaBody({ ...form, place: 'Katy' })).toMatchObject({ city: 'Katy', postal_code: null });
    expect(canAdd(form)).toBe(true);
    expect(canAdd({ ...form, address: 'Katy' })).toBe(false);
  });

  it('shows sale prices only when the MLS allows them', () => {
    const money = (n: number) => `$${n}`;
    expect(statRows(stats, money).map((r) => r.key)).not.toContain('medianSold');
    expect(statRows({ ...stats, median_sold_price: 455000 }, money)).toContainEqual({ key: 'medianSold', value: '$455000' });
  });

  it('shows why the server refused to send', async () => {
    mockApi.POST.mockResolvedValueOnce({ data: undefined, error: { errors: { body: ["The update can't be sent until the compliance issues are fixed."] } } });
    await expect(sendUpdate('a1', 'Katy update', 'Body')).rejects.toThrow("The update can't be sent until the compliance issues are fixed.");
  });
});
