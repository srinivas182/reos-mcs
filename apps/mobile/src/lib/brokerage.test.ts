import { chooseSeats, firstOpen, inviteAll, lookupLicence, ready } from './brokerage';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const found = {
  licence: { number: '9033333', name: 'Grace Hill', status: '20', active: true, expires_on: '2027-08-31' },
  name_matches: true,
  brokerages: [
    { number: '0600999', name: 'Old Brokerage LLC', kind: 'entity' as const, sponsored: 4, on_reos: true },
    { number: '0600123', name: 'Bayou City Homes LLC', kind: 'entity' as const, sponsored: 2, on_reos: false },
  ],
};

describe('brokerage setup on the phone (A15.1)', () => {
  beforeEach(() => jest.clearAllMocks());

  it('picks the first brokerage not already on REOS, and trims the license', async () => {
    expect(firstOpen(found)).toBe('0600123');
    mockApi.POST.mockResolvedValueOnce({ data: { data: found } });
    await lookupLicence(' 9033333 ');
    expect(mockApi.POST).toHaveBeenCalledWith('/brokerage/verify/lookup', { body: { licence: '9033333' } });
  });

  it('counts who can be invited, and shows why an action was refused', async () => {
    expect(ready({ trec_number: '0600123', counts: { sponsored: 5, member: 1, invited: 1, on_reos: 1, contact: 1, needs_contact: 1 }, agents: [] })).toBe(2);
    mockApi.POST.mockResolvedValueOnce({ data: undefined, error: { errors: { seats: ['Only 1 seat left.'] } } });
    await expect(inviteAll()).rejects.toThrow('Only 1 seat left.');
    mockApi.PUT.mockResolvedValueOnce({ data: { data: { payer: 'split' } } });
    await expect(chooseSeats('split', false)).resolves.toEqual({ payer: 'split' });
    expect(mockApi.PUT).toHaveBeenCalledWith('/organisation/seats', { body: { payer: 'split', credits_pooled: false } });
  });
});
