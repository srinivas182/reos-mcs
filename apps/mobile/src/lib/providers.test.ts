import { acceptRules, addArea, areaParts, chooseCategory, lowestPrice, nextStep, requestCategory, saveAnswers, startPlan, type Onboarding, type ProviderCategory } from './providers';

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
