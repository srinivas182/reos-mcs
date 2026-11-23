import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { Linking } from 'react-native';
import { I18nextProvider } from 'react-i18next';

import ContactsScreen from '@/app/contacts/index';
import CrmsScreen from '@/app/connections/crms';
import ContactScreen from '@/app/contacts/[id]';
import { TodayInsight } from '@/features/contacts/TodayInsight';
import type { Contact, ContactRow, Insight } from '@/lib/crm';
import { reachUri } from '@/lib/crm';
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
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ status: 'signedIn' }) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const maria: Contact = {
  id: '0193f0a2-7c1e-7000-8000-000000000001',
  name: 'Maria Lopez',
  first_name: 'Maria',
  last_name: 'Lopez',
  emails: ['maria.lopez@example.com'],
  phones: ['+18325550123'],
  stage: 'Active client',
  birthday: null,
  tags: [],
  sources: [{ source: 'follow-up-boss', name: 'Follow Up Boss', synced_at: null, campaign: null, campaign_status: null }],
  updated_at: null,
};

const insight: Insight = {
  summary: 'Maria is an active buyer in Katy.',
  next_step: 'Text Maria two Katy listings',
  why: 'No touch in 12 days; she asked about Katy schools.',
  channel: 'text',
  draft: "Hi Maria, it's James. Two Katy homes for you?",
  engine: 'ai',
  generated_at: '2026-11-23T15:00:00Z',
};

const row: ContactRow = { ...maria, segment: 'active', last_touch_at: null, next_step: { text: insight.next_step, why: insight.why, channel: 'text' } };

beforeEach(() => {
  jest.clearAllMocks();
});

describe('contacts on the phone (B8.3)', () => {
  it('lists everyone with tabs and search, and opens a contact', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: [row], counts: { all: 1, new: 0, active: 1, past: 0, sphere: 0 }, tags: [], total: 1 }));
    await render(wrap(<ContactsScreen />));

    expect(await screen.findByText('Maria Lopez')).toBeTruthy();
    expect(screen.getByText(/Text Maria two Katy listings/)).toBeTruthy();

    await fireEvent.press(screen.getByText('Active (1)'));
    await waitFor(() => expect(mockApi.GET).toHaveBeenLastCalledWith('/contacts', { params: { query: { segment: 'active' } } }));

    await fireEvent.press(screen.getByText('Maria Lopez'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/contacts/[id]', params: { id: maria.id } });
  });

  it('texts from the agent\'s own phone on Today, then logs it to the CRMs', async () => {
    const open = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
    mockApi.POST.mockResolvedValue(ok({ data: {} }));
    const done = jest.fn();
    await render(wrap(<TodayInsight contact={maria} insight={insight} onDone={done} />));

    expect(screen.getByText('DO THIS NOW')).toBeTruthy();
    await fireEvent.press(screen.getByText('Text from my phone'));

    await waitFor(() => expect(done).toHaveBeenCalled());
    expect(open).toHaveBeenCalledWith(reachUri('text', '+18325550123', insight.draft ?? ''));
    expect(mockApi.POST).toHaveBeenCalledWith('/contacts/{contact}/touches', { params: { path: { contact: maria.id } }, body: { channel: 'text', body: insight.draft } });
  });

  it('asks for the AI summary on the contact screen', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: maria.id });
    mockApi.GET.mockResolvedValue(ok({ data: maria, notes: [], tasks: [], activity: [], insight: null, segment: 'active', last_touch_at: null }));
    mockApi.POST.mockResolvedValue(ok({ data: insight }));
    await render(wrap(<ContactScreen />));

    await screen.findByText('Maria Lopez');
    const buttons = screen.getAllByText('AI summary');
    await fireEvent.press(buttons[buttons.length - 1]);

    expect(await screen.findByText('Text Maria two Katy listings')).toBeTruthy();
    expect(screen.getByText(/No touch in 12 days/)).toBeTruthy();
  });

  it('builds tel: and sms: links', () => {
    expect(reachUri('call', '+18325550123')).toBe('tel:+18325550123');
    expect(reachUri('text', '+18325550123', 'Hi & bye')).toBe('sms:+18325550123?&body=Hi%20%26%20bye');
  });
});

describe('using two CRMs and adding a contact (B8.3b)', () => {
  const crms = [
    { connection: 1, integration: 'follow-up-boss', name: 'Follow Up Boss', account: 'james@lonestar.example', takes_new_people: true, is_default: true },
    { connection: 2, integration: 'lofty', name: 'Lofty', account: null, takes_new_people: true, is_default: false },
    { connection: 3, integration: 'boldtrail', name: 'BoldTrail (kvCORE)', account: null, takes_new_people: false, is_default: false },
  ];

  it('picks where new contacts go, with Undo', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: crms }));
    mockApi.PUT.mockResolvedValue(ok({ data: crms.map((c) => ({ ...c, is_default: c.connection === 2 })) }));
    await render(wrap(<CrmsScreen />));

    expect(await screen.findByText('BoldTrail (kvCORE): Can\'t take new contacts from REOS')).toBeTruthy();
    await fireEvent.press(screen.getByText('Lofty'));
    await waitFor(() => expect(mockApi.PUT).toHaveBeenCalledWith('/contacts/crms/default', { body: { connection: 2 } }));
    expect(await screen.findByText('Saved. New contacts go there from now on.')).toBeTruthy();
  });

  it('adds a contact from the list and opens it', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: [], counts: { all: 0 }, tags: [], total: 0 }));
    mockApi.POST.mockResolvedValue({ data: { data: { id: 'abc' }, sent_to: 'Follow Up Boss' }, error: undefined, response: { ok: true, status: 201 } });
    await render(wrap(<ContactsScreen />));

    await fireEvent.press(screen.getAllByText('Add contact')[0]);
    await fireEvent.changeText(screen.getByLabelText('First name'), 'Nora');
    await fireEvent.changeText(screen.getByLabelText('Email'), 'nora@example.com');
    await fireEvent.press(screen.getByText('Save contact'));

    await waitFor(() => expect(router.push).toHaveBeenCalledWith({ pathname: '/contacts/[id]', params: { id: 'abc' } }));
    expect(mockApi.POST).toHaveBeenCalledWith('/contacts', { body: { first_name: 'Nora', last_name: null, email: 'nora@example.com', phone: null } });
  });
});
