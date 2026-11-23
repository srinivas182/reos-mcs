import { act, fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react-native';
import * as Contacts from 'expo-contacts';
import * as Notifications from 'expo-notifications';
import { I18nextProvider } from 'react-i18next';

import { TodayTodos } from '@/features/todos/TodayTodos';
import { VoiceCaptureSheet } from '@/features/voice/VoiceCaptureSheet';
import { i18n } from '@/lib/i18n';
import { DONE_ACTION, handleTodoResponse, SNOOZE_ACTION, TODO_CATEGORY } from '@/lib/todoReminders';
import { blankDraft, flushTodoQueue, queued, saveTodo, setDone, type TodoRow } from '@/lib/todos';
import { cardItems, moveDay, moveMinutes, setRemind, toggleDate, understand, understandOnPhone } from '@/lib/voice';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ status: 'signedIn' }) }));
jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return { useFocusEffect: (effect: () => void) => useEffect(effect, [effect]) };
});

// eslint-disable-next-line @typescript-eslint/no-require-imports
const speech = require('expo-speech-recognition') as { __emit: (name: string, event: unknown) => void; ExpoSpeechRecognitionModule: Record<string, jest.Mock> };

const ok = (data: unknown, status = 200) => ({ data, response: { ok: true, status } });
const offline = () => Promise.reject(new TypeError('Network request failed'));

/** Saturday, November 21, 2026, 10:00 AM in Texas (the tests run in America/Chicago). */
const NOW = new Date('2026-11-21T16:00:00Z');

const todo = (overrides: object = {}) => ({
  id: 7,
  client_id: null,
  title: 'Call Maria',
  notes: null,
  due_at: '2026-11-21T16:00:00-06:00',
  due_date: null,
  remind_at: '2026-11-21T16:00:00-06:00',
  related: { type: 'phone_contact', id: 'c-42', label: 'Maria Lopez' },
  source: 'voice',
  done: false,
  done_at: null,
  calendar_event_id: 90,
  created_at: '2026-11-21T16:00:00Z',
  ...overrides,
});

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

const say = async (words: string) => {
  await act(() => {
    speech.__emit('result', { isFinal: true, results: [{ transcript: words, confidence: 0.9 }] });
    speech.ExpoSpeechRecognitionModule.stop!();
  });
};

beforeEach(async () => {
  jest.clearAllMocks();
  jest.useFakeTimers({ now: NOW, advanceTimers: true });
  (Notifications.getPermissionsAsync as jest.Mock).mockResolvedValue({ granted: true });
  (Contacts.Contact.getAllDetails as jest.Mock).mockResolvedValue([{ id: 'c-42', fullName: 'Maria Lopez' }]);
  mockApi.GET.mockResolvedValue(ok({ data: [] }));
  mockApi.DELETE.mockResolvedValue(ok({}));
  // Empty the phone's queue between tests.
  mockApi.POST.mockResolvedValue(ok({ data: todo() }, 201));
  await flushTodoQueue();
  jest.clearAllMocks();
});
afterEach(() => {
  jest.useRealTimers();
  void i18n.changeLanguage('en');
});

describe('Reading what was said (B4.3b)', () => {
  it('reads "Remind me to call Maria at 4" on the phone with no signal: today at 4 PM, Maria, remind', () => {
    expect(understandOnPhone('Remind me to call Maria at 4', 'en', NOW)).toEqual([
      { title: 'Call Maria', notes: null, people: ['Maria'], due_at: '2026-11-21T16:00:00-06:00', due_date: null, remind_at: '2026-11-21T16:00:00-06:00' },
    ]);
    expect(understandOnPhone('Recuérdame llamar a María mañana', 'es', NOW)[0]).toMatchObject({ title: 'Llamar a María', people: ['María'], due_at: '2026-11-22T09:00:00-06:00' });
  });

  it("uses the server's answer, and the phone's own reading when there's no signal", async () => {
    mockApi.POST.mockResolvedValueOnce(ok({ engine: 'ai', items: [{ title: 'Send Maria the comps', notes: 'Oak Ridge', people: ['Maria'], due_at: null, due_date: '2026-11-23', remind_at: null }], credits: { used: 1, left: 499 } }));
    await expect(understand('Send Maria the comps on Monday, the Oak Ridge ones', 'en', NOW)).resolves.toMatchObject({ engine: 'ai', items: [{ title: 'Send Maria the comps' }] });
    expect(mockApi.POST).toHaveBeenCalledWith('/voice/parse', { body: { text: 'Send Maria the comps on Monday, the Oak Ridge ones', language: 'en', timezone: 'America/Chicago' } });

    mockApi.POST.mockImplementationOnce(offline);
    await expect(understand('Remind me to call Maria at 4', 'en', NOW)).resolves.toMatchObject({ engine: 'phone', items: [{ title: 'Call Maria', remind_at: '2026-11-21T16:00:00-06:00' }] });
  });

  it('moves the due date and time on the card, keeping the reminder in step', () => {
    const [item] = cardItems(understandOnPhone('Remind me to call Maria at 4', 'en', NOW), 'voice');
    const later = moveMinutes(moveDay(item!.draft, 1), 15);
    expect(later).toMatchObject({ due_at: '2026-11-22T16:15:00-06:00', remind_at: '2026-11-22T16:15:00-06:00' });
    expect(setRemind(later, false).remind_at).toBeNull();
    expect(toggleDate(later)).toMatchObject({ due_at: null, due_date: null, remind_at: null });
    expect(toggleDate({ ...blankDraft('typed'), title: 'x' }, NOW)).toMatchObject({ due_at: '2026-11-21T11:00:00-06:00', remind_at: '2026-11-21T11:00:00-06:00' });
    expect(moveDay({ ...blankDraft('typed'), due_date: '2026-11-30' }, 1).due_date).toBe('2026-12-01');
  });
});

describe('Voice capture and the confirm card (agent app journey, Today; B4.3b)', () => {
  it('done when: "Remind me to call Maria at 4" saves the to-do linked to Maria, with a 4 PM lock-screen reminder with Done and Snooze', async () => {
    const onSaved = jest.fn();
    mockApi.POST.mockImplementation(async (path: string) =>
      path === '/voice/parse'
        ? ok({ engine: 'ai', items: [{ title: 'Call Maria', notes: null, people: ['Maria'], due_at: '2026-11-21T16:00:00-06:00', due_date: null, remind_at: '2026-11-21T16:00:00-06:00' }], credits: { used: 1, left: 499 } })
        : ok({ data: todo() }, 201),
    );
    await wrap(<VoiceCaptureSheet mode="voice" onClose={jest.fn()} onSaved={onSaved} />);

    // The mic opened it, so it is already listening, on the device.
    expect(speech.ExpoSpeechRecognitionModule.start).toHaveBeenCalledWith(expect.objectContaining({ lang: 'en-US', interimResults: true, requiresOnDeviceRecognition: true }));
    expect(screen.getByRole('button', { name: 'Listening… tap to stop' })).toBeOnTheScreen();

    await say('Remind me to call Maria at 4');

    await waitFor(() => expect(screen.getByText('Save this to-do?')).toBeOnTheScreen());
    expect(screen.getByDisplayValue('Call Maria')).toBeOnTheScreen();
    expect(screen.getByLabelText('Date: Saturday, November 21')).toBeOnTheScreen();
    expect(screen.getByLabelText('Time: 4:00 PM')).toBeOnTheScreen();
    expect(screen.getByRole('radio', { name: 'Link to Maria Lopez' })).toBeChecked();
    expect(Contacts.Contact.getAllDetails).toHaveBeenCalledWith(['fullName'], { name: 'Maria', limit: 5 });

    await fireEvent.press(screen.getByRole('button', { name: 'Save to-do' }));

    await waitFor(() => expect(onSaved).toHaveBeenCalled());
    const [, saved] = mockApi.POST.mock.calls.find(([path]) => path === '/todos')!;
    expect(saved.body).toMatchObject({
      title: 'Call Maria',
      due_at: '2026-11-21T16:00:00-06:00',
      remind_at: '2026-11-21T16:00:00-06:00',
      related_type: 'phone_contact',
      related_id: 'c-42',
      related_label: 'Maria Lopez',
      source: 'voice',
    });
    expect(saved.body.client_id).toMatch(/^[0-9a-f-]{36}$/);

    expect(Notifications.setNotificationCategoryAsync).toHaveBeenCalledWith(TODO_CATEGORY, [
      { identifier: 'done', buttonTitle: 'Done', options: { opensAppToForeground: false } },
      { identifier: 'snooze', buttonTitle: 'Snooze 10 min', options: { opensAppToForeground: false } },
    ]);
    expect(Notifications.scheduleNotificationAsync).toHaveBeenCalledWith({
      identifier: `reos.todo.${saved.body.client_id}`,
      content: expect.objectContaining({ title: 'REOS to-do', body: 'Call Maria', categoryIdentifier: TODO_CATEGORY, data: { kind: 'todo', clientId: saved.body.client_id, id: 7, title: 'Call Maria' } }),
      trigger: { type: 'date', date: new Date('2026-11-21T22:00:00Z'), channelId: 'todos' },
    });
    expect(await screen.findByText('To-do saved.')).toBeOnTheScreen();
  });

  it('lets the person pick which Maria, or none, and edit before saving', async () => {
    (Contacts.Contact.getAllDetails as jest.Mock).mockResolvedValue([
      { id: 'c-42', fullName: 'Maria Lopez' },
      { id: 'c-43', fullName: 'Maria Chen' },
    ]);
    mockApi.POST.mockImplementation(async (path: string) =>
      path === '/voice/parse'
        ? ok({ engine: 'parser', items: [{ title: 'Call Maria', notes: null, people: ['Maria'], due_at: '2026-11-21T16:00:00-06:00', due_date: null, remind_at: '2026-11-21T16:00:00-06:00' }], credits: { used: 0, left: 500 } })
        : ok({ data: todo() }, 201),
    );
    await wrap(<VoiceCaptureSheet mode="type" onClose={jest.fn()} onSaved={jest.fn()} />);

    await fireEvent.changeText(screen.getByLabelText('What to do'), 'Remind me to call Maria at 4');
    await fireEvent.press(screen.getByRole('button', { name: 'Continue' }));

    expect(await screen.findByLabelText('Who is “Maria”?')).toBeOnTheScreen();
    expect(screen.getByText('Read without AI. Check the date and time.')).toBeOnTheScreen();
    expect(screen.getByRole('radio', { name: "Don't link" })).toBeChecked();
    await fireEvent.press(screen.getByRole('radio', { name: 'Maria Chen' }));
    await fireEvent.press(screen.getByRole('button', { name: '15 minutes later' }));
    await fireEvent(screen.getByRole('switch', { name: 'Remind me' }), 'valueChange', false);
    await fireEvent.press(screen.getByRole('button', { name: 'Save to-do' }));

    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/todos', expect.anything()));
    expect(mockApi.POST.mock.calls.find(([path]) => path === '/todos')![1].body).toMatchObject({
      due_at: '2026-11-21T16:15:00-06:00',
      remind_at: null,
      related_id: 'c-43',
      related_label: 'Maria Chen',
      source: 'typed',
    });
    expect(Notifications.scheduleNotificationAsync).not.toHaveBeenCalled();
  });

  it('with no signal, reads it on the phone, keeps it, still sets the reminder, and sends it once back online', async () => {
    mockApi.POST.mockImplementation(offline);
    await wrap(<VoiceCaptureSheet mode="voice" onClose={jest.fn()} onSaved={jest.fn()} />);
    await say('Remind me to call Maria at 4');

    await waitFor(() => expect(screen.getByText(/No signal: REOS read this on your phone/)).toBeOnTheScreen());
    await fireEvent.press(screen.getByRole('button', { name: 'Save to-do' }));

    await waitFor(() => expect(queued()).toHaveLength(1));
    expect(await screen.findByText("Saved on your phone. It syncs when you're back online.")).toBeOnTheScreen();
    expect(Notifications.scheduleNotificationAsync).toHaveBeenCalledWith(expect.objectContaining({ trigger: expect.objectContaining({ date: new Date('2026-11-21T22:00:00Z') }) }));
    const clientId = (queued()[0] as { draft: { clientId: string } }).draft.clientId;

    // Done from the lock screen while still offline: kept too, in order.
    await handleTodoResponse({ actionIdentifier: DONE_ACTION, notification: { request: { identifier: `reos.todo.${clientId}`, content: { data: { kind: 'todo', clientId, id: null, title: 'Call Maria' } } } } } as never, NOW);
    expect(queued()).toHaveLength(2);

    mockApi.POST.mockClear();
    mockApi.POST.mockImplementation(async (path: string) => (path === '/todos' ? ok({ data: todo({ client_id: clientId }) }, 201) : ok({ data: todo({ done: true }) })));
    await expect(flushTodoQueue()).resolves.toBe(2);

    expect(mockApi.POST.mock.calls.map(([path, options]) => [path, options?.body?.client_id ?? options?.params?.path?.todo])).toEqual([
      ['/todos', clientId],
      ['/todos/{todo}/done', 7],
    ]);
    expect(queued()).toEqual([]);
  });

  it('says so when the microphone is not allowed, and offers typing', async () => {
    speech.ExpoSpeechRecognitionModule.requestPermissionsAsync!.mockResolvedValueOnce({ granted: false });
    await wrap(<VoiceCaptureSheet mode="voice" onClose={jest.fn()} onSaved={jest.fn()} />);

    expect(await screen.findByText(/REOS needs the microphone and speech recognition/)).toBeOnTheScreen();
    expect(speech.ExpoSpeechRecognitionModule.start).not.toHaveBeenCalled();
    await fireEvent.press(screen.getByRole('button', { name: 'Type it instead' }));
    expect(screen.getByLabelText('What to do')).toBeOnTheScreen();
  });

  it('works in Spanish', async () => {
    await i18n.changeLanguage('es');
    mockApi.POST.mockImplementation(offline);
    await wrap(<VoiceCaptureSheet mode="voice" onClose={jest.fn()} onSaved={jest.fn()} />);
    expect(speech.ExpoSpeechRecognitionModule.start).toHaveBeenCalledWith(expect.objectContaining({ lang: 'es-US' }));

    await say('Recuérdame llamar a María a las 4');

    await waitFor(() => expect(screen.getByText('¿Guardar esta tarea?')).toBeOnTheScreen());
    expect(screen.getByDisplayValue('Llamar a María')).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Guardar tarea' })).toBeOnTheScreen();
  });
});

describe('Lock-screen reminders (B4.3b)', () => {
  const response = (actionIdentifier: string) =>
    ({ actionIdentifier, notification: { request: { identifier: 'reos.todo.abc', content: { data: { kind: 'todo', clientId: 'abc', id: 7, title: 'Call Maria' } } } } }) as never;

  it('Done marks the to-do done without opening the app', async () => {
    mockApi.POST.mockResolvedValue(ok({ data: todo({ done: true }) }));
    await handleTodoResponse(response(DONE_ACTION), NOW);

    expect(mockApi.POST).toHaveBeenCalledWith('/todos/{todo}/done', { params: { path: { todo: 7 } } });
    expect(Notifications.dismissNotificationAsync).toHaveBeenCalledWith('reos.todo.abc');
  });

  it('Snooze rings again in 10 minutes', async () => {
    await handleTodoResponse(response(SNOOZE_ACTION), NOW);

    expect(mockApi.POST).not.toHaveBeenCalled();
    expect(Notifications.scheduleNotificationAsync).toHaveBeenCalledWith(
      expect.objectContaining({ identifier: 'reos.todo.abc', trigger: { type: 'date', date: new Date(NOW.getTime() + 600_000), channelId: 'todos' } }),
    );
  });
});

describe('To-dos on Today (B4.3b)', () => {
  const rows: TodoRow[] = [
    { key: 'cabc', id: null, clientId: 'abc', title: 'Text Jordan the lockbox code', due_at: null, due_date: null, contact: null, waiting: true },
    { key: 't7', id: 7, clientId: null, title: 'Call Maria', due_at: '2026-11-21T16:00:00-06:00', due_date: null, contact: 'Maria Lopez', waiting: false },
    { key: 't8', id: 8, clientId: null, title: 'Order sign rider', due_at: '2026-11-21T08:00:00-06:00', due_date: null, contact: null, waiting: false },
  ];

  it('lists each with when and who, marks the waiting and overdue ones, and Done has Undo', async () => {
    const onChanged = jest.fn();
    mockApi.POST.mockResolvedValue(ok({ data: todo({ done: true }) }));
    await wrap(<TodayTodos rows={rows} onChanged={onChanged} onAdd={jest.fn()} />);

    expect(screen.getByText('Sat, Nov 21, 4:00 PM · With Maria Lopez')).toBeOnTheScreen();
    expect(screen.getByText('Waiting to sync')).toBeOnTheScreen();
    expect(screen.getByText('Overdue')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Mark “Call Maria” done' }));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/todos/{todo}/done', { params: { path: { todo: 7 } } }));
    expect(Notifications.cancelScheduledNotificationAsync).toHaveBeenCalledWith('reos.todo.7');
    expect(onChanged).toHaveBeenCalled();

    await fireEvent.press(await screen.findByRole('button', { name: 'Undo' }));
    await waitFor(() => expect(mockApi.DELETE).toHaveBeenCalledWith('/todos/{todo}/done', { params: { path: { todo: 7 } } }));
  });

  it('shows an empty state that starts capture', async () => {
    const onAdd = jest.fn();
    await wrap(<TodayTodos rows={[]} onChanged={jest.fn()} onAdd={onAdd} />);

    expect(screen.getByText('Your to-dos live here')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Add a to-do' }));
    expect(onAdd).toHaveBeenCalled();
  });
});

describe('The offline queue (B4.3b)', () => {
  it('drops what the server refuses for good and keeps what it could not reach', async () => {
    mockApi.POST.mockImplementationOnce(offline);
    const draft = { ...blankDraft('typed'), title: 'Call Maria' };
    await expect(saveTodo(draft)).resolves.toEqual({ todo: null, outcome: 'offline' });
    await expect(setDone({ id: 9, clientId: null })).resolves.toBe('saved');

    mockApi.POST.mockImplementationOnce(offline);
    await expect(flushTodoQueue()).resolves.toBe(0);
    expect(queued()).toHaveLength(1);

    mockApi.POST.mockResolvedValueOnce({ data: undefined, response: { ok: false, status: 422 } });
    await expect(flushTodoQueue()).resolves.toBe(0);
    expect(queued()).toEqual([]);
  });
});

describe('The "Add a to-do" home-screen shortcut (B4.3b)', () => {
  it('is offered only when signed in, in the language on screen, and opens /voice', async () => {
    const QuickActions = jest.requireMock('expo-quick-actions') as { setItems: jest.Mock };
    const { useShortcuts } = jest.requireActual<typeof import('@/lib/shortcuts')>('@/lib/shortcuts');
    const { rerender } = await renderHook(({ signedIn }: { signedIn: boolean }) => useShortcuts(signedIn), {
      initialProps: { signedIn: false },
      wrapper: ({ children }) => <I18nextProvider i18n={i18n}>{children}</I18nextProvider>,
    });
    expect(QuickActions.setItems).toHaveBeenLastCalledWith([]);

    await rerender({ signedIn: true });
    expect(QuickActions.setItems).toHaveBeenLastCalledWith([
      { id: 'reos.voice', title: 'Add a to-do', subtitle: 'Say it, REOS saves it', icon: 'symbol:mic.fill', params: { href: '/voice' } },
      { id: 'reos.quiz', title: 'Quiz me', subtitle: 'Hands-free voice quiz', icon: 'symbol:headphones', params: { href: '/practice/hands-free' } },
    ]);
  });
});
