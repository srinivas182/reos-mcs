import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import AddLicence from '@/app/licences/add';
import MyLicences from '@/app/licences/index';
import MeScreen from '@/app/me';
import type { Me } from '@/lib/auth';
import { i18n } from '@/lib/i18n';
import type { Licence } from '@/lib/licences';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PATCH: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };
const lonestar = { id: 2, name: 'Lone Star Realty', type: 'brokerage' as const };
const james = (options = [{ code: 'TX', name: 'Texas' }]): Me =>
  ({
    id: 7,
    name: 'James Miller',
    email: 'james.miller@demo.reos.test',
    phone: null,
    roles: [{ role: 'agent', organisation: lonestar, admin: false }],
    active_role: { role: 'agent', organisation_id: 2 },
    permissions: [],
    two_step: { enabled: false, required: false },
    states: { current: { code: 'TX', name: 'Texas' }, options },
  }) as unknown as Me;
const mockAuth = { status: 'signedIn', user: james(), refresh: jest.fn().mockResolvedValue(undefined), switchRole: jest.fn(), signOut: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return { router: { push: jest.fn(), back: jest.fn() }, useFocusEffect: (effect: () => void) => useEffect(effect, [effect]) };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => mockAuth }));

const ok = (data: unknown, status = 200) => ({ data, error: undefined, response: { ok: true, status } });

const texas: Licence = {
  id: 1,
  state: { code: 'TX', name: 'Texas', live: true },
  type: 'sales_agent',
  number: '0798765',
  status: 'active',
  broker_role: null,
  holder_name: 'James Miller',
  licensed_on: '2024-04-01',
  expires_on: '2027-03-31',
  sponsor_name: 'Lone Star Realty',
  first_renewal: false,
  verified_by: 'simulator',
  verified_at: '2026-11-21T16:00:00+00:00',
  review_note: null,
  tracked_only: false,
  ce: {
    state: 'TX',
    due_on: '2027-03-31',
    period_start: '2025-03-31',
    days_left: 129,
    first_renewal: false,
    hours_required: 18,
    hours_done: 9,
    hours_left: 9,
    complete: false,
    parts: [
      { code: 'legal_update_1', title: 'Legal Update I: Laws, Rules and Forms', kind: 'mandatory', hours: 4, hours_done: 4, done: true },
      { code: 'legal_update_2', title: 'Legal Update II: Agency, Ethics and Hot Topics', kind: 'mandatory', hours: 4, hours_done: 0, done: false },
      { code: 'contracts', title: 'Real estate contracts', kind: 'mandatory', hours: 3, hours_done: 3, done: true },
      { code: 'electives', title: 'Electives', kind: 'elective', hours: 7, hours_done: 2, done: false },
    ],
    deferral: { fee_usd: 200, days: 60 },
    sources: [{ key: 'ce.hours', citation: '22 TAC §535.92(a)', url: null, effective_from: '2023-08-29' }],
  },
};
const louisiana: Licence = {
  ...texas,
  id: 2,
  state: { code: 'LA', name: 'Louisiana', live: false },
  number: '0995-21837',
  status: 'pending_review',
  expires_on: null,
  sponsor_name: null,
  verified_by: null,
  verified_at: null,
  review_note: "This state's records can't be checked automatically yet.",
  tracked_only: true,
  ce: null,
};

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  jest.clearAllMocks();
  mockAuth.user = james();
  mockApi.GET.mockImplementation(async (path: string) =>
    path === '/states'
      ? ok({ data: [
          { code: 'LA', name: 'Louisiana', status: 'in_preparation', live: false, waiting: 12 },
          { code: 'TX', name: 'Texas', status: 'live', live: true, waiting: 0 },
        ] })
      : ok({ data: [texas, louisiana] }),
  );
});
afterEach(() => void i18n.changeLanguage('en'));

describe('My licences (agent journey screen 148)', () => {
  it('shows each licence: live with sponsor and renewal, coming soon explained', async () => {
    await wrap(<MyLicences />);

    expect(await screen.findByRole('button', { name: /Texas · Sales agent\. #0798765 · active · verified Nov 21/ })).toBeOnTheScreen();
    expect(screen.getByLabelText('Sponsoring broker: Lone Star Realty')).toBeOnTheScreen();
    expect(screen.getByLabelText('Renewal: Mar 31, 2027')).toBeOnTheScreen();
    expect(screen.getByLabelText('CE this cycle: 9 of 18 h')).toBeOnTheScreen();
    expect(screen.getByText('Coming soon')).toBeOnTheScreen();
    expect(screen.getByText(/REOS isn't live in Louisiana yet/)).toBeOnTheScreen();
    expect(screen.getByText("Licenses are checked with each state's regulator.")).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Add a license' }));
    expect(router.push).toHaveBeenCalledWith('/licences/add');
  });

  it('sets who you supervise and removes a licence with Undo', async () => {
    mockApi.PATCH.mockResolvedValue(ok({ data: { ...texas, broker_role: 'delegated_supervisor' } }));
    mockApi.DELETE.mockResolvedValue(ok(undefined, 204));
    mockApi.POST.mockResolvedValue(ok({ data: texas }));
    await wrap(<MyLicences />);

    await fireEvent.press(await screen.findByRole('button', { name: /Texas · Sales agent/ }));
    // What you still need, from the Texas rules (A5.2).
    expect(screen.getByText('9 hours left, due Mar 31, 2027')).toBeOnTheScreen();
    expect(screen.getByLabelText('Legal Update II: Agency, Ethics and Hot Topics: 0 of 4 h')).toBeOnTheScreen();
    expect(screen.getByLabelText('Legal Update I: Laws, Rules and Forms: 4 of 4 h, done')).toBeOnTheScreen();
    expect(screen.getByText(/\$200 deferral fee and get 60 more days/)).toBeOnTheScreen();
    expect(screen.getByText('From 22 TAC §535.92(a).')).toBeOnTheScreen();
    // A sales agent licence can't sponsor agents.
    expect(screen.queryByRole('radio', { name: 'I sponsor sales agents' })).toBeNull();
    await fireEvent.press(screen.getByRole('radio', { name: 'Delegated supervisor' }));
    expect(mockApi.PATCH).toHaveBeenCalledWith('/me/licences/{licence}', { params: { path: { licence: 1 } }, body: { broker_role: 'delegated_supervisor' } });

    await fireEvent.press(screen.getByRole('button', { name: 'Remove Texas license' }));
    await waitFor(() => expect(mockApi.DELETE).toHaveBeenCalledWith('/me/licences/{licence}', { params: { path: { licence: 1 } } }));
    expect(mockAuth.refresh).toHaveBeenCalled();

    await fireEvent.press(await screen.findByRole('button', { name: 'Undo' }));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/me/licences/{licence}/restore', { params: { path: { licence: 1 } } }));
  });

  it('starts with an empty state that adds a licence', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: [] }));
    await wrap(<MyLicences />);

    expect(await screen.findByText('Add your first license')).toBeOnTheScreen();
  });
});

describe('Add a licence (agent journey screen 149)', () => {
  it('checks the number, shows what the regulator holds and where from, then adds it', async () => {
    mockApi.POST.mockImplementation(async (path: string) =>
      path === '/me/licences/check'
        ? ok({ check: 'verified', record: { name: 'James Miller', type: 'sales_agent', status: 'active', expires_on: '2027-03-31' }, source: "Checked with the Texas Real Estate Commission's public license data." })
        : ok({ data: texas, check: 'verified', source: null }),
    );
    await wrap(<AddLicence />);

    expect(await screen.findByRole('button', { name: 'State: Texas' })).toBeOnTheScreen();
    await fireEvent.changeText(screen.getByLabelText('License number'), '0798765');
    await fireEvent.press(screen.getByRole('button', { name: 'Check number' }));

    expect(await screen.findByText('Found: James Miller')).toBeOnTheScreen();
    expect(screen.getByText('Sales agent · active · expires Mar 31, 2027')).toBeOnTheScreen();
    expect(screen.getByText("Checked with the Texas Real Estate Commission's public license data.")).toBeOnTheScreen();
    expect(mockApi.POST).toHaveBeenCalledWith('/me/licences/check', { body: { state: 'TX', number: '0798765' } });

    await fireEvent.press(screen.getByRole('button', { name: 'Add license' }));
    await waitFor(() => expect(router.back).toHaveBeenCalled());
    expect(mockApi.POST).toHaveBeenCalledWith('/me/licences', { body: { state: 'TX', number: '0798765', review: false } });
    expect(mockAuth.refresh).toHaveBeenCalled();
  });

  it('sends a state with no check to a reviewer', async () => {
    mockApi.POST.mockImplementation(async (path: string) =>
      path === '/me/licences/check' ? ok({ check: 'no_lookup', record: null, source: null }) : ok({ data: louisiana, check: 'no_lookup', source: null }),
    );
    await wrap(<AddLicence />);

    await fireEvent.press(await screen.findByRole('button', { name: 'State: Texas' }));
    await fireEvent.press(screen.getByRole('radio', { name: 'Louisiana. Coming soon' }));
    await fireEvent.changeText(screen.getByLabelText('License number'), '0995-21837');
    await fireEvent.press(screen.getByRole('button', { name: 'Check number' }));

    expect(await screen.findByText(/Louisiana's records can't be checked automatically yet/)).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Send for review' }));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/me/licences', { body: { state: 'LA', number: '0995-21837', review: true } }));
  });

  it('says what is wrong with the number', async () => {
    mockApi.POST.mockResolvedValue({ data: undefined, error: { message: 'Invalid', errors: { number: ['The number field format is invalid.'] } }, response: { ok: false, status: 422 } });
    await wrap(<AddLicence />);

    await fireEvent.changeText(await screen.findByLabelText('License number'), '<x>');
    await fireEvent.press(screen.getByRole('button', { name: 'Check number' }));

    expect(await screen.findByText('The number field format is invalid.')).toBeOnTheScreen();
  });
});

describe('Me: licences and the state switcher', () => {
  it('opens My licences, and switches state with Undo when there is more than one', async () => {
    mockAuth.user = james([
      { code: 'FL', name: 'Florida' },
      { code: 'TX', name: 'Texas' },
    ]);
    mockApi.PUT.mockResolvedValue(ok({}));
    await wrap(<MeScreen />);

    await fireEvent.press(screen.getByRole('button', { name: 'My licenses' }));
    expect(router.push).toHaveBeenCalledWith('/licences');

    await fireEvent.press(screen.getByRole('button', { name: 'Working in, Texas' }));
    await fireEvent.press(screen.getByRole('radio', { name: 'Florida' }));
    await waitFor(() => expect(mockApi.PUT).toHaveBeenCalledWith('/me/states/current', { body: { state: 'FL' } }));
    expect(await screen.findByText('Now working in Florida.')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Undo' }));
    await waitFor(() => expect(mockApi.PUT).toHaveBeenLastCalledWith('/me/states/current', { body: { state: 'TX' } }));
  });

  it('hides the switcher with one state', async () => {
    await wrap(<MeScreen />);

    expect(screen.queryByRole('button', { name: /Working in/ })).toBeNull();
  });
});
