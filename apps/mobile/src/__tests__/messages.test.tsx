import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import ConversationScreen from '@/app/messages/[id]';
import MessagesScreen from '@/app/messages';
import NewMessageScreen from '@/app/messages/new';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const now = new Date();
const later = new Date(now.getTime() + 4 * 60_000).toISOString();

const mockConversation = {
  id: 'c-1',
  kind: 'direct' as const,
  title: 'Linda Park',
  people: [{ id: 2, name: 'Linda Park' }],
  reads: [{ user: 2, read_at: new Date(now.getTime() + 60_000).toISOString() }],
  older: false,
  blocked: null,
  messages: [
    { id: 'm-1', body: 'Can you look at the Elm St offer?', sender: { id: 7, name: 'James Miller' }, reply_to: null, edited: false, deleted: false, created_at: now.toISOString(), changeable_until: later, attachments: [] },
    { id: 'm-2', body: 'On it.', sender: { id: 2, name: 'Linda Park' }, reply_to: { id: 'm-1', body: 'Can you look at the Elm St offer?', sender: 'James Miller', deleted: false }, edited: false, deleted: false, created_at: now.toISOString(), changeable_until: null, attachments: [{ id: 'f-1', name: 'elm-st-offer.pdf', mime: 'application/pdf', size: 1000, image: false, status: 'pending', url: null }] },
  ],
};

const mockSend = jest.fn().mockResolvedValue(true);
const mockStart = jest.fn().mockResolvedValue('c-9');
const mockReport = jest.fn().mockResolvedValue(true);
const mockBlock = jest.fn().mockResolvedValue(true);
jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() }, useLocalSearchParams: () => ({ id: 'c-1' }), useFocusEffect: jest.fn() }));
jest.mock('@/lib/realtime', () => ({ listen: () => () => undefined }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ user: { id: 7, name: 'James Miller' } }) }));
jest.mock('@/lib/messages', () => ({
  ...jest.requireActual('@/lib/messages'),
  useInbox: () => ({
    inbox: { unread: 1, conversations: [{ id: 'c-1', kind: 'direct', title: 'Linda Park', people: [], last_message: { body: 'On it.', sender: 'Linda Park', deleted: false, files: 0, at: new Date().toISOString() }, unread: 1, blocked: null, updated_at: null }] },
    reload: jest.fn(),
  }),
  useConversation: () => ({ conversation: mockConversation, failed: false, reload: jest.fn() }),
  sendMessage: (...args: unknown[]) => mockSend(...args),
  editMessage: jest.fn().mockResolvedValue(true),
  deleteMessage: jest.fn().mockResolvedValue(true),
  startConversation: (...args: unknown[]) => mockStart(...args),
  reportConversation: (...args: unknown[]) => mockReport(...args),
  setBlocked: (...args: unknown[]) => mockBlock(...args),
  fetchContacts: jest.fn().mockResolvedValue([
    { id: 2, name: 'Linda Park', role: 'broker', organisation: 'Lone Star Realty' },
    { id: 3, name: 'Ana Okoye', role: 'agent', organisation: 'Okoye Team' },
  ]),
}));

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

describe('Messages on the phone (B6.1)', () => {
  it('lists conversations with unread counts and opens one', async () => {
    const { router } = jest.requireMock('expo-router') as { router: { push: jest.Mock } };
    await wrap(<MessagesScreen />);
    expect(screen.getByText('Linda Park')).toBeOnTheScreen();
    expect(screen.getByText('Linda Park: On it.')).toBeOnTheScreen();
    await fireEvent.press(screen.getByLabelText(/Linda Park\. Linda Park: On it\.\. 1 unread message/));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/messages/[id]', params: { id: 'c-1' } });
  });

  it('shows the conversation with Read, replies, and edit for 5 minutes on your own', async () => {
    await wrap(<ConversationScreen />);
    expect(screen.getByLabelText('You: Can you look at the Elm St offer?')).toBeOnTheScreen();
    expect(screen.getByText(/Read/)).toBeOnTheScreen();
    // Edit and delete only on James's own message.
    expect(screen.getAllByRole('button', { name: 'Edit' })).toHaveLength(1);

    await fireEvent.press(screen.getByRole('button', { name: 'Reply to Linda Park' }));
    expect(screen.getByText('Replying to Linda Park')).toBeOnTheScreen();
    await fireEvent.changeText(screen.getByLabelText('Message'), 'Thanks!');
    await fireEvent.press(screen.getByRole('button', { name: 'Send' }));
    await waitFor(() => expect(mockSend).toHaveBeenCalledWith('c-1', 'Thanks!', 'm-2', []));
  });

  it('starts a new message with people you work with', async () => {
    const { router } = jest.requireMock('expo-router') as { router: { replace: jest.Mock } };
    await wrap(<NewMessageScreen />);
    await waitFor(() => expect(screen.getByText('Ana Okoye')).toBeOnTheScreen());
    await fireEvent.press(screen.getByText('Linda Park'));
    await fireEvent.changeText(screen.getByLabelText('Message'), 'Hi Linda');
    await fireEvent.press(screen.getByRole('button', { name: 'Send' }));
    await waitFor(() => expect(mockStart).toHaveBeenCalledWith([2], 'Hi Linda', null));
    expect(router.replace).toHaveBeenCalledWith({ pathname: '/messages/[id]', params: { id: 'c-9' } });
  });

  it('shows files being checked, and reports or blocks from the conversation', async () => {
    await wrap(<ConversationScreen />);
    expect(screen.getByText('elm-st-offer.pdf')).toBeOnTheScreen();
    expect(screen.getByText('Checking for viruses…')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Block Linda Park' }));
    await waitFor(() => expect(mockBlock).toHaveBeenCalledWith(2, true));

    await fireEvent.press(screen.getByRole('button', { name: 'Report' }));
    await fireEvent.press(screen.getByText('Spam or selling'));
    await fireEvent.press(screen.getByRole('button', { name: 'Send report' }));
    await waitFor(() => expect(mockReport).toHaveBeenCalledWith('c-1', 'spam', null, null, true));
  });
});
