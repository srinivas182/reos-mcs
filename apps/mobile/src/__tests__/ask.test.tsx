import { act, fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import AskScreen from '@/app/ask/index';
import type { AskThread } from '@/lib/ask';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
  useLocalSearchParams: jest.fn(() => ({})),
}));
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const thread: AskThread = {
  uuid: '6f1e2d3c-4b5a-4c6d-8e7f-9a0b1c2d3e4f',
  title: 'When is the IABS form given?',
  updated_at: '2026-11-23T15:00:00Z',
  messages: [
    { id: 1, role: 'question', body: 'When is the IABS form given?', tier: null, mode: null, sources: [], quiz: null, math_kind: null, sponsor: null, created_at: null },
    {
      id: 2,
      role: 'answer',
      body: 'At the first substantive contact with a prospective client.',
      tier: 'content',
      mode: 'ask',
      sources: [{ lesson_id: 7, lesson: 'Agency basics', seconds: 200, page: null, heading: null, text: '…' }],
      quiz: null,
      math_kind: null,
      sponsor: null,
      created_at: null,
    },
  ],
};

beforeEach(() => {
  jest.clearAllMocks();
  mockApi.GET.mockImplementation(async (path: string) => (path === '/ask/package' ? ok({ data: { candidate: true, beyond_course: false, sponsor: null, credits_left: null } }) : ok({ data: [] })));
});

describe('Ask, the study mate (candidate screen 13, A10.1)', () => {
  it('answers from the course with the lesson to watch, and follows up on the last answer', async () => {
    mockApi.POST.mockResolvedValueOnce(ok({ data: thread }));
    await render(wrap(<AskScreen />));

    expect(await screen.findByText('Your study mate: answers from your courses, with the lesson to watch. Always free.')).toBeTruthy();
    await fireEvent.changeText(screen.getByLabelText('Your question'), 'When is the IABS form given?');
    await fireEvent.press(screen.getByRole('button', { name: 'Ask' }));

    expect(mockApi.POST).toHaveBeenCalledWith('/ask', { body: { mode: 'ask', question: 'When is the IABS form given?', thread: null } });
    expect(await screen.findByText('From your course content')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Watch at 3:20' }));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/learn/[id]', params: { id: '7', t: '200' } });

    mockApi.POST.mockResolvedValueOnce(ok({ data: thread }));
    await fireEvent.press(screen.getByRole('button', { name: 'Explain simpler' }));
    expect(mockApi.POST).toHaveBeenLastCalledWith('/ask', { body: { mode: 'simpler', question: null, thread: thread.uuid } });
  });

  it('asks at once from the Ask bar, and says plainly when the course does not cover it', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ q: 'Louisiana property taxes?' });
    mockApi.POST.mockResolvedValueOnce(
      ok({ data: { ...thread, messages: [thread.messages[0], { ...thread.messages[1], tier: 'limited', body: "That isn't covered in your Texas course content.", sources: [] }] } }),
    );
    await render(wrap(<AskScreen />));
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 5));
    });

    expect(mockApi.POST).toHaveBeenCalledWith('/ask', { body: { mode: 'ask', question: 'Louisiana property taxes?', thread: null } });
    expect(await screen.findByText('Not covered in your course')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Quiz me' })).toBeNull();
  });
});
