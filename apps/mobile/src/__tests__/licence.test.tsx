import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import ExamDayScreen from '@/app/licence-journey/exam-day';
import OffersScreen from '@/app/licence-journey/offers';
import PassesScreen from '@/app/licence-journey/passes';
import LicenceJourneyScreen from '@/app/licence-journey/index';
import ResultScreen from '@/app/licence-journey/result';
import { i18n } from '@/lib/i18n';
import type { Journey } from '@/lib/licence';
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
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ user: null }) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const step = (code: string, title: string, done_on: string | null = null) => ({ code, title, detail: `${title}: what to do.`, url: 'https://www.trec.texas.gov/become-licensed/sales-agent', done_on, auto: false });
const journey = (over: Partial<Journey> = {}): Journey => ({
  state: { code: 'TX', name: 'Texas' },
  steps: [step('education', 'Qualifying education', '2026-10-02'), step('application', 'Application', '2026-11-02'), step('fingerprints', 'Fingerprints')],
  window: { filed_on: '2026-11-02', ends_on: '2027-11-02', days_left: 344, months: 12 },
  readiness: { percent: 91, national: 93, state: 91, line: 90, ready: true, simulations: 2, needed: 2 },
  exam: { vendor: 'Pearson VUE', book_url: 'https://www.pearsonvue.com/us/en/tx/realestate.html', on: '2026-12-14', time: '08:30', centre: 'Pearson VUE Houston', days_to: 21, directions_url: 'https://www.google.com/maps/search/?api=1&query=Pearson%20VUE%20Houston' },
  kit: { items: [{ key: 'id', done: false }, { key: 'confirmation', done: false }, { key: 'final_simulation', done: false }, { key: 'flashcards', done: false }, { key: 'sleep', done: false }], rules: 'Bring two current IDs with your signature.' },
  results: { national: { passed: null, attempts: 0, failed: 0, on: null }, state: { passed: null, attempts: 0, failed: 0, on: null }, can_enter: false, attempts_allowed: 3, passed_both: false },
  retake: null,
  news: null,
  offers: { opted_in: false, city: null, open: 0 },
  ...over,
});

beforeEach(() => jest.clearAllMocks());

describe('Licence journey on the phone (A10.3a: screens 16, 15, 14, 19)', () => {
  it('shows the window and steps, checks a step off, and saves the exam date', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: journey() }));
    mockApi.PUT.mockResolvedValue(ok({ data: journey() }));
    await render(wrap(<LicenceJourneyScreen />));

    expect(await screen.findByText('344 days left, until Tue, Nov 2')).toBeTruthy();
    expect(screen.getByText("You're ready to book")).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Mark done' }));
    expect(mockApi.PUT).toHaveBeenCalledWith('/licence-journey/steps/{code}', { params: { path: { code: 'fingerprints' } }, body: { done: true, on: null } });

    await fireEvent.press(screen.getByRole('button', { name: 'Save exam date' }));
    expect(mockApi.PUT).toHaveBeenLastCalledWith('/licence-journey/exam', { body: { exam_on: '2026-12-14', time: '08:30', centre: 'Pearson VUE Houston' } });
    await fireEvent.press(screen.getByRole('button', { name: 'Exam-day kit' }));
    expect(router.push).toHaveBeenCalledWith('/licence-journey/exam-day');
  });

  it('offers the retake plan, and the result entry after the exam', async () => {
    mockApi.GET.mockResolvedValue(
      ok({ data: journey({ results: { ...journey().results, can_enter: true }, retake: { portions: ['state'], topics: ['state_rules'], more_education: false, attempts_left: { state: 2 } } }) }),
    );
    await render(wrap(<LicenceJourneyScreen />));

    expect(await screen.findByText('State portion: 2 attempts left.')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Enter your result' }));
    expect(router.push).toHaveBeenCalledWith('/licence-journey/result');
  });

  it('ticks the exam-day checklist and shows the vendor rules', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: journey() }));
    mockApi.PUT.mockResolvedValue(ok({ data: journey() }));
    await render(wrap(<ExamDayScreen />));

    expect(await screen.findByText('Bring two current IDs with your signature.')).toBeTruthy();
    await fireEvent(screen.getByLabelText('Exam confirmation'), 'valueChange', true);
    expect(mockApi.PUT).toHaveBeenCalledWith('/licence-journey/kit/{item}', { params: { path: { item: 'confirmation' } }, body: { done: true } });
    expect(screen.getByRole('button', { name: 'Start' })).toBeTruthy();
  });

  it('enters a missed state portion with the weak areas from the score report', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: journey({ results: { ...journey().results, can_enter: true } }) }));
    mockApi.POST.mockResolvedValue(ok({ data: journey() }));
    await render(wrap(<ResultScreen />));

    await screen.findByText('State portion');
    await fireEvent.press(screen.getAllByRole('radio', { name: "Didn't pass" })[1]!);
    await fireEvent(screen.getByLabelText('Texas rules'), 'valueChange', true);
    await fireEvent.press(screen.getByRole('button', { name: 'Save result' }));

    expect(mockApi.POST).toHaveBeenCalledWith('/licence-journey/results', { body: { national: true, state: false, weak_topics: ['state_rules'] } });
    expect(router.replace).toHaveBeenCalledWith('/licence-journey');
  });
});

describe('After the exam on the phone (A10.3b: f05, f06, 17)', () => {
  it('shares the news with the people chosen', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: journey({ news: 'prompt', results: { ...journey().results, passed_both: true } }) }));
    mockApi.POST.mockResolvedValue(ok({ data: { shared: true } }));
    await render(wrap(<LicenceJourneyScreen />));

    await fireEvent(await screen.findByLabelText('My school'), 'valueChange', false);
    await fireEvent.press(screen.getByRole('button', { name: 'Share' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/licence-journey/news', { body: { to_group: true, to_school: false, to_state: true, city: null, tip: null } });
  });

  it('opts in to broker offers with a city', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: journey() }));
    mockApi.PUT.mockResolvedValue(ok({ data: { opted_in: true, city: 'Katy', offers: [] } }));
    await render(wrap(<LicenceJourneyScreen />));

    await fireEvent(await screen.findByLabelText('Get offers from brokerages'), 'valueChange', true);
    await fireEvent.changeText(screen.getByLabelText('City you want to work in'), 'Katy');
    await fireEvent.press(screen.getByRole('button', { name: 'Save' }));
    expect(mockApi.PUT).toHaveBeenCalledWith('/licence-journey/offers/opt-in', { body: { on: true, city: 'Katy' } });
  });

  it('sends congrats on a pass', async () => {
    const feed = { week: 14, state: 'Texas', readiness: 86, passes: [{ id: 7, first_name: 'Jordan', city: 'Katy', state: 'Texas', tip: 'Math lab daily.', on: '2026-11-22T21:00:00Z', congrats: 12, sent: false, mine: false }] };
    mockApi.GET.mockResolvedValue(ok({ data: feed }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...feed, passes: [{ ...feed.passes[0], sent: true, congrats: 13 }] } }));
    await render(wrap(<PassesScreen />));

    expect(await screen.findByText('Jordan from Katy passed the Texas exam!')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Send congrats' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Send congrats' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/licence-journey/passes/{pass}/congrats', { params: { path: { pass: 7 } }, body: { message: 'Congratulations, Jordan!' } });
    expect(await screen.findByText('Congrats sent')).toBeTruthy();
  });

  it('accepts a broker offer', async () => {
    const offer = { id: 'o-1', brokerage: 'Gulf Coast Homes', office_city: 'Katy', split_percent: 70, monthly_fee_cents: 9900, fees: null, training: null, mentoring: true, message: null, status: 'open', rating: 4.8, ratings: 23, fit: true, contact: null };
    mockApi.GET.mockResolvedValue(ok({ data: { opted_in: true, city: 'Katy', offers: [offer] } }));
    mockApi.POST.mockResolvedValue(ok({ data: { opted_in: true, city: 'Katy', offers: [{ ...offer, status: 'accepted' }] } }));
    await render(wrap(<OffersScreen />));

    expect(await screen.findByText('70% to you')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Accept' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/licence-journey/offers/{offer}/accept', { params: { path: { offer: 'o-1' } } });
    expect(await screen.findByText(/Gulf Coast Homes has your contact details/)).toBeTruthy();
  });
});
