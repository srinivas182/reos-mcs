import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import CreatePostScreen from '@/app/social/create';
import NewPostScreen from '@/app/social/new';
import VersionsScreen from '@/app/social/versions/[id]';
import { i18n } from '@/lib/i18n';
import type { Generation } from '@/lib/studio';
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
const made: Generation = {
  id: 'g-1', source: { type: 'listing', id: listing, label: '2418 Willow Bend Dr' }, listing, outputs: ['post'],
  captions: [
    { tone: 'professional', text: 'Just listed: 2418 Willow Bend Dr. #JustListed' },
    { tone: 'warm', text: 'Say hello to 2418 Willow Bend Dr! #JustListed' },
    { tone: 'short', text: 'New: 2418 Willow Bend Dr. #JustListed' },
  ],
  designs: [{ layout: 'band', path: 'social/1/studio/g-band.png', url: 'https://reos.test/m/g-band.png' }, { layout: 'top', path: 'social/1/studio/g-top.png', url: 'https://reos.test/m/g-top.png' }],
  carousel: [], ad: null, credits: 5, engine: 'simulator', created_at: '2026-11-24T15:00:00Z',
};

beforeEach(() => {
  jest.clearAllMocks();
  mockParams = {};
});

describe('The content studio on the phone (B12.1: screens 165–167)', () => {
  it('makes a post from a listing, with the cost first', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { cost: 5, credits_left: 495, listings: [{ id: listing, address: '2418 Willow Bend Dr', status: 'active', photo: null }], sales: [], generations: [], video_cost: 30, tracks: ['calm', 'bright', 'none'], videos: [] } }));
    mockApi.POST.mockResolvedValue(ok({ data: made }));
    await render(wrap(<CreatePostScreen />));

    expect(await screen.findByText('5 credits · 495 left')).toBeTruthy();
    await fireEvent.press(screen.getByText('Make'));
    expect(mockApi.POST).toHaveBeenCalledWith('/studio/generations', { body: { source: { type: 'listing', id: listing }, outputs: ['post'] } });
    await waitFor(() => expect(router.push).toHaveBeenCalledWith({ pathname: '/social/versions/[id]', params: { id: 'g-1' } }));
  });

  it('picks a design and caption, changes a tone, and goes on to share', async () => {
    mockParams = { id: 'g-1' };
    mockApi.GET.mockResolvedValue(ok({ data: made }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...made, captions: [{ tone: 'professional', text: 'Recién listada: 2418 Willow Bend Dr. #RecienListada' }, ...made.captions.slice(1)] } }));
    await render(wrap(<VersionsScreen />));

    await fireEvent.press(await screen.findByLabelText('Design 2'));
    await fireEvent.press(screen.getAllByText('Español')[0]);
    expect(await screen.findByText('Recién listada: 2418 Willow Bend Dr. #RecienListada')).toBeTruthy();
    await fireEvent.press(screen.getByText('Use this'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/social/new', params: { caption: 'Recién listada: 2418 Willow Bend Dr. #RecienListada', media: JSON.stringify([{ path: made.designs[1]?.path, url: made.designs[1]?.url }]), listing } });
  });

  it('flags fair housing wording, fixes it, and says the broker approves first (screen 167)', async () => {
    mockParams = { caption: 'Perfect for families! Open house Saturday.', media: '[]' };
    mockApi.GET.mockResolvedValue(ok({ data: [] }));
    mockApi.POST.mockImplementation(async (path: string) =>
      path === '/social/posts/fix'
        ? ok({ data: { body: 'roomy layout! Open house Saturday.', fixed: [] } })
        : ok({ data: { attribution: 'James Miller · Lone Star Realty', checks: [{ code: 'fair_housing', ok: false, detail: 'perfect for families' }], ok: false, approval: true, issues: [{ code: 'fair_housing', phrase: 'perfect for families', fix: 'roomy layout' }] } }),
    );
    await render(wrap(<NewPostScreen />));

    expect(await screen.findByText(/perfect for families/)).toBeTruthy();
    expect(screen.getByText('Your brokerage approves your posts first. When you post, it goes to your broker.')).toBeTruthy();
    await fireEvent.press(screen.getByText('Fix automatically'));
    expect(mockApi.POST).toHaveBeenCalledWith('/social/posts/fix', { body: { body: 'Perfect for families! Open house Saturday.' } });
  });
});
