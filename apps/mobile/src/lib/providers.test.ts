import {
  declineRequest,
  sharingState,
  shareContacts,
  type ProviderConnection,
  askToConnect,
  loadAgentsInArea,
  usageShare,
  acceptRules,
  addArea,
  areaParts,
  chooseCategory,
  loadProviders,
  lowestPrice,
  nextStep,
  requestCategory,
  saveAnswers,
  saveProfile,
  splitList,
  startPlan,
  type Onboarding,
  type ProviderCategory,
} from './providers';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const stager: ProviderCategory = {
  key: 'home-stager',
  name: 'Home stager',
  description: null,
  licence: { kind: 'business', label: 'Photo ID and business details' },
  fields: [{ key: 'styles', label: 'Styles', type: 'list', options: ['Modern', 'Farmhouse'] }],
  areas_on_pro: 3,
  prices: { basic: 2900, pro: 7900, premium: null },
};

describe('joining the provider network on the phone (B15.1)', () => {
  beforeEach(() => jest.clearAllMocks());

  it('shows the lowest priced plan, or none', () => {
    expect(lowestPrice(stager)).toBe(2900);
    expect(lowestPrice({ ...stager, prices: { basic: null, pro: null, premium: null } })).toBeNull();
  });

  it('chooses a category and saves answers without blanks', async () => {
    mockApi.PUT.mockResolvedValue({ data: { data: { category: stager, fields: {}, missing: ['styles'] } } });
    await chooseCategory('home-stager');
    expect(mockApi.PUT).toHaveBeenCalledWith('/provider/profile/category', { body: { category: 'home-stager' } });

    await saveAnswers({ styles: ['Modern', ' '], rooms: ' ', insured: false, notes: ' Staged 40 homes ' });
    expect(mockApi.PUT).toHaveBeenLastCalledWith('/provider/profile/fields', { body: { fields: { styles: ['Modern'], insured: false, notes: 'Staged 40 homes' } } });
  });

  it('asks for a category and shows why it was refused', async () => {
    mockApi.POST.mockResolvedValueOnce({ data: undefined, error: { errors: { name: ['Loan officer is already listed. Choose it above.'] } } });
    await expect(requestCategory('Loan officer', '')).rejects.toThrow('Loan officer is already listed. Choose it above.');
    expect(mockApi.POST).toHaveBeenCalledWith('/provider/categories/requests', { body: { name: 'Loan officer', note: null } });
  });
});

describe('the joining steps on the phone (B15.2)', () => {
  beforeEach(() => jest.clearAllMocks());

  const onboarding = {
    status: 'joining',
    hidden_reason: null,
    steps: [
      { key: 'welcome', done: true },
      { key: 'category', done: true },
      { key: 'licence', done: false },
      { key: 'areas', done: false },
      { key: 'plan', done: false },
      { key: 'rules', done: false },
    ],
    done: 2,
    areas: [{ id: 3, kind: 'radius', city: null, postal_code: '77494', radius_miles: 15, agents: 4, providers: 0 }],
    rules: { version: '2026-11-30', keys: [], accepted_at: null, current: false },
  } as unknown as Onboarding;

  it('finds the next step and reads an area', () => {
    expect(nextStep(onboarding)).toBe('licence');
    expect(nextStep({ ...onboarding, steps: onboarding.steps.map((s) => ({ ...s, done: true })) })).toBeNull();
    expect(areaParts(onboarding.areas[0])).toEqual({ kind: 'radius', value: '77494', miles: 15 });
  });

  it('adds an area, starts the plan on reos.com and accepts the rules at their version', async () => {
    mockApi.POST.mockResolvedValueOnce({ data: { data: onboarding } });
    await addArea({ kind: 'city', city: 'Katy' });
    expect(mockApi.POST).toHaveBeenLastCalledWith('/provider/join/areas', { body: { kind: 'city', city: 'Katy', postal_code: null, radius_miles: null } });

    mockApi.POST.mockResolvedValueOnce({ data: { data: { checkout_url: 'https://reos.com/checkout/12?signup=abc' } } });
    await expect(startPlan('pro')).resolves.toBe('https://reos.com/checkout/12?signup=abc');

    mockApi.POST.mockResolvedValueOnce({ data: { data: onboarding } });
    await acceptRules('2026-11-30');
    expect(mockApi.POST).toHaveBeenLastCalledWith('/provider/join/rules', { body: { version: '2026-11-30', accept: true } });
  });
});

describe('the provider profile on the phone (B15.3)', () => {
  beforeEach(() => jest.clearAllMocks());

  it('splits lists and shows why a save was refused', async () => {
    expect(splitList(' FHA loans, VA loans ,, ')).toEqual(['FHA loans', 'VA loans']);
    mockApi.PUT.mockResolvedValueOnce({ data: undefined, error: { errors: { bio: ['Take out phone numbers, emails and web addresses. Agents reach you through REOS.'] } } });
    await expect(saveProfile({ bio: 'Call 281 555 0142', bio_es: null, services: [], specialities: [], response_hours: 2, resources: [], sessions: [] })).rejects.toThrow('Take out phone numbers');
  });

  it('finds verified providers by place', async () => {
    mockApi.GET.mockResolvedValueOnce({ data: { data: [] } });
    await loadProviders(undefined, 'Katy');
    expect(mockApi.GET).toHaveBeenCalledWith('/providers', { params: { query: { category: undefined, place: 'Katy' } } });
  });
});

describe('agents in my areas on the phone (B16.1)', () => {
  it('loads agents by level and sends a request with a trimmed note', async () => {
    mockApi.GET.mockResolvedValueOnce({ data: { data: { agents: [], usage: { limit: 5, used: 3, left: 2 } } } });
    await expect(loadAgentsInArea('top')).resolves.toMatchObject({ usage: { left: 2 } });
    expect(mockApi.GET).toHaveBeenLastCalledWith('/provider/agents', { params: { query: { level: 'top' } } });

    mockApi.POST.mockResolvedValueOnce({ data: { data: { id: 'r-1', status: 'pending' }, usage: { limit: 5, used: 4, left: 1 } } });
    await expect(askToConnect(11, '  Happy to help.  ')).resolves.toMatchObject({ id: 'r-1' });
    expect(mockApi.POST).toHaveBeenLastCalledWith('/provider/requests', { body: { agent_id: 11, message: 'Happy to help.' } });

    mockApi.POST.mockResolvedValueOnce({ error: { errors: { limit: ["You've used all 5 requests for this month."] } } });
    await expect(askToConnect(12, '')).rejects.toThrow("You've used all 5 requests for this month.");
    expect(usageShare({ tier: 'basic', limit: 5, used: 4, left: 1, resets_on: '2026-12-01' })).toBeCloseTo(0.8);
  });
});

describe('the private network on the phone (B16.2)', () => {
  it('shares contacts and knows whose turn it is', async () => {
    const base = { id: 'c-1', i_shared: false, they_shared: false, contacts: null } as unknown as ProviderConnection;
    expect(sharingState(base)).toBe('none');
    expect(sharingState({ ...base, they_shared: true })).toBe('waiting_me');
    expect(sharingState({ ...base, i_shared: true })).toBe('waiting_them');
    expect(sharingState({ ...base, i_shared: true, they_shared: true, contacts: { email: 'maya@chenlending.example', phone: null } })).toBe('both');

    mockApi.PUT.mockResolvedValueOnce({ data: { data: { ...base, i_shared: true } } });
    await expect(shareContacts('c-1', true)).resolves.toMatchObject({ i_shared: true });
    expect(mockApi.PUT).toHaveBeenLastCalledWith('/provider-connections/{providerRequest}/share', { params: { path: { providerRequest: 'c-1' } }, body: { share: true } });

    mockApi.POST.mockResolvedValueOnce({ data: { declined: true } });
    await declineRequest('r-1', true);
    expect(mockApi.POST).toHaveBeenLastCalledWith('/me/provider-requests/{providerRequest}/decline', { params: { path: { providerRequest: 'r-1' } }, body: { block: true } });
  });
});
