import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import LessonScreen from '@/app/learn/[id]';
import Learn from '@/app/learn/index';
import { i18n } from '@/lib/i18n';
import type { LearnLesson, SearchHit } from '@/lib/learn';
import { clock, marks } from '@/lib/learn';

const mockApi = { GET: jest.fn() };
const mockAuth = { status: 'signedIn' };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: jest.fn(() => ({})),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => mockAuth }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => <I18nextProvider i18n={i18n}>{ui}</I18nextProvider>;

const hit: SearchHit = {
  passage_id: 102,
  lesson_id: 7,
  lesson_title: '6.2 Agency disclosure',
  course_id: 3,
  course_title: 'Principles of Real Estate II',
  kind: 'transcript',
  heading: null,
  page: null,
  seconds: 245,
  language: 'en',
  snippet: 'hand them the [[IABS]] form.',
  match: 'keyword',
};

const lesson: LearnLesson = {
  id: 7,
  title: '6.2 Agency disclosure',
  language: 'en',
  minutes: 25,
  course: { id: 3, title: 'Principles of Real Estate II' },
  sections: [{ heading: 'First contact', text: 'Give the IABS at the first substantive contact.' }],
  documents: [{ id: 12, kind: 'slides', name: 'slides.pdf', url: 'https://files.example/slides.pdf', pages: [{ page: 1, text: 'Agency' }, { page: 2, text: 'Information About Brokerage Services' }] }],
  video: { type: 'hls', url: 'https://stream.mux.com/play-1.m3u8?token=x', duration: 754, poster: null },
  transcript: [
    { seconds: 1, text: 'Welcome back.' },
    { seconds: 245, text: 'Hand them the IABS form.' },
  ],
  previous: null,
  next: 8,
};

beforeEach(() => {
  jest.clearAllMocks();
});

describe('helpers', () => {
  it('writes times and splits matches', () => {
    expect(clock(245)).toBe('4:05');
    expect(marks('the [[IABS]] form')).toEqual([
      { text: 'the ', match: false },
      { text: 'IABS', match: true },
      { text: ' form', match: false },
    ]);
  });
});

describe('Learn (screen 8)', () => {
  it('lists my courses and opens a search result at its moment', async () => {
    mockApi.GET.mockImplementation((path: string) =>
      Promise.resolve(
        path === '/learn/courses'
          ? ok({
              state: 'TX',
              data: [
                { id: 3, title: 'Principles of Real Estate II', type: 'qualifying', hours: 30, credit: { number: 'TREC 0451', hours: 30 }, lessons: [{ id: 7, title: '6.2 Agency disclosure', minutes: 25, language: 'en' }] },
                { id: 4, title: 'Contracts for new agents', type: 'ce', hours: 4, credit: null, lessons: [] },
              ],
            })
          : ok({ data: [hit] }),
      ),
    );

    await render(wrap(<Learn />));

    expect(await screen.findByText('Principles of Real Estate II')).toBeTruthy();
    // No CE or qualifying label without an approval in force (A7.1).
    expect(screen.getByText(/Qualifying · approval TREC 0451/)).toBeTruthy();
    expect(screen.getByText(/Not approved for credit in TX yet/)).toBeTruthy();
    await fireEvent.changeText(screen.getByLabelText('Search your lessons'), 'IABS');
    await fireEvent.press(screen.getByRole('button', { name: 'Search' }));

    expect(mockApi.GET).toHaveBeenCalledWith('/learn/search', { params: { query: { q: 'IABS' } } });
    await fireEvent.press(await screen.findByLabelText(/6\.2 Agency disclosure, At 4:05/));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/learn/[id]', params: { id: '7', t: '245' } });
  });

  it('says when nothing is found', async () => {
    mockApi.GET.mockImplementation((path: string) => Promise.resolve(path === '/learn/courses' ? ok({ state: 'TX', data: [] }) : ok({ data: [] })));

    await render(wrap(<Learn />));
    await fireEvent.changeText(screen.getByLabelText('Search your lessons'), 'mineral rights');
    await fireEvent.press(screen.getByRole('button', { name: 'Search' }));

    expect(await screen.findByText('Nothing found for “mineral rights”')).toBeTruthy();
    expect(screen.getByText('No courses for TX yet')).toBeTruthy();
  });
});

describe('a lesson', () => {
  it('opens the Membership screen when the lesson needs a plan', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '8' });
    mockApi.GET.mockResolvedValue({ data: undefined, error: { membership_required: true }, response: { ok: false, status: 402 } });

    await render(wrap(<LessonScreen />));

    await screen.findByText('Learn');
    expect(router.replace).toHaveBeenCalledWith('/membership');
  });

  it('starts the video at the moment, and a transcript line plays from there', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7', t: '245' });
    mockApi.GET.mockResolvedValue(ok({ data: lesson }));
    const { __player: player } = jest.requireMock<{ __player: { currentTime: number; play: jest.Mock } }>('expo-video');

    await render(wrap(<LessonScreen />));

    expect(await screen.findByText('Starts at 4:05')).toBeTruthy();
    expect(player.currentTime).toBe(245);

    await fireEvent.press(screen.getByLabelText('Play from 0:01. Welcome back.'));
    expect(player.currentTime).toBe(1);
    expect(player.play).toHaveBeenCalled();
  });

  it('says when there is no video yet, and the transcript times are not buttons', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7' });
    mockApi.GET.mockResolvedValue(ok({ data: { ...lesson, video: null } }));

    await render(wrap(<LessonScreen />));

    expect(await screen.findByText('Video not added yet. This is the transcript only.')).toBeTruthy();
    expect(screen.getByText('Welcome back.')).toBeTruthy();
    expect(screen.queryByLabelText('Play from 0:01. Welcome back.')).toBeNull();
  });

  it('shows the page a result came from first', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '7', page: '2' });
    mockApi.GET.mockResolvedValue(ok({ data: lesson }));

    await render(wrap(<LessonScreen />));

    expect(await screen.findByText('From your search')).toBeTruthy();
    expect(screen.getByText('Slides · Page 2')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Next lesson' }));
    expect(router.replace).toHaveBeenCalledWith({ pathname: '/learn/[id]', params: { id: '8' } });
  });
});
