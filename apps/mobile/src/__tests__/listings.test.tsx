import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import ListingsScreen from '@/app/deals/listings';
import { i18n } from '@/lib/i18n';
import type { Listing, ListingsOverview, Member } from '@/lib/listings';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const james: Member = { mls: 'mls-har', short_name: 'HAR', member_key: 'HAR-M1', name: 'James Miller', office: 'Lone Star Realty', office_city: 'Katy' };
const listing = (over: Partial<Listing> = {}): Listing => ({
  uuid: 'l1',
  mls: 'mls-har',
  short_name: 'HAR',
  listing_id: 'HAR4100137',
  status: 'active',
  role: 'list',
  address: '2418 Willow Bend Dr, Katy, TX 77494',
  city: 'Katy',
  price: 489000,
  close_price: null,
  beds: 4,
  baths: 3,
  area: 2850,
  photo: { url: 'https://reos.com/demo/listings/2.svg', caption: 'Front' },
  photos: 4,
  listed_on: '2026-11-11',
  days_on_market: 12,
  office: 'Lone Star Realty',
  open_house: { starts_at: '2026-11-26T19:00:00+00:00', ends_at: '2026-11-26T22:00:00+00:00', kind: 'public' },
  ...over,
});
const overview = (over: Partial<ListingsOverview> = {}): ListingsOverview => ({
  licensed: true,
  linked: [james],
  candidates: [],
  listings: [listing(), listing({ uuid: 'l4', status: 'closed', close_price: 331000, role: 'co_list', photo: null, open_house: null, address: '803 Magnolia Trace, Katy, TX 77449' })],
  sample: true,
  synced_at: '2026-11-24T15:45:00+00:00',
  ...over,
});

beforeEach(() => jest.clearAllMocks());

describe('My listings on the phone (B10.1)', () => {
  it('shows the listings with their status and role, marked as sample data', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: overview() }));
    await render(wrap(<ListingsScreen />));

    expect(await screen.findByText('$489,000')).toBeTruthy();
    expect(screen.getByText('Sold for $331,000')).toBeTruthy();
    expect(screen.getByText('Sample data')).toBeTruthy();
    expect(screen.getByText('Co-listing agent')).toBeTruthy();
    expect(screen.getAllByText('HAR #HAR4100137 · 12 days on market')).toHaveLength(2);
    expect(mockApi.GET).toHaveBeenCalledWith('/listings');
  });

  it('confirms the member record found by license', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: overview({ linked: [], listings: [], candidates: [james] }) }));
    mockApi.POST.mockResolvedValue(ok({ data: overview() }));
    await render(wrap(<ListingsScreen />));

    fireEvent.press(await screen.findByText('Yes, this is me'));
    expect(await screen.findByText('$489,000')).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/listings/members', { body: { mls: 'mls-har', member_key: 'HAR-M1' } });
  });

  it('unlinks with Undo', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: overview() }));
    mockApi.POST.mockResolvedValue(ok({ data: overview({ linked: [], listings: [], candidates: [james] }) }));
    await render(wrap(<ListingsScreen />));

    fireEvent.press(await screen.findByLabelText('Unlink HAR'));
    expect(await screen.findByText('Unlinked from HAR')).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/listings/members/unlink', { body: { mls: 'mls-har', member_key: 'HAR-M1' } });
  });

  it('asks for a license first', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: overview({ licensed: false, linked: [], listings: [], sample: false }) }));
    await render(wrap(<ListingsScreen />));

    fireEvent.press(await screen.findByText('Add a license'));
    expect(router.push).toHaveBeenCalledWith('/licences');
  });
});
