import { act, fireEvent, render, screen } from '@testing-library/react-native';
import { createHmac } from 'node:crypto';
import { useLocalSearchParams } from 'expo-router';
import * as Network from 'expo-network';
import * as SecureStore from 'expo-secure-store';
import { I18nextProvider } from 'react-i18next';

import Downloads from '@/app/downloads';
import LessonScreen from '@/app/learn/[id]';
import { i18n } from '@/lib/i18n';
import type { LearnLesson } from '@/lib/learn';
import {
  flush,
  hmacSha256Hex,
  offlineState,
  pending,
  recordCheckin,
  recordWatch,
  type SavedLesson,
  saveLesson,
  savedLessons,
  setDownloadSettings,
  syncDownloads,
} from '@/lib/offline';
import { spent } from '@/lib/player';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), DELETE: jest.fn(async () => ({ data: null })) };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: jest.fn(() => ({})),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ status: 'signedIn' }) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const state = { counted: 4320, required: 7200, percent: 60, reason: null, checkin: null, completed: false, today_left: null };
const question = {
  key: 'checkin:12',
  at: 900,
  kind: 'question' as const,
  question: { id: 41, prompt: 'When is the IABS notice given?', options: ['At the first substantive communication', 'At closing'] },
};

const lesson: LearnLesson = {
  id: 7,
  title: '6.2 Agency disclosure',
  language: 'en',
  minutes: 120,
  course: { id: 3, title: 'Texas Agency Law Update' },
  sections: [],
  documents: [],
  video: { type: 'hls', url: 'https://stream.mux.com/play-1.m3u8?token=x', duration: 7200, poster: null },
  transcript: [{ seconds: 1, text: 'Welcome back.' }],
  captions: { en: [{ seconds: 1, text: 'Welcome back.' }], es: [{ seconds: 1, text: 'Bienvenido de nuevo.' }] },
  previous: null,
  next: null,
  player: state,
};

const grant = {
  grant: 55,
  key: 'k'.repeat(64),
  url: 'https://stream.mux.com/play-1/720p.mp4?token=x',
  bytes: 180_000_000,
  expires_at: new Date(Date.now() + 30 * 86_400_000).toISOString(),
  lesson,
  player: { ...state, counted: 840 },
  answered: [],
  checkpoints: [{ ...question, answer: { correct: 0, explanation: 'At the first substantive communication.', source: 'TREC Rule §531.20' } }],
  rules: { checkin_minutes: 15, presence_percent: 100, max_seconds_per_day: 43_200 },
};

const offline = () => jest.mocked(Network.useNetworkState).mockReturnValue({ isConnected: false, isInternetReachable: false, type: Network.NetworkStateType.NONE });

beforeEach(async () => {
  jest.clearAllMocks();
  jest.mocked(Network.useNetworkState).mockReturnValue({ isConnected: true, isInternetReachable: true, type: Network.NetworkStateType.WIFI });
  jest.mocked(Network.getNetworkStateAsync).mockResolvedValue({ isConnected: true, isInternetReachable: true, type: Network.NetworkStateType.WIFI });
  setDownloadSettings({ limitGb: 2, smart: false, wifiOnly: true });
  for (const s of savedLessons()) {
    mockApi.GET.mockResolvedValue(ok({ data: [] }));
    await syncDownloads();
    void s;
  }
});

describe('helpers', () => {
  it('signs offline time exactly as the server checks it (HMAC-SHA256)', async () => {
    const payload = '[{"id":"a","type":"watch","at":"2026-11-23T15:00:00Z","seconds":60}]';

    expect(await hmacSha256Hex('secret-key', payload)).toBe(createHmac('sha256', 'secret-key').update(payload).digest('hex'));
    expect(await hmacSha256Hex('x'.repeat(100), 'm')).toBe(createHmac('sha256', 'x'.repeat(100)).update('m').digest('hex'));
    expect(spent(4320)).toBe('1 h 12 m');
  });
});

describe('the phone player (screen 25)', () => {
  it('sends heartbeats and shows what has counted', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7' });
    mockApi.GET.mockResolvedValue(ok({ data: lesson }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...state, reason: 'hidden' } }));

    await render(wrap(<LessonScreen />));
    await act(async () => {
      await new Promise((r) => setTimeout(r, 5));
    });

    expect(await screen.findByText('1 h 12 m of 2 h counted')).toBeTruthy();
    expect(screen.getByText("REOS isn't on screen, so time isn't counting. Come back to keep going.")).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/learn/lessons/{lesson}/heartbeat', expect.objectContaining({ params: { path: { lesson: 7 } }, body: expect.objectContaining({ playing: false, visible: true }) }));
  });

  it('stops for a check-in until answered, explains a wrong answer, and switches captions and speed', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7' });
    mockApi.GET.mockResolvedValue(ok({ data: lesson }));
    mockApi.POST.mockImplementation(async (path: string) =>
      path.endsWith('/checkins')
        ? ok({ correct: false, explanation: 'At the first substantive communication.', source: 'TREC Rule §531.20', state })
        : ok({ data: { ...state, reason: 'checkin_due', checkin: question } }),
    );
    const { __player: player } = jest.requireMock<{ __player: { pause: jest.Mock; playbackRate: number } }>('expo-video');

    await render(wrap(<LessonScreen />));
    expect(await screen.findByText('When is the IABS notice given?')).toBeTruthy();
    expect(player.pause).toHaveBeenCalled();
    expect(screen.queryByRole('button', { name: 'Close' })).toBeNull();

    await fireEvent.press(screen.getByLabelText('At closing'));
    await fireEvent.press(screen.getByRole('button', { name: 'Answer' }));
    expect(await screen.findByText("Not quite. You're still counted as present.")).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/learn/lessons/{lesson}/checkins', { params: { path: { lesson: 7 } }, body: { key: 'checkin:12', choice: 1 } });

    await fireEvent.press(screen.getByRole('button', { name: 'Report this question' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Send report' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/questions/{question}/report', { params: { path: { question: 41 } }, body: { reason: 'wrong_answer' } });

    await fireEvent.press(screen.getByLabelText('1.5×'));
    expect(player.playbackRate).toBe(1.5);
    await fireEvent.press(screen.getByLabelText('Spanish'));
    expect(screen.getByText('Bienvenido de nuevo.')).toBeTruthy();
  });

  it('saves a lesson offline on Wi-Fi, with its key in secure storage', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7' });
    mockApi.GET.mockResolvedValue(ok({ data: lesson }));
    mockApi.POST.mockImplementation(async (path: string) => (path.endsWith('/downloads') ? ok({ data: grant }) : ok({ data: state })));
    const { File } = jest.requireMock<{ File: { downloadFileAsync: jest.Mock } }>('expo-file-system');

    await render(wrap(<LessonScreen />));
    await fireEvent.press(await screen.findByRole('button', { name: 'Save offline' }));

    expect(await screen.findByText('Saved offline')).toBeTruthy();
    expect(File.downloadFileAsync).toHaveBeenCalledWith(grant.url, expect.objectContaining({ uri: 'file:///documents/lessons/55.mp4' }), { idempotent: true });
    expect(SecureStore.setItemAsync).toHaveBeenCalledWith('reos.offline.key.55', grant.key, expect.anything());
    expect(savedLessons()).toHaveLength(1);
  });

  it('won\'t save on mobile data with Wi-Fi only on', async () => {
    jest.mocked(Network.getNetworkStateAsync).mockResolvedValue({ isConnected: true, type: Network.NetworkStateType.CELLULAR });
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7' });
    mockApi.GET.mockResolvedValue(ok({ data: lesson }));
    mockApi.POST.mockResolvedValue(ok({ data: state }));

    await render(wrap(<LessonScreen />));
    await fireEvent.press(await screen.findByRole('button', { name: 'Save offline' }));

    expect(await screen.findByText(/Connect to Wi-Fi to save this lesson/)).toBeTruthy();
    expect(mockApi.POST).not.toHaveBeenCalledWith('/learn/lessons/{lesson}/downloads', expect.anything());
  });
});

describe('offline (A8.2b)', () => {
  const save = async (): Promise<SavedLesson> => {
    mockApi.POST.mockResolvedValueOnce(ok({ data: grant }));

    return saveLesson(7);
  };

  it('plays the saved copy offline and keeps the time on the phone', async () => {
    await save();
    offline();
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7' });
    mockApi.GET.mockRejectedValue(new Error('offline'));
    const { useVideoPlayer } = jest.requireMock<{ useVideoPlayer: jest.Mock }>('expo-video');

    await render(wrap(<LessonScreen />));

    expect(await screen.findByText("You're offline. Your time is kept on this phone and sent when you're back online.")).toBeTruthy();
    expect(useVideoPlayer).toHaveBeenLastCalledWith('file:///documents/lessons/55.mp4', expect.any(Function));
  });

  it('records stretches, stops at a check-in, and sends them signed', async () => {
    const saved = await save();
    const t0 = new Date('2026-11-23T15:00:00Z');

    recordWatch(55, t0);
    recordWatch(55, new Date(t0.getTime() + 15_000));
    recordWatch(55, new Date(t0.getTime() + 30_000));
    // A minute away: a new stretch.
    recordWatch(55, new Date(t0.getTime() + 90_000));
    recordWatch(55, new Date(t0.getTime() + 105_000));
    expect(pending(55).map((e) => (e.type === 'watch' ? e.seconds : null))).toEqual([30, 15]);
    expect(offlineState(saved)).toMatchObject({ counted: 885, checkin: null });

    recordWatch(55, new Date(t0.getTime() + 120_000));
    expect(offlineState(saved)).toMatchObject({ counted: 900, reason: 'checkin_due', checkin: { key: 'checkin:12' } });
    recordCheckin(55, 'checkin:12', 0, new Date(t0.getTime() + 125_000));
    expect(offlineState(saved).checkin).toBeNull();

    mockApi.POST.mockResolvedValueOnce(ok({ accepted: 3, duplicates: 0, rejected: 0, state: { ...state, counted: 900 } }));
    expect(await flush(55)).toMatchObject({ counted: 900 });
    const [, body] = mockApi.POST.mock.calls.at(-1) as [string, { body: { payload: string; signature: string } }];
    expect(body.body.signature).toBe(createHmac('sha256', grant.key).update(body.body.payload).digest('hex'));
    expect(JSON.parse(body.body.payload)).toHaveLength(3);
    expect(pending(55)).toEqual([]);
    expect(savedLessons()[0]?.data.answered).toEqual(['checkin:12']);
  });

  it('removes lessons whose access ended when back online (Downloads)', async () => {
    await save();
    mockApi.GET.mockResolvedValue(ok({ data: [{ grant: 55, lesson_id: 7, title: '6.2 Agency disclosure', course: 'Texas Agency Law Update', bytes: 1, expires_at: grant.expires_at, status: 'revoked' }] }));

    await render(wrap(<Downloads />));

    expect(await screen.findByText('No saved lessons')).toBeTruthy();
    expect(savedLessons()).toEqual([]);
    expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith('reos.offline.key.55');
  });

  it('shows storage, the limit and each saved lesson with its days left', async () => {
    await save();
    mockApi.GET.mockResolvedValue(ok({ data: [{ grant: 55, lesson_id: 7, title: '6.2 Agency disclosure', course: 'Texas Agency Law Update', bytes: 1, expires_at: grant.expires_at, status: 'active' }] }));

    await render(wrap(<Downloads />));

    expect(await screen.findByText('172 MB of 2 GB used')).toBeTruthy();
    expect(screen.getByText(/Works 30 more days offline/)).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('5 GB'));
    expect(await screen.findByText('172 MB of 5 GB used')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Remove' }));
    expect(await screen.findByText('No saved lessons')).toBeTruthy();
  });
});
