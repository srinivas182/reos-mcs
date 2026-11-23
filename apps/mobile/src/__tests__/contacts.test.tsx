import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import * as Contacts from 'expo-contacts';
import { I18nextProvider } from 'react-i18next';

import SyncActivity from '@/app/connections/activity';
import ContactScreen from '@/app/contacts/[id]';
import PhoneContacts from '@/app/connections/phone';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), DELETE: jest.fn() };

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: 'c-1' }),
  useFocusEffect: (run: () => void) => {
    const { useEffect } = jest.requireActual('react');
    useEffect(run, [run]);
  },
}));
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ status: 'signedIn' }) }));

const renderScreen = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  jest.clearAllMocks();
  mockApi.GET.mockImplementation(async (path: string) =>
    path === '/contacts'
      ? { data: { data: [] }, response: { ok: true } }
      : path === '/contacts/{contact}'
        ? {
            data: {
              data: { id: 'c-1', name: 'Maria Lopez', first_name: 'Maria', last_name: 'Lopez', emails: ['maria.lopez@example.com'], phones: ['+18325550123'], stage: 'Active client', birthday: null, tags: [], updated_at: null,
                sources: [{ source: 'follow-up-boss', name: 'Follow Up Boss', synced_at: null }, { source: 'phone', name: 'Phone contacts', synced_at: null }] },
              notes: [{ id: 5, body: 'Wants 3 bedrooms in Katy ISD.', source: 'follow-up-boss', source_name: 'Follow Up Boss', sent_to: [], at: null }],
            },
            response: { ok: true },
          }
        : {
          data: {
            data: [
              {
                id: 1,
                kind: 'changed',
                contact: { id: 'c-1', name: 'Maria Lopez' },
                source: 'follow-up-boss',
                source_name: 'Follow Up Boss',
                text: 'Follow Up Boss changed the stage of Maria Lopez to “Active”. The earlier value from Phone contacts was “Lead”.',
                occurred_at: new Date().toISOString(),
              },
            ],
            suggestions: [{ id: 9, contact: { id: 'c-1', name: 'Maria Lopez' }, other: { id: 'c-2', name: 'Maria Lopez' } }],
          },
          response: { ok: true },
        },
  );
  mockApi.POST.mockResolvedValue({ response: { ok: true } });
});

describe('phone contacts (B7.1)', () => {
  it('reads the phone only when asked, and sends names, emails, phones and birthdays', async () => {
    (Contacts.Contact.getAllDetails as jest.Mock).mockResolvedValue([
      { id: 'p-1', givenName: 'Maria', familyName: 'Lopez', emails: [{ address: 'maria@example.com' }], phones: [{ number: '(832) 555-0123' }], birthday: { year: 1988, month: 4, day: 9 } },
    ]);
    await renderScreen(<PhoneContacts />);

    expect(Contacts.Contact.getAllDetails).not.toHaveBeenCalled();
    expect(screen.getByText(/never changes or deletes anything on your phone/)).toBeOnTheScreen();
    fireEvent.press(screen.getByRole('button', { name: 'Add phone contacts' }));

    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/contacts/phone', {
        body: { contacts: [{ id: 'p-1', first_name: 'Maria', last_name: 'Lopez', emails: ['maria@example.com'], phones: ['(832) 555-0123'], birthday: '1988-04-09' }] },
      }),
    );
    expect(await screen.findByText('1 contact added from your phone.')).toBeOnTheScreen();
  });

  it('explains when Contacts permission is off', async () => {
    (Contacts.getPermissionsAsync as jest.Mock).mockResolvedValueOnce({ granted: false, canAskAgain: false });
    await renderScreen(<PhoneContacts />);

    fireEvent.press(screen.getByRole('button', { name: 'Add phone contacts' }));

    expect(await screen.findByText(/Allow Contacts for REOS/)).toBeOnTheScreen();
    expect(mockApi.POST).not.toHaveBeenCalled();
  });
});

describe('Sync activity (screen 23)', () => {
  it('shows changes in plain words, and merges same-name contacts with Undo', async () => {
    await renderScreen(<SyncActivity />);

    expect(await screen.findByText(/The earlier value from Phone contacts was “Lead”/)).toBeOnTheScreen();
    fireEvent.press(screen.getByRole('button', { name: 'Merge' }));

    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/contacts/{contact}/merge', { params: { path: { contact: 'c-1' } }, body: { other: 'c-2' } }),
    );
    fireEvent.press(await screen.findByRole('button', { name: 'Undo' }));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/contacts/{contact}/split', { params: { path: { contact: 'c-2' } } }));
  });

  it('dismisses "Not the same"', async () => {
    await renderScreen(<SyncActivity />);

    fireEvent.press(await screen.findByRole('button', { name: 'Not the same' }));

    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/contacts/suggestions/{suggestion}/dismiss', { params: { path: { suggestion: 9 } } }),
    );
  });
});

describe('one person, all sources (screen 14, B7.2)', () => {
  it('shows every source and the CRM notes, and sends a new note to the CRMs', async () => {
    await renderScreen(<ContactScreen />);

    expect(await screen.findByText('Wants 3 bedrooms in Katy ISD.')).toBeOnTheScreen();
    expect(screen.getByText('Follow Up Boss')).toBeOnTheScreen();
    expect(screen.getByText('Phone contacts')).toBeOnTheScreen();
    await fireEvent.changeText(screen.getByLabelText('Add a note'), 'Toured 2218 Elm St; loved the yard.');
    await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));

    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/contacts/{contact}/notes', { params: { path: { contact: 'c-1' } }, body: { body: 'Toured 2218 Elm St; loved the yard.' } }),
    );
    expect(await screen.findByText(/Note saved/)).toBeOnTheScreen();
  });
});

describe('connecting with an API key (B7.2)', () => {
  it('sends the key and is connected at once, with no sign-in page', async () => {
    const { connectTool } = jest.requireActual('@/lib/connections');
    mockApi.POST.mockResolvedValueOnce({ data: { authorization_url: null, connection_id: 12 }, response: { ok: true } });

    await expect(connectTool('follow-up-boss', 'fka_123456')).resolves.toEqual({ outcome: 'connected', reason: null, connectionId: 12 });
    expect(mockApi.POST).toHaveBeenCalledWith('/connections', { body: { integration: 'follow-up-boss', api_key: 'fka_123456' } });
  });
});
