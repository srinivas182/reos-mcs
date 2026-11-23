import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import * as Contacts from 'expo-contacts';
import { I18nextProvider } from 'react-i18next';

import SyncActivity from '@/app/connections/activity';
import PhoneContacts from '@/app/connections/phone';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), DELETE: jest.fn() };

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
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
