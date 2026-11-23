import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import CmaScreen from '@/app/deals/listings/[uuid]/cma';
import ListingPostScreen from '@/app/deals/listings/[uuid]/post';
import ListingsScreen from '@/app/deals/listings/index';
import { i18n } from '@/lib/i18n';
import type { Cma, Listing, ListingsOverview, Member, PostDraft } from '@/lib/listings';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: () => ({ uuid: 'l1' }),
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
  postal_code: '77494',
  price: 489000,
  close_price: null,
  close_date: null,
  beds: 4,
  baths: 3,
  area: 2850,
  price_per_sqft: 172,
  photo: { url: 'https://reos.com/demo/listings/2.svg', caption: 'Front' },
  photos: [{ url: 'https://reos.com/demo/listings/2.svg', caption: 'Front' }],
  listed_on: '2026-11-11',
  days_on_market: 12,
  office: 'Lone Star Realty',
  latitude: 29.78,
  longitude: -95.82,
  attribution: 'Listing courtesy of Lone Star Realty. Source: HAR MLS.',
  logo_url: null,
  sample: true,
  can_post: true,
  open_house: { starts_at: '2026-11-26T19:00:00+00:00', ends_at: '2026-11-26T22:00:00+00:00', kind: 'public' },
  ...over,
});
const overview = (over: Partial<ListingsOverview> = {}): ListingsOverview => ({
  licensed: true,
  linked: [james],
  candidates: [],
  listings: [listing(), listing({ uuid: 'l4', status: 'closed', close_price: 331000, role: 'co_list', photo: null, photos: [], can_post: false, open_house: null, address: '803 Magnolia Trace, Katy, TX 77449' })],
  sample: true,
  synced_at: '2026-11-24T15:45:00+00:00',
  ...over,
});

const page = { handle: 'james-miller', published: true, bio: null, url: 'https://reos.com/agent/james-miller' };
/** GET answers by path: the public page's settings, or `data` for everything else. */
const answer = (data: unknown) => (path: string) => Promise.resolve(ok({ data: path === '/listings/page' ? page : data }));

beforeEach(() => jest.clearAllMocks());

describe('My listings on the phone (B10.1)', () => {
  it('shows the listings with their status and role, marked as sample data', async () => {
    mockApi.GET.mockImplementation(answer(overview()));
    await render(wrap(<ListingsScreen />));

    expect(await screen.findByText('$489,000')).toBeTruthy();
    expect(screen.getByText('Sold for $331,000')).toBeTruthy();
    expect(screen.getByText('Sample data')).toBeTruthy();
    expect(screen.getByText('Co-listing agent')).toBeTruthy();
    expect(screen.getAllByText('HAR #HAR4100137 · 12 days on market')).toHaveLength(2);
    expect(mockApi.GET).toHaveBeenCalledWith('/listings');
  });

  it('confirms the member record found by license', async () => {
    mockApi.GET.mockImplementation(answer(overview({ linked: [], listings: [], candidates: [james] })));
    mockApi.POST.mockResolvedValue(ok({ data: overview() }));
    await render(wrap(<ListingsScreen />));

    fireEvent.press(await screen.findByText('Yes, this is me'));
    expect(await screen.findByText('$489,000')).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/listings/members', { body: { mls: 'mls-har', member_key: 'HAR-M1' } });
  });

  it('unlinks with Undo', async () => {
    mockApi.GET.mockImplementation(answer(overview()));
    mockApi.POST.mockResolvedValue(ok({ data: overview({ linked: [], listings: [], candidates: [james] }) }));
    await render(wrap(<ListingsScreen />));

    fireEvent.press(await screen.findByLabelText('Unlink HAR'));
    expect(await screen.findByText('Unlinked from HAR')).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/listings/members/unlink', { body: { mls: 'mls-har', member_key: 'HAR-M1' } });
  });

  it('asks for a license first', async () => {
    mockApi.GET.mockImplementation(answer(overview({ licensed: false, linked: [], listings: [], sample: false })));
    await render(wrap(<ListingsScreen />));

    fireEvent.press(await screen.findByText('Add a license'));
    expect(router.push).toHaveBeenCalledWith('/licences');
  });
});

describe('A listing post on the phone (B10.2)', () => {
  const photos = [1, 2, 3].map((n) => ({ url: `https://reos.com/demo/listings/${n}.svg`, caption: null }));
  const draft: PostDraft = {
    listing: 'l1',
    address: '2418 Willow Bend Dr, Katy, TX 77494',
    photos,
    max_photos: 3,
    caption: 'Just listed: 2418 Willow Bend Dr',
    chosen: [photos[0]!.url],
    attribution: 'Listing courtesy of Lone Star Realty. Source: HAR MLS.',
    saved_at: null,
    sample: true,
  };

  it('opens from the listing', async () => {
    mockApi.GET.mockImplementation(answer(overview()));
    await render(wrap(<ListingsScreen />));
    fireEvent.press(await screen.findByLabelText('Create a post for 2418 Willow Bend Dr, Katy, TX 77494'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/deals/listings/[uuid]/post', params: { uuid: 'l1' } });
  });

  it('saves the photos chosen within the MLS limit', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: draft }));
    mockApi.PUT.mockResolvedValue(ok({ data: { ...draft, saved_at: '2026-11-24T10:00:00Z' } }));
    await render(wrap(<ListingPostScreen />));

    expect(await screen.findByText('Up to 3 photos, as the MLS allows. 1 chosen.')).toBeTruthy();
    fireEvent.press(screen.getByLabelText('Photo 2 of 2418 Willow Bend Dr, Katy, TX 77494'));
    expect(await screen.findByText('Up to 3 photos, as the MLS allows. 2 chosen.')).toBeTruthy();
    expect(screen.getByText('Listing courtesy of Lone Star Realty. Source: HAR MLS.')).toBeTruthy();

    fireEvent.press(screen.getByText('Save post'));
    expect(await screen.findByText('Post saved')).toBeTruthy();
    expect(mockApi.PUT).toHaveBeenCalledWith('/listings/{listing}/post', { params: { path: { listing: 'l1' } }, body: { caption: 'Just listed: 2418 Willow Bend Dr', photos: [photos[0]!.url, photos[1]!.url] } });
  });
});

describe('CMA and the public page on the phone (B10.2b)', () => {
  it('shows the public page link to share, and opens a CMA', async () => {
    mockApi.GET.mockImplementation(answer(overview()));
    await render(wrap(<ListingsScreen />));

    expect(await screen.findByText('https://reos.com/agent/james-miller')).toBeTruthy();
    fireEvent.press(screen.getAllByLabelText('CMA for 2418 Willow Bend Dr, Katy, TX 77494')[0]!);
    expect(router.push).toHaveBeenCalledWith({ pathname: '/deals/listings/[uuid]/cma', params: { uuid: 'l1' } });
  });

  it('shows the range and comparables, and changes the radius', async () => {
    const { role: _r, can_post: _c, open_house: _o, ...subject } = listing();
    const cma: Cma = {
      subject,
      radius: 1,
      months: 6,
      comparables: [{ listing: { ...subject, uuid: 'c1', address: '2502 Willow Bend Dr, Katy, TX 77494', status: 'closed', close_price: 463000, attribution: 'Listing courtesy of Bayou City Homes. Source: HAR MLS.' }, distance: 0.1, adjusted: 476000 }],
      sold: 4,
      active: 2,
      median_per_sqft: 168,
      low: 468000,
      high: 488000,
      suggested: 478000,
      sample: true,
    };
    mockApi.GET.mockResolvedValue(ok({ data: cma }));
    await render(wrap(<CmaScreen />));

    expect(await screen.findByText('$468,000 – $488,000')).toBeTruthy();
    expect(screen.getByText('Listing courtesy of Bayou City Homes. Source: HAR MLS.')).toBeTruthy();
    fireEvent.press(screen.getByLabelText('2 mi'));
    expect(await screen.findByText('$468,000 – $488,000')).toBeTruthy();
    expect(mockApi.GET).toHaveBeenLastCalledWith('/listings/{listing}/cma', { params: { path: { listing: 'l1' }, query: { radius: '2', months: '6' } } });
  });
});
