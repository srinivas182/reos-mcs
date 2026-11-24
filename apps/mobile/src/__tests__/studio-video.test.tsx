import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import CreatePostScreen from '@/app/social/create';
import EditVideoScreen from '@/app/social/video/[id]';
import { i18n } from '@/lib/i18n';
import type { Video } from '@/lib/studio';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };
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
jest.mock('@/lib/voice', () => ({ speechLanguage: () => 'en-US', useSpeech: () => ({ state: 'idle', transcript: '', start: jest.fn(), stop: jest.fn() }) }));
jest.mock('expo-document-picker', () => ({ getDocumentAsync: jest.fn() }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const listing = '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66';
const video: Video = {
  id: 'v-1', source: { type: 'listing', id: listing, label: '2418 Willow Bend Dr' }, listing, title: 'Step inside this Katy home',
  scenes: [
    { media: 'https://reos.test/p/1.jpg', url: 'https://reos.test/p/1.jpg', kind: 'image', start: 0, seconds: 6, length: null, caption: '2418 Willow Bend Dr' },
    { media: 'social/1/tour.mp4', url: 'https://reos.test/m/tour.mp4', kind: 'video', start: 0, seconds: 6, length: 12, caption: '' },
    { media: 'https://reos.test/p/2.jpg', url: 'https://reos.test/p/2.jpg', kind: 'image', start: 0, seconds: 6, length: null, caption: 'Call James Miller to see it' },
  ],
  music: 'calm', brand: true, status: 'ready', seconds: 18,
  video: { path: 'social/1/studio/v-1-v1.mp4', url: 'https://reos.test/m/v-1-v1.mp4' }, poster: { path: 'social/1/studio/v-1-v1.jpg', url: 'https://reos.test/m/v-1-v1.jpg' },
  error: null, issues: [], credits: 30, engine: 'ffmpeg', created_at: '2026-11-24T15:00:00Z', rendered_at: '2026-11-24T15:01:00Z',
};

beforeEach(() => {
  jest.clearAllMocks();
  mockParams = {};
});

describe('Studio videos on the phone (B12.2: screen 168)', () => {
  it('makes a listing video with the cost first, and lists my videos', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { cost: 5, credits_left: 495, listings: [{ id: listing, address: '2418 Willow Bend Dr', status: 'active', photo: null }], sales: [], generations: [], video_cost: 30, tracks: ['calm', 'bright', 'none'], videos: [video] } }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...video, status: 'rendering' } }));
    await render(wrap(<CreatePostScreen />));

    expect(await screen.findByText('30 credits · 495 left')).toBeTruthy();
    expect(screen.getByText('Ready · 18 seconds')).toBeTruthy();
    await fireEvent.press(screen.getByText('Listing video'));
    expect(mockApi.POST).toHaveBeenCalledWith('/studio/videos', { body: { source: { type: 'listing', id: listing }, clips: [] } });
    await waitFor(() => expect(router.push).toHaveBeenCalledWith({ pathname: '/social/video/[id]', params: { id: 'v-1' } }));
  });

  it('trims, reorders, picks music and renders again, then uses it in a post', async () => {
    mockParams = { id: 'v-1' };
    mockApi.GET.mockResolvedValue(ok({ data: video }));
    mockApi.PUT.mockImplementation(async (_path: string, { body }: { body: { scenes: Video['scenes']; music: Video['music'] } }) => ok({ data: { ...video, music: body.music, scenes: body.scenes.map((s) => ({ ...video.scenes.find((x) => x.media === s.media), ...s })) } }));
    await render(wrap(<EditVideoScreen />));

    expect(await screen.findByText('18 seconds in all')).toBeTruthy();
    await fireEvent.press(screen.getAllByLabelText('Shorter')[0]);
    await fireEvent.press(screen.getByLabelText('Start later'));
    expect(screen.getByText('17.5 seconds in all')).toBeTruthy();
    await fireEvent.press(screen.getAllByText('Move up')[0]);
    await fireEvent.press(screen.getByText('Music'));
    await fireEvent.press(screen.getByText('Bright'));
    await fireEvent.press(screen.getByText('Done'));

    await waitFor(() => expect(mockApi.PUT).toHaveBeenCalled());
    const body = mockApi.PUT.mock.calls[0][1].body;
    expect(body).toMatchObject({ title: 'Step inside this Katy home', music: 'bright', brand: true, render: true });
    expect(body.scenes.map((s: { media: string; start: number; seconds: number }) => [s.media, s.start, s.seconds])).toEqual([
      ['social/1/tour.mp4', 0.5, 6],
      ['https://reos.test/p/1.jpg', 0, 5.5],
      ['https://reos.test/p/2.jpg', 0, 6],
    ]);

    await fireEvent.press(await screen.findByText('Use in a post'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/social/new', params: { caption: 'Step inside this Katy home', media: JSON.stringify([{ path: 'social/1/studio/v-1-v1.mp4', url: 'https://reos.test/m/v-1-v1.mp4', kind: 'video' }]), listing } });
  });

  it('shows flagged words and fixes them, and suggests hooks', async () => {
    mockParams = { id: 'v-1' };
    mockApi.GET.mockResolvedValue(ok({ data: { ...video, issues: [{ code: 'fair_housing', phrase: 'perfect for families', fix: 'roomy layout' }] } }));
    mockApi.PUT.mockResolvedValue(ok({ data: video }));
    mockApi.POST.mockResolvedValue(ok({ data: ['Tour this Katy home', 'New in Katy'] }));
    await render(wrap(<EditVideoScreen />));

    expect(await screen.findByText('Fix these before rendering')).toBeTruthy();
    await fireEvent.press(screen.getByText('Fix automatically'));
    await waitFor(() => expect(mockApi.PUT.mock.calls[0][1].body).toMatchObject({ fix: true }));
    await waitFor(() => expect(screen.queryByText('Fix these before rendering')).toBeNull());

    await fireEvent.press(screen.getByText('Hook'));
    await fireEvent.press(screen.getByText('Suggest stronger hooks'));
    await fireEvent.press(await screen.findByText('New in Katy'));
    expect(screen.getByDisplayValue('New in Katy')).toBeTruthy();
  });
});
