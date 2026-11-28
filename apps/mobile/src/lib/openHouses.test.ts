import { canSave, fromNow, pending, saveVisit, sendPending, withoutSent } from './openHouses';

const mockApi = { GET: jest.fn(), POST: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('expo-crypto', () => {
  let n = 0;
  return { randomUUID: () => `visit-${++n}` };
});

const visitor = { name: ' Rosa Lima ', email: 'rosa.lima@example.com', phone: ' ', has_agent: false, pre_approved: true, timeline: '3_months' as const, consent: true as const };

describe('open house sign-in on the phone (B14.3a)', () => {
  beforeEach(() => jest.clearAllMocks());

  it('needs a name, consent, and an email or a phone', () => {
    expect(canSave({ name: 'Rosa', email: '', phone: '832 555 0177', consent: true })).toBe(true);
    expect(canSave({ name: 'Rosa', email: ' ', phone: '', consent: true })).toBe(false);
    expect(canSave({ name: 'Rosa', email: 'r@example.com', phone: '', consent: false })).toBe(false);
    expect(canSave({ name: 'R', email: 'r@example.com', phone: '', consent: true })).toBe(false);
  });

  it('keeps sign-ins on the phone until the server has them, and sends each once', async () => {
    expect(saveVisit('h1', visitor, new Date('2026-11-28T19:10:00Z'))).toBe(1);
    expect(pending('h1')[0]).toMatchObject({ client_id: 'visit-1', name: 'Rosa Lima', phone: null, signed_in_at: '2026-11-28T19:10:00.000Z' });

    // No signal: nothing is lost.
    mockApi.POST.mockRejectedValueOnce(new Error('offline'));
    expect(await sendPending('h1')).toBe(1);
    mockApi.POST.mockResolvedValueOnce({ data: undefined, error: { message: 'Server error' } });
    expect(await sendPending('h1')).toBe(1);

    saveVisit('h1', { ...visitor, name: 'Ravi Patel', email: null, phone: '(713) 555-0101' });
    mockApi.POST.mockResolvedValueOnce({ data: { data: { added: 2, duplicates: 0 } } });
    expect(await sendPending('h1')).toBe(0);
    expect(mockApi.POST).toHaveBeenLastCalledWith('/open-houses/{openHouse}/visits', expect.objectContaining({ body: { visits: [expect.objectContaining({ client_id: 'visit-1' }), expect.objectContaining({ client_id: 'visit-2' })] } }));
    expect(pending('h1')).toEqual([]);
    expect(await sendPending('h1')).toBe(0);
  });

  it('drops only what was sent, and starts at the door for three hours', () => {
    const q = [
      { client_id: 'a', name: 'A' },
      { client_id: 'b', name: 'B' },
    ];
    expect(withoutSent(q, ['a'])).toEqual([{ client_id: 'b', name: 'B' }]);
    const { starts, ends } = fromNow(new Date('2026-11-28T19:00:00Z'));
    expect(ends.getTime() - starts.getTime()).toBe(3 * 3600_000);
  });
});
