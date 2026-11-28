import {
  briefClear,
  type BrokerBrief,
  loadBrief,
  loadClosings,
  dueRows,
  remindAllDue,
  type CeTable,
  loadBrokerView,
  needingHelp,
  type OversightBoard,
  checkRule,
  chooseSeats,
  clearItem,
  detailText,
  firstOpen,
  inviteAll,
  itemsFor,
  lookupLicence,
  ready,
  type Approvals,
} from './brokerage';

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

describe('brokerage rules on the phone (A15.2)', () => {
  it('checks words against TREC only once there are enough of them', async () => {
    mockApi.POST.mockClear();
    await expect(checkRule('Hi')).resolves.toBeNull();
    expect(mockApi.POST).not.toHaveBeenCalled();
    mockApi.POST.mockResolvedValueOnce({ data: { data: { conflict: { rule: 'brokerage.compensation', citation: '22 TAC §535.3', url: null, excerpt: 'x', rule_text: 'y' } } } });
    await expect(checkRule('Agents may accept fees directly.')).resolves.toMatchObject({ citation: '22 TAC §535.3' });
  });
});

describe('approvals on the phone (A15.3)', () => {
  const queue = {
    brokerage: 'Lone Star Realty',
    scope: 'brokerage',
    team: null,
    fallback_hours: 4,
    can: { posts: true, questions: true, settings: true },
    counts: { posts: 1, pages: 0, questions: 1, overdue: 1 },
    items: [
      { kind: 'post', id: 'p1', agent: 'James Miller', summary: 'Open house Saturday.', waiting_since: null, hours: 7, overdue: true, detail: { body: 'Open house Saturday.' } },
      { kind: 'question', id: 'q1', agent: 'James Miller', summary: 'Can I pay a referral fee?', waiting_since: null, hours: 0, overdue: false, detail: { question: 'Can I pay a referral fee?', answer: '' } },
    ],
  } as Approvals;

  it("filters the queue and reads an item's detail", () => {
    expect(itemsFor(queue, 'question').map((i) => i.id)).toEqual(['q1']);
    expect(itemsFor(queue, 'all')).toHaveLength(2);
    expect(detailText(queue.items[0], 'body')).toBe('Open house Saturday.');
    expect(detailText(queue.items[1], 'answer')).toBeNull();
  });

  it('answers a question into the knowledge, and sends a note back trimmed', async () => {
    mockApi.POST.mockResolvedValueOnce({ data: { data: { ...queue, items: [] } } });
    await expect(clearItem(queue.items[1], 'answer', ' No, through the brokerage. ', true)).resolves.toMatchObject({ items: [] });
    expect(mockApi.POST).toHaveBeenLastCalledWith('/organisation/approvals/{kind}/{item}', { params: { path: { kind: 'question', item: 'q1' } }, body: { action: 'answer', text: 'No, through the brokerage.', add_to_knowledge: true } });

    mockApi.POST.mockResolvedValueOnce({ data: undefined, error: { errors: { text: ['Add a note so the agent knows what to change.'] } } });
    await expect(clearItem(queue.items[0], 'return', '', true)).rejects.toThrow('Add a note so the agent knows what to change.');
    expect(mockApi.POST).toHaveBeenLastCalledWith('/organisation/approvals/{kind}/{item}', { params: { path: { kind: 'post', item: 'p1' } }, body: { action: 'return', text: '', add_to_knowledge: false } });
  });
});

describe('agents and deals on the phone (A16.1)', () => {
  it('lists agents with a real signal, not just new ones', async () => {
    const board = {
      agents: [
        { id: 1, signals: ['new'] },
        { id: 2, signals: ['quiet', 'new'] },
        { id: 3, signals: [] },
      ],
    } as unknown as OversightBoard;
    expect(needingHelp(board).map((a) => a.id)).toEqual([2]);
    mockApi.GET.mockResolvedValueOnce({ data: { data: null } });
    await expect(loadBrokerView()).resolves.toBeNull();
    expect(mockApi.GET).toHaveBeenLastCalledWith('/me/broker-view');
  });
});

describe('CE compliance on the phone (A16.2)', () => {
  it('counts who a reminder reaches, and reminds them in one call', async () => {
    const table = {
      rows: [
        { id: 1, status: 'behind' },
        { id: 2, status: 'on_track' },
        { id: 3, status: 'overdue' },
        { id: 4, status: 'due' },
      ],
    } as unknown as CeTable;
    expect(dueRows(table).map((r) => r.id)).toEqual([1, 3, 4]);
    mockApi.POST.mockResolvedValueOnce({ data: { data: { sent: 3, skipped: 0, table } } });
    await expect(remindAllDue()).resolves.toMatchObject({ sent: 3 });
    expect(mockApi.POST).toHaveBeenLastCalledWith('/organisation/ce/remind');
  });
});

describe('reports and the morning brief on the phone (A16.3)', () => {
  it('loads closings for a period and the brief, and knows a clear morning', async () => {
    mockApi.GET.mockResolvedValueOnce({ data: { data: { period: 'last_month', totals: { count: 1, volume: 389000 } } } });
    await expect(loadClosings('last_month')).resolves.toMatchObject({ totals: { count: 1 } });
    expect(mockApi.GET).toHaveBeenLastCalledWith('/organisation/reports/closings', { params: { query: { period: 'last_month' } } });

    const brief = { need_help: { count: 0 }, at_risk: { count: 0 }, approvals: { waiting: 0 }, ce_due: null } as unknown as BrokerBrief;
    expect(briefClear(brief)).toBe(true);
    expect(briefClear({ ...brief, ce_due: 2 })).toBe(false);
    mockApi.GET.mockResolvedValueOnce({ data: { data: brief } });
    await expect(loadBrief()).resolves.toBe(brief);
    expect(mockApi.GET).toHaveBeenLastCalledWith('/organisation/brief');
  });
});
