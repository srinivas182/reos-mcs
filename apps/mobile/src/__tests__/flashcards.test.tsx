import { fireEvent, render, screen } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import FlashcardsScreen from '@/app/practice/flashcards';
import SpeedScreen from '@/app/practice/speed';
import { SaveFlashcard } from '@/features/practice/SaveFlashcard';
import { type Deck, loadDeck, pendingReviews, rate } from '@/lib/flashcards';
import { AGAIN, GOOD, HARD, interval, retrievability, review } from '@/lib/fsrs';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: jest.fn(() => ({})),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const offline = () => Promise.reject(new Error('Network request failed'));
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const deck: Deck = {
  due: 1,
  new_left: 1,
  total: 2,
  cards: [
    { id: 61, source: 'review', topic: 'finance', front: 'When must borrower-paid PMI end?', back: 'At a scheduled 78 percent LTV.', new: false, due_at: '2020-01-01T00:00:00Z', reps: 3, lapses: 0, stability: 4.2, difficulty: 6.1, last_reviewed_at: '2019-12-28T00:00:00Z' },
    { id: 62, source: 'course', topic: 'agency', front: 'What does IABS stand for?', back: 'Information About Brokerage Services.', new: true, due_at: '2020-01-01T00:00:00Z', reps: 0, lapses: 0, stability: null, difficulty: null, last_reviewed_at: null },
  ],
};

beforeEach(() => jest.clearAllMocks());

describe('FSRS on the phone matches the server (A9.2d)', () => {
  it('schedules a new card by its first rating', () => {
    const at = new Date('2027-01-01T10:00:00Z');
    const fresh = { stability: null, difficulty: null, reps: 0, lapses: 0, last_reviewed_at: null };

    expect(review(fresh, AGAIN, at)).toMatchObject({ stability: 0.4026, interval_days: 0, due_at: '2027-01-01T10:10:00.000Z' });
    expect(review(fresh, HARD, at)).toMatchObject({ stability: 1.1839, interval_days: 1 });
    expect(review(fresh, GOOD, at)).toMatchObject({ stability: 3.173, interval_days: 3, difficulty: Math.round((7.1949 - Math.exp(0.5345 * 2) + 1) * 10_000) / 10_000 });
    expect(Math.round(retrievability(10, 10) * 1000) / 1000).toBe(0.9);
    expect(interval(10)).toBe(10);
  });
});

describe('flashcards offline (screen 31)', () => {
  it('reviews with no connection, keeps the queue, and syncs each review once when back online', async () => {
    mockApi.POST.mockImplementation(offline);
    mockApi.GET.mockResolvedValueOnce(ok({ data: deck }));
    await loadDeck();

    // Offline now: the saved deck, scheduled on the phone.
    mockApi.GET.mockImplementation(offline);
    const { deck: saved, offline: isOffline } = await loadDeck();
    expect(isOffline).toBe(true);
    const updated = rate(saved!.cards[1]!, GOOD, new Date('2027-01-01T10:00:00Z'));
    expect(updated).toMatchObject({ new: false, reps: 1, stability: 3.173 });
    expect(pendingReviews()).toHaveLength(1);

    // Back online: the queue goes, once.
    mockApi.POST.mockResolvedValue(ok({ data: { applied: 1, cards: [] } }));
    mockApi.GET.mockResolvedValue(ok({ data: deck }));
    await loadDeck();
    expect(mockApi.POST).toHaveBeenLastCalledWith('/practice/flashcards/reviews', { body: { reviews: [expect.objectContaining({ card_id: 62, rating: 3, reviewed_at: '2027-01-01T10:00:00.000Z' })] } });
    expect(pendingReviews()).toHaveLength(0);
  });

  it('works through a burst: the card due first, Again brings it back', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: deck }));
    mockApi.POST.mockResolvedValue(ok({ data: { applied: 1, cards: [] } }));
    await render(wrap(<FlashcardsScreen />));

    await fireEvent.press(await screen.findByLabelText('Start a 2-minute burst'));
    expect(screen.getByText('When must borrower-paid PMI end?')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Show the answer'));
    expect(screen.getByText('At a scheduled 78 percent LTV.')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Again'));

    expect(screen.getByText('What does IABS stand for?')).toBeTruthy();
    expect(screen.getByText('Card 2 · 1 to go')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Show the answer'));
    await fireEvent.press(screen.getByLabelText('Got it'));
    expect(screen.getByText('When must borrower-paid PMI end?')).toBeTruthy();
  });
});

describe('speed round and Save as flashcard (screens 50, 43)', () => {
  it('plays true or false and shows the server\'s result', async () => {
    mockApi.POST.mockImplementation((path: string) =>
      Promise.resolve(
        path === '/practice/speed'
          ? ok({ data: { id: 5, seconds: 60, best: 27, statements: [{ id: 1, prompt: 'How many acres are in a section?', answer: '640', truth: true, topic: 'math' }] } })
          : ok({ data: { score: 1, answered: 1, best: 27, record: false, misses: 0 } }),
      ),
    );
    await render(wrap(<SpeedScreen />));

    await fireEvent.press(screen.getByLabelText('Start'));
    expect(await screen.findByText('Answer: 640')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('True'));
    expect(mockApi.POST).toHaveBeenLastCalledWith('/practice/speed/{round}/finish', { params: { path: { round: 5 } }, body: { taps: [{ id: 1, true: true }] } });
    expect(await screen.findByText("Time's up")).toBeTruthy();
  });

  it('saves a card from answer review', async () => {
    mockApi.POST.mockResolvedValue(ok({ data: { id: 70 } }));
    await render(wrap(<SaveFlashcard source="review" front="What does IABS stand for?" back="Information About Brokerage Services." questionId={12} />));

    await fireEvent.press(screen.getByLabelText('Save as flashcard'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/flashcards', { body: { source: 'review', front: 'What does IABS stand for?', back: 'Information About Brokerage Services.', question_id: 12 } });
    expect(await screen.findByLabelText('Saved to your flashcards.')).toBeTruthy();
  });
});
