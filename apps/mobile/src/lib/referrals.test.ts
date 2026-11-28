import { blankReferral, canRefer, claimInvite, referralBody } from './referrals';

const mockApi = { GET: jest.fn(), POST: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const form = { ...blankReferral, toName: 'Dev Shah', toEmail: 'dev.shah@example.com', clientName: 'Elena Torres', clientPhone: '512 555 0101', area: ' Austin ' };

describe('referrals on the phone (B14.3b)', () => {
  it('needs the agent, the client, a way to reach them, and the client’s agreement', () => {
    expect(canRefer(form, true)).toBe(true);
    expect(canRefer(form, false)).toBe(false);
    expect(canRefer({ ...form, clientPhone: '' }, true)).toBe(false);
    expect(canRefer({ ...form, toEmail: 'dev' }, true)).toBe(false);
  });

  it('sends blanks as null and the brokers’ fee as a number', () => {
    expect(referralBody(form)).toEqual({
      to_name: 'Dev Shah',
      to_email: 'dev.shah@example.com',
      to_brokerage: null,
      client_name: 'Elena Torres',
      client_email: null,
      client_phone: '512 555 0101',
      kind: 'buyer',
      area: 'Austin',
      note: null,
      fee_percent: 25,
      client_agreed: true,
    });
    expect(referralBody({ ...form, fee: '' }).fee_percent).toBeNull();
  });

  it('sends the invite code in capitals, and shows why it can’t be used', async () => {
    mockApi.POST.mockResolvedValueOnce({ data: undefined, error: { errors: { code: ["That code can't be used."] } } });
    await expect(claimInvite(' k7m2xq9p ')).rejects.toThrow("That code can't be used.");
    expect(mockApi.POST).toHaveBeenCalledWith('/me/invite/claim', { body: { code: 'K7M2XQ9P' } });
  });
});
