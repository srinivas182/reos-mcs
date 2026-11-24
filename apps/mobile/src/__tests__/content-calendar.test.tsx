import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import * as DocumentPicker from 'expo-document-picker';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import BrandKitScreen from '@/app/social/brand';
import ContentCalendarScreen from '@/app/social/calendar';
import CelebrationScreen from '@/app/social/celebrate/[id]';
import PostsScreen from '@/app/social/index';
import type { BrandKit, Calendar } from '@/lib/content-calendar';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };
let mockParams: Record<string, string> = {};

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: () => mockParams,
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('expo-document-picker', () => ({ getDocumentAsync: jest.fn() }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const week: Calendar = {
  view: 'week', date: '2026-12-01', from: '2026-11-29', to: '2026-12-05', previous: '2026-11-24', next: '2026-12-08', timezone: 'America/Chicago', scheduled: 1,
  days: ['2026-11-29', '2026-11-30', '2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05'].map((date) => ({
    date,
    today: false,
    posts:
      date === '2026-11-30'
        ? [{ id: 'p-1', status: 'scheduled', body: 'Open house Saturday, 1 to 3 pm.', time: '18:30', channels: ['facebook'], thumb: null, kind: null, movable: true }]
        : date === '2026-11-29'
          ? [{ id: 'p-0', status: 'published', body: 'Market update: homes are selling in 21 days.', time: '09:00', channels: ['facebook'], thumb: null, kind: null, movable: false }]
          : [],
  })),
};

beforeEach(() => {
  jest.clearAllMocks();
  mockParams = {};
});

describe('The content calendar on the phone (B12.3)', () => {
  it('shows the week, moves a post a day later with Undo, and reuses one', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: week }));
    mockApi.POST.mockResolvedValue(ok({ data: week.days[1]?.posts[0] }));
    await render(wrap(<ContentCalendarScreen />));

    expect(await screen.findByText('Open house Saturday, 1 to 3 pm.')).toBeTruthy();
    expect(screen.getByText('1 post scheduled')).toBeTruthy();
    await fireEvent.press(screen.getAllByLabelText('Next')[1]);
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/social/posts/{post}/move', { params: { path: { post: 'p-1' } }, body: { date: '2026-12-01' } }));
    await fireEvent.press(await screen.findByText('Undo'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenLastCalledWith('/social/posts/{post}/move', { params: { path: { post: 'p-1' } }, body: { date: '2026-11-30' } }));

    await fireEvent.press(screen.getAllByText('Reuse')[0]);
    await waitFor(() => expect(mockApi.POST).toHaveBeenLastCalledWith('/social/posts/{post}/reuse', { params: { path: { post: 'p-0' } }, body: { date: '2026-12-06' } }));

    await fireEvent.press(screen.getAllByText('Plan a post')[2]);
    expect(router.push).toHaveBeenCalledWith({ pathname: '/social/new', params: { at: '2026-12-01T10:00' } });
  });

  it('lists celebrations in My posts and opens one as Pick a version with its badge', async () => {
    const today = { id: 'c-1', milestone: 'first_closing', label: '88 Oak Ridge Dr, Katy', post: 'p-9', generation: { id: 'g-9' }, shown_at: '2026-11-24T15:17:00Z', created_at: '2026-11-24T15:17:00Z' };
    mockApi.GET.mockImplementation(async (path: string) =>
      path === '/studio/celebrations'
        ? ok({ data: { today, waiting: [{ ...today, id: 'c-2', milestone: 'anniversary', label: '3 years', generation: null }] } })
        : path === '/studio/celebrations/{celebration}'
          ? ok({ data: today })
          : ok({ data: { stats: { posts_this_month: 0, reach: 0, engagement: 0, clicks: 0 }, scheduled: [], posted: [], drafts: [] } }),
    );
    await render(wrap(<PostsScreen />));
    await fireEvent.press(await screen.findByText('First closing'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/social/celebrate/[id]', params: { id: 'c-1' } });
    expect(screen.getByText('3 years')).toBeTruthy();

    mockParams = { id: 'c-1' };
    await render(wrap(<CelebrationScreen />));
    await waitFor(() => expect(router.replace).toHaveBeenCalledWith({ pathname: '/social/versions/[id]', params: { id: 'g-9', milestone: 'first_closing' } }));
  });

  it('uploads a profile photo and shows its quality check (screen 162)', async () => {
    const kit: BrandKit = { photo: null, check: null, name: 'James Miller', brokerage: 'Lone Star Realty', licence: '0798765', colour: '#1f3a5f', logo: null };
    mockApi.GET.mockResolvedValue(ok({ data: kit }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...kit, photo: { path: 'social/1/studio/profile-a.jpg', url: 'https://reos.test/m/profile-a.jpg' }, check: { width: 300, height: 300, brightness: 50, issues: ['small', 'dark'] } } }));
    (DocumentPicker.getDocumentAsync as jest.Mock).mockResolvedValue({ canceled: false, assets: [{ uri: 'file:///me.jpg', name: 'me.jpg', mimeType: 'image/jpeg' }] });
    await render(wrap(<BrandKitScreen />));

    expect(await screen.findByText('No photo yet')).toBeTruthy();
    expect(screen.getByLabelText('Brokerage, Lone Star Realty')).toBeTruthy();
    await fireEvent.press(screen.getByText('Upload photo'));
    expect(await screen.findByText("It's small; a larger photo looks sharper.")).toBeTruthy();
    expect(screen.getByText("It's dark; we brightened it. Daylight works best.")).toBeTruthy();
    expect(screen.getByText('Replace photo')).toBeTruthy();
  });
});
