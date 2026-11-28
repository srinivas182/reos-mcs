import { claimLead, minutesLeft, signSplit } from './team';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

describe('teams on the phone (A17.3)', () => {
  it('claims a lead, says when it was taken, and counts the minutes left', async () => {
    mockApi.POST.mockResolvedValueOnce({ data: { data: { id: 'l-1', status: 'claimed' } } });
    await claimLead('l-1');
    expect(mockApi.POST).toHaveBeenLastCalledWith('/team/leads/{offer}/claim', { params: { path: { offer: 'l-1' } } });

    mockApi.POST.mockResolvedValueOnce({ error: { errors: { lead: ['Another agent claimed this lead first.'] } } });
    await expect(claimLead('l-2')).rejects.toThrow('Another agent claimed this lead first.');

    mockApi.POST.mockResolvedValueOnce({ data: { data: { id: 's-1', status: 'signed' } } });
    await signSplit('s-1');
    expect(minutesLeft('2026-11-23T16:05:00Z', new Date('2026-11-23T16:01:30Z'))).toBe(4);
    expect(minutesLeft(null)).toBe(0);
  });
});
