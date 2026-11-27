import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import FeedbackScreen from '@/app/feedback';
import type { FeedbackView } from '@/lib/feedback';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };

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
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const waiting: FeedbackView = {
  to_rate: [{ deal: 'd-1', property: '88 Oak Ridge Dr, Katy', closed_at: '2026-11-24T18:00:00Z', agent: { id: 21, name: 'Priya Shah' } }],
  mine: { count: 2, needed: 3, shown: false, average: null, items: [], tip: null, public: false },
};
const shown: FeedbackView = {
  to_rate: [],
  mine: {
    count: 3,
    needed: 3,
    shown: true,
    average: { overall: 3.7, communication: 3, responsiveness: 3, professionalism: 5 },
    items: [{ id: 'f1', month: '2026-11', communication: 2, responsiveness: 2, professionalism: 5, comment: 'Slow to reply.', response: null, reported: false }],
    tip: { dimension: 'communication', text: 'Send a short update at each step.', engine: 'template' },
    public: false,
  },
};

beforeEach(() => jest.clearAllMocks());

describe('Feedback from agents on the phone (A13.3b: screen 125)', () => {
  it('rates the other side\'s agent, and shows nothing of mine until three ratings', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: waiting }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...waiting, to_rate: [] } }));
    await render(wrap(<FeedbackScreen />));

    expect(await screen.findByText('2 agents have rated you. Your feedback shows once 3 have.')).toBeTruthy();
    for (const star of screen.getAllByLabelText('4 stars')) await fireEvent.press(star);
    await fireEvent.press(screen.getByLabelText('Send feedback'));
    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/feedback', { body: { deal: 'd-1', agent: 21, communication: 4, responsiveness: 4, professionalism: 4, comment: null } }),
    );
  });

  it('shows the averages and tip, and reports with Undo', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: shown }));
    mockApi.POST.mockResolvedValue(ok({ data: shown }));
    await render(wrap(<FeedbackScreen />));

    expect(await screen.findByText('3.7 out of 5 from 3 agents')).toBeTruthy();
    expect(screen.getByText('Send a short update at each step.')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Report'));
    await fireEvent.changeText(screen.getByLabelText('Why are you reporting this?'), 'Not about the deal.');
    await fireEvent.press(screen.getByLabelText('Report it'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/feedback/{feedback}/report', { params: { path: { feedback: 'f1' } }, body: { reason: 'Not about the deal.' } }));
    await fireEvent.press(await screen.findByText('Undo'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/feedback/{feedback}/report', { params: { path: { feedback: 'f1' } }, body: { reason: null } }));
  });
});
