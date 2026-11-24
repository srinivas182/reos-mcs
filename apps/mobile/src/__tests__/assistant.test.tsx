import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';
import { Linking } from 'react-native';

import { AgentAssistant } from '@/features/assistant/AgentAssistant';
import type { AssistantChat } from '@/lib/assistant';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return { router: { push: jest.fn(), back: jest.fn() }, useFocusEffect: (effect: () => void) => useEffect(effect, [effect]) };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/voice', () => ({ speechLanguage: () => 'en-US', useSpeech: () => ({ state: 'idle', transcript: '', start: jest.fn(), stop: jest.fn() }) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const home = (permission: boolean) => ok({ data: { permission, starters: ['virtual_broker', 'review_contract', 'prepare_contract', 'write_ad', 'reply_emails', 'prep_appointment'], chats: [] } });
const chat = (answer: Partial<AssistantChat['messages'][number]>): AssistantChat => ({
  id: 'c-1', title: 'What should I do today?', updated_at: '2026-11-24T16:00:00Z',
  messages: [
    { id: 1, role: 'question', body: 'What should I do today?', kind: null, items: [], draft: null, broker_question: null, created_at: null },
    { id: 2, role: 'answer', body: '', kind: 'today', items: [], draft: null, broker_question: null, created_at: null, ...answer },
  ],
});

beforeEach(() => jest.clearAllMocks());

describe('Ask home for agents (A12.3: screens 59 and 60)', () => {
  it('shows the six starters and opens the virtual broker and contract review', async () => {
    mockApi.GET.mockResolvedValue(home(true));
    await render(wrap(<AgentAssistant />));

    expect(await screen.findByText('Ask your virtual broker')).toBeTruthy();
    expect(screen.getByText('Coming soon')).toBeTruthy();
    await fireEvent.press(screen.getByText('Review a contract'));
    expect(router.push).toHaveBeenCalledWith('/contracts');
  });

  it('asks for permission, then answers "What should I do today?" with each item\'s source', async () => {
    mockApi.GET.mockResolvedValue(home(false));
    mockApi.POST.mockResolvedValueOnce(ok({ data: chat({ kind: 'permission', body: 'To answer from your CRM, deals and calendar, REOS needs your permission.' }) }));
    await render(wrap(<AgentAssistant />));

    expect(await screen.findByText('Use my CRM, deals and calendar')).toBeTruthy();
    await fireEvent.press(screen.getByText('What should I do today?'));
    expect(await screen.findByText(/needs your permission/)).toBeTruthy();

    mockApi.PUT.mockResolvedValue(ok({ data: { permission: true } }));
    mockApi.POST.mockResolvedValueOnce(
      ok({
        data: chat({
          body: "Here's your day: 2 things. Call Maria Lopez first.",
          items: [
            { title: 'Call Maria back', detail: 'Overdue', source: 'Follow Up Boss', at: null, link: null },
            { title: 'Option period ends', detail: 'Deadline · 1420 Pecan St', source: 'dotloop', at: null, link: '/deals?deal=0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e77' },
          ],
        }),
      }),
    );
    await fireEvent.press(screen.getByText('Allow'));
    expect(mockApi.PUT).toHaveBeenCalledWith('/assistant/permission', { body: { allowed: true } });
    expect(await screen.findByText('Overdue · Follow Up Boss')).toBeTruthy();
    await fireEvent.press(screen.getByText('Option period ends'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/deals/[uuid]', params: { uuid: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e77' } });
  });

  it('opens a drafted text in Messages, never sending it', async () => {
    const open = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
    mockApi.GET.mockResolvedValue(home(true));
    mockApi.POST.mockResolvedValue(ok({ data: chat({ kind: 'draft', body: "Here's a text to Maria Lopez.", draft: { channel: 'text', to: '+15125550142', name: 'Maria Lopez', subject: null, body: 'Hi Maria, just checking in.', href: 'sms:+15125550142?&body=Hi%20Maria' } }) }));
    await render(wrap(<AgentAssistant />));

    await fireEvent.changeText(await screen.findByPlaceholderText('For example: What should I do today?'), 'Text Maria to check in');
    await fireEvent.press(screen.getByLabelText('Ask'));
    await fireEvent.press(await screen.findByText('Open in Messages'));
    expect(open).toHaveBeenCalledWith('sms:+15125550142?&body=Hi%20Maria');
  });
});
