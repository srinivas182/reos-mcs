
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import { TodayAgenda } from '@/features/calendar/TodayAgenda';
import { blankForm, type CalendarEvent, localIso, payload } from '@/lib/calendar';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ status: 'signedIn' }) }));
jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return { useFocusEffect: (effect: () => void) => useEffect(effect, [effect]) };
});

const event = (overrides: Partial<CalendarEvent> = {}): CalendarEvent => ({
  id: 41,
  title: 'Showing — 1234 Oak Ridge Dr',
  kind: 'showing',
  starts_at: '2026-11-21T20:00:00+00:00',
  ends_at: '2026-11-21T20:45:00+00:00',
  all_day: false,
  start_date: null,
  end_date: null,
  location: '1234 Oak Ridge Dr, Cypress, TX',
  description: null,
  attendees: [],
  notify_guests: false,
  busy: false,
  source: 'reos',
  tool: 'Google Calendar',
  calendar: 'linda.park@demo.reos.test',
  link: null,
  editable: true,
  deletable: true,
  sync: 'synced',
  sync_error: null,
  conflict: null,
  ...overrides,
});

const day = (data: CalendarEvent[], extra: object = {}) => ({
  data: {
    data,
    write_to: { connection: 7, tool: 'Google Calendar', account: 'linda.park@demo.reos.test', calendar: 'linda.park@demo.reos.test' },
    removed: [],
    ...extra,
  },
});

const renderAgenda = () =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>
        <TodayAgenda />
      </ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  jest.clearAllMocks();
  jest.useFakeTimers({ now: new Date('2026-11-21T15:20:00Z'), advanceTimers: true });
  mockApi.POST.mockResolvedValue({ response: { ok: true } });
  mockApi.PUT.mockResolvedValue({ response: { ok: true } });
  mockApi.DELETE.mockResolvedValue({ response: { ok: true } });
});
afterEach(() => {
  jest.useRealTimers();
  void i18n.changeLanguage('en');
});

describe('Today: the calendar (agent app journey; B4.1)', () => {
  it('leaves to-dos to the To-dos list below (B4.3b), so they are not shown twice', async () => {
    mockApi.GET.mockResolvedValue(day([event(), event({ id: 44, title: 'Call Maria', kind: 'todo', source: 'reos' })]));
    await renderAgenda();

    await waitFor(() => expect(screen.getByRole('button', { name: 'Showing — 1234 Oak Ridge Dr. 2:00 PM · Google Calendar' })).toBeOnTheScreen());
    expect(screen.queryByText('Call Maria')).toBeNull();
  });

  it("asks for today in the phone's time zone and lists each event with its time and tool", async () => {
    mockApi.GET.mockResolvedValue(day([event({ id: 40, title: 'Option period ends', kind: 'deadline', all_day: true, start_date: '2026-11-21', end_date: '2026-11-21' }), event(), event({ id: 43, title: null, busy: true, editable: false, deletable: false, source: 'google-calendar' })]));
    await renderAgenda();

    await waitFor(() => expect(screen.getByRole('button', { name: 'Showing — 1234 Oak Ridge Dr. 2:00 PM · Google Calendar' })).toBeOnTheScreen());
    expect(mockApi.GET).toHaveBeenCalledWith('/calendar/events', { params: { query: { from: '2026-11-21T00:00:00-06:00', to: '2026-11-22T00:00:00-06:00' } } });
    expect(screen.getByRole('button', { name: 'Option period ends. All day · Google Calendar' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Busy. 2:00 PM · Google Calendar' })).toBeOnTheScreen();
  });

  it('adds an appointment from the sheet, an hour from the next whole hour, guests not emailed', async () => {
    mockApi.GET.mockResolvedValue(day([]));
    await renderAgenda();

    await waitFor(() => expect(screen.getByText('Nothing on your calendar today')).toBeOnTheScreen());
    await fireEvent.press(screen.getByRole('button', { name: 'New appointment' }));
    expect(screen.getByText('Goes to linda.park@demo.reos.test in Google Calendar.')).toBeOnTheScreen();
    await fireEvent.changeText(screen.getByLabelText('What'), 'Buyer consultation — Ana Torres');
    await fireEvent.press(screen.getByRole('button', { name: '15 minutes later' }));
    await fireEvent.press(screen.getByRole('radio', { name: '45 min' }));
    await fireEvent.changeText(screen.getByLabelText('Guests'), 'ana.torres@example.com');
    expect(screen.getByText('Email the guests an invitation')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Add' }));

    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/calendar/events', {
        body: {
          title: 'Buyer consultation — Ana Torres',
          kind: 'appointment',
          all_day: false,
          // 10:15–11:00 AM in Houston.
          starts_at: '2026-11-21T16:15:00.000Z',
          ends_at: '2026-11-21T17:00:00.000Z',
          location: null,
          description: null,
          attendees: ['ana.torres@example.com'],
          notify_guests: false,
        },
      }),
    );
    await waitFor(() => expect(screen.getByText('Added: Buyer consultation — Ana Torres.')).toBeOnTheScreen());
  });

  it('deletes an event REOS made with Undo, and says why a busy one only shows its time', async () => {
    mockApi.GET.mockResolvedValue(day([event(), event({ id: 43, title: null, busy: true, editable: false, deletable: false, source: 'google-calendar' })]));
    await renderAgenda();

    await waitFor(() => expect(screen.getByRole('button', { name: /^Busy/ })).toBeOnTheScreen());
    await fireEvent.press(screen.getByRole('button', { name: /^Busy/ }));
    expect(screen.getByText('From a calendar shared as busy only, so only the time is shown.')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Close' }));

    await fireEvent.press(screen.getByRole('button', { name: /^Showing — 1234 Oak Ridge Dr/ }));
    await fireEvent.press(screen.getByRole('button', { name: 'Delete' }));
    await waitFor(() => expect(mockApi.DELETE).toHaveBeenCalledWith('/calendar/events/{event}', { params: { path: { event: 41 } } }));
    await waitFor(() => expect(screen.getByText('Deleted Showing — 1234 Oak Ridge Dr.')).toBeOnTheScreen());
    await fireEvent.press(screen.getByRole('button', { name: 'Undo' }));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/calendar/events/{event}/restore', { params: { path: { event: 41 } } }));
  });

  it('marks an event changed in two places, and uses the other version on request', async () => {
    mockApi.GET.mockResolvedValue(
      day([
        event({
          conflict: {
            id: 9,
            kept: 'tool',
            reason: 'edited_both',
            losing: { title: 'Showing — 1234 Oak Ridge Dr (REOS)', starts_at: '2026-11-21T21:00:00+00:00', ends_at: '2026-11-21T21:45:00+00:00', all_day: false, location: null, deleted: false },
            at: '2026-11-21T15:00:00+00:00',
            until: '2026-12-21T15:00:00+00:00',
          },
        }),
      ]),
    );
    await renderAgenda();

    await waitFor(() => expect(screen.getByText('Changed in two places')).toBeOnTheScreen());
    await fireEvent.press(screen.getByRole('button', { name: /^Showing — 1234 Oak Ridge Dr/ }));
    expect(screen.getByText('Changed in Google Calendar too. That change was newer, so it was kept.')).toBeOnTheScreen();
    expect(screen.getByText('The other version: Showing — 1234 Oak Ridge Dr (REOS), Sat, Nov 21, 3:00 PM. You can undo this until Dec 21.')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Use the other version' }));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/calendar/conflicts/{conflict}/undo', { params: { path: { conflict: 9 } } }));
  });

  it('shows a refused change from the server', async () => {
    mockApi.GET.mockResolvedValue(day([event()]));
    mockApi.PUT.mockResolvedValue({ response: { ok: false }, error: { errors: { ends_at: ['The ends at field must be a date after starts at.'] } } });
    await renderAgenda();

    await waitFor(() => expect(screen.getByRole('button', { name: /^Showing/ })).toBeOnTheScreen());
    await fireEvent.press(screen.getByRole('button', { name: /^Showing/ }));
    await fireEvent.press(screen.getByRole('button', { name: 'Save' }));
    await waitFor(() => expect(screen.getByText('The ends at field must be a date after starts at.')).toBeOnTheScreen());
  });

  it('speaks Spanish', async () => {
    await i18n.changeLanguage('es');
    mockApi.GET.mockResolvedValue(day([event()]));
    await renderAgenda();

    await waitFor(() => expect(screen.getByText('Hoy')).toBeOnTheScreen());
    expect(screen.getByRole('button', { name: 'Nueva cita' })).toBeOnTheScreen();
  });
});

describe('calendar helpers', () => {
  it('sends all-day appointments as dates, last day included', () => {
    const form = { ...blankForm(new Date(2026, 10, 27)), allDay: true, days: 2, title: ' Closing ' };

    expect(payload(form, true)).toMatchObject({ title: 'Closing', all_day: true, start_date: '2026-11-27', end_date: '2026-11-28' });
    expect(localIso(new Date(2026, 6, 1))).toBe('2026-07-01T00:00:00-05:00');
  });
});
