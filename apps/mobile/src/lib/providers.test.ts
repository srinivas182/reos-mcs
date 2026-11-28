import { chooseCategory, lowestPrice, requestCategory, saveAnswers, type ProviderCategory } from './providers';

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
