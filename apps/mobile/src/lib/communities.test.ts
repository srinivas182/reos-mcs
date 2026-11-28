import { inviteFrom, joinCommunity, postTo, rsvp } from './communities';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

describe('communities on the phone (B17.2)', () => {
  it('joins from a QR code, RSVPs, and shows why a post was turned down', async () => {
    expect(inviteFrom('https://reos.com/join/community/k3v9q2m7tz?via=qr')).toEqual({ code: 'k3v9q2m7tz', via: 'qr' });
    expect(inviteFrom('https://reos.com/join/community/k3v9q2m7tz')).toEqual({ code: 'k3v9q2m7tz', via: 'link' });
    expect(inviteFrom('https://reos.com/deals')).toBeNull();

    mockApi.POST.mockResolvedValueOnce({ data: { data: { id: 'c-1' } } });
    await expect(joinCommunity('k3v9q2m7tz', 'qr')).resolves.toBe('c-1');
    expect(mockApi.POST).toHaveBeenLastCalledWith('/communities/join/{code}', { params: { path: { code: 'k3v9q2m7tz' } }, body: { via: 'qr' } });

    mockApi.POST.mockResolvedValueOnce({ data: { data: { status: 'held', checkout_url: 'https://reos.com/checkout/9' } } });
    await expect(rsvp('s-1')).resolves.toEqual({ status: 'held', checkout_url: 'https://reos.com/checkout/9' });

    mockApi.POST.mockResolvedValueOnce({ error: { errors: { body: ['Communities can\'t be used to pay for referrals: remove "referral fee".'] } } });
    await expect(postTo('c-1', 'referral fee')).rejects.toThrow('remove "referral fee"');
  });
});
