import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import VirtualBrokerScreen from '@/app/ask/broker';
import BrokerQuestionScreen from '@/app/ask/broker/[id]';
import { i18n } from '@/lib/i18n';
import type { BrokerQuestion } from '@/lib/virtualBroker';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: () => ({ id: 'q-1' }),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/voice', () => ({ speechLanguage: () => 'en-US', useSpeech: () => ({ state: 'idle', transcript: '', start: jest.fn(), stop: jest.fn() }) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const question = (over: Partial<BrokerQuestion> = {}): BrokerQuestion => ({
  id: 'q-1',
  question: 'How many days do I have to deposit the earnest money?',
  answer: "The policy manual and TREC's rule disagree here, and TREC's rule wins.\n\nUnder 22 TAC §535.146: Trust money goes into the trust account by the second working day.",
  confidence: 'high',
  sources: [{ kind: 'rule', title: '22 TAC §535.146', citation: '22 TAC §535.146', page: null, url: 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-146', effective_from: '2026-11-24', excerpt: 'Trust money a license holder receives goes into the trust account.' }],
  status: 'waiting',
  reason: 'money',
  escalated_at: '2026-11-24T15:00:00Z',
  due_at: '2026-11-24T19:00:00Z',
  broker_answer: null,
  broker: null,
  answered_at: null,
  added_to_knowledge: false,
  agent: 'James Miller',
  deal: null,
  created_at: '2026-11-24T15:00:00Z',
  ...over,
});

beforeEach(() => jest.clearAllMocks());

describe('The virtual broker on the phone (A12.1: screens 61 and 62)', () => {
  it('lists my questions and asks a new one', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { brokerage: 'Lone Star Realty', manual: true, reply_hours: 4, questions: [question()] } }));
    mockApi.POST.mockResolvedValue(ok({ data: question({ id: 'q-2' }) }));
    await render(wrap(<VirtualBrokerScreen />));

    expect(await screen.findByText("Trained on Lone Star Realty's policy manual")).toBeTruthy();
    expect(screen.getByText('With your broker')).toBeTruthy();
    await fireEvent.changeText(screen.getByLabelText("Ask Lone Star Realty's virtual broker"), 'Who approves my yard sign?');
    await fireEvent.press(screen.getByLabelText('Ask'));
    expect(mockApi.POST).toHaveBeenCalledWith('/virtual-broker/questions', { body: { question: 'Who approves my yard sign?' } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/ask/broker/[id]', params: { id: 'q-2' } });
  });

  it('says it is for agents at a brokerage when there is none', async () => {
    mockApi.GET.mockResolvedValue({ data: undefined, error: { message: 'Not found' }, response: { ok: false, status: 404 } });
    await render(wrap(<VirtualBrokerScreen />));

    expect(await screen.findByText('For agents at a brokerage')).toBeTruthy();
  });

  it('shows an escalated money question with the TREC source and the expected time', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: question() }));
    await render(wrap(<BrokerQuestionScreen />));

    expect(await screen.findByText('With your broker')).toBeTruthy();
    expect(screen.getByText(/Money is involved/)).toBeTruthy();
    expect(screen.getAllByText('22 TAC §535.146').length).toBeGreaterThan(0);
    expect(screen.queryByText('Send to my broker')).toBeNull();
  });

  it("labels the broker's answer", async () => {
    mockApi.GET.mockResolvedValueOnce(ok({ data: question({ status: 'corrected', broker: 'Linda Park', broker_answer: 'Deposit it by the second working day.' }) }));
    const { unmount } = await render(wrap(<BrokerQuestionScreen />));
    expect(await screen.findByText('From your broker, Linda Park')).toBeTruthy();
    expect(screen.getByText('Corrected')).toBeTruthy();
    unmount();
  });

  it('sends an AI answer to the broker', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: question({ status: 'answered', reason: null, due_at: null }) }));
    mockApi.POST.mockResolvedValue(ok({ data: question({ reason: 'asked' }) }));
    await render(wrap(<BrokerQuestionScreen />));
    await fireEvent.press(await screen.findByText('Send to my broker'));
    expect(mockApi.POST).toHaveBeenCalledWith('/virtual-broker/questions/{question}/escalate', { params: { path: { question: 'q-1' } } });
    expect(await screen.findByText('With your broker')).toBeTruthy();
  });
});
