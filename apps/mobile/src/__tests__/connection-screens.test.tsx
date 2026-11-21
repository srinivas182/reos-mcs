import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import * as WebBrowser from 'expo-web-browser';
import { I18nextProvider } from 'react-i18next';

import ConnectionDetail from '@/app/connections/[id]/index';
import SyncSettings from '@/app/connections/[id]/settings';
import AddConnection from '@/app/connections/add';
import ConnectedScreen from '@/app/connections/connected/[id]';
import ConsentScreen from '@/app/connections/consent/[tool]';
import ConnectionsHub from '@/app/connections/index';
import type { CatalogueItem, Connection } from '@/lib/connections';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const item = (overrides: Partial<CatalogueItem>): CatalogueItem =>
  ({ id: 'x', name: 'X', purpose: 'Does things.', group: 'crm', status: 'enabled', action: 'connect', interested: false, retires_on: null, connected: 0, accounts: 'user', consent: null, ...overrides }) as CatalogueItem;

const catalogue: CatalogueItem[] = [
  item({ id: 'google-workspace', name: 'Google Calendar and Gmail', purpose: 'Two-way calendar and email.', group: 'calendar', consent: { can: ['read_events', 'change_alerts'], never: ['change_events'] } }),
  item({ id: 'follow-up-boss', name: 'Follow Up Boss', purpose: 'Contacts, notes and tasks.', status: 'coming_soon', action: 'notify' }),
  item({ id: 'lofty', name: 'Lofty', purpose: 'Contacts, notes and tasks.', status: 'coming_soon', action: 'notify', interested: true }),
  item({ id: 'skyslope', name: 'SkySlope', purpose: 'Files and deadlines.', group: 'deals', status: 'paused', action: 'none', accounts: 'tenant' }),
  item({ id: 'lone-wolf', name: 'Lone Wolf Transactions', purpose: 'Deals and commissions.', group: 'deals', status: 'coming_soon', action: 'ask_broker', accounts: 'tenant' }),
];

const calendar: Connection = {
  id: 7,
  integration: 'google-workspace',
  integration_name: 'Google Calendar and Gmail',
  owner: 'user',
  account: 'linda.park@demo.reos.test',
  status: 'active',
  demo: true,
  scopes: [],
  history: '3m',
  last_synced_at: new Date(Date.now() - 2 * 60_000).toISOString(),
  problem: null,
  problem_since: null,
  connected_at: '2026-11-01T10:00:00Z',
  streams: [{ key: 'events', direction: 'read', records: 12 }],
} as Connection;

const mockApi = { GET: jest.fn(), POST: jest.fn(), PATCH: jest.fn(), DELETE: jest.fn() };
const mockData = { catalogue, connections: [calendar] as Connection[], reloadCatalogue: jest.fn(), reloadConnections: jest.fn() };
const mockParams: Record<string, string> = {};

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
  useLocalSearchParams: () => mockParams,
  useFocusEffect: jest.fn(),
}));
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ status: 'signedIn' }) }));
jest.mock('@/lib/connections', () => {
  const actual = jest.requireActual('@/lib/connections');

  return {
    ...actual,
    useCatalogue: () => ({ items: mockData.catalogue, reload: mockData.reloadCatalogue }),
    useConnections: () => ({ connections: mockData.connections, reload: mockData.reloadConnections }),
  };
});

const renderScreen = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

const router = () => (jest.requireMock('expo-router') as { router: { push: jest.Mock; back: jest.Mock; replace: jest.Mock } }).router;

beforeEach(() => {
  jest.clearAllMocks();
  mockData.connections = [calendar];
  for (const key of Object.keys(mockParams)) delete mockParams[key];
  mockApi.POST.mockResolvedValue({ response: { ok: true } });
  mockApi.DELETE.mockResolvedValue({ response: { ok: true } });
  mockApi.PATCH.mockResolvedValue({ response: { ok: true } });
});
afterEach(() => i18n.changeLanguage('en'));

describe('Connections: one place (screen 12)', () => {
  it('groups connected tools with status and last sync', async () => {
    await renderScreen(<ConnectionsHub />);

    expect(screen.getByText('CALENDAR · 1')).toBeOnTheScreen();
    expect(screen.getByText('Up to date')).toBeOnTheScreen();
    expect(screen.getByText('Synced 2 minutes ago')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Google Calendar and Gmail, linda.park@demo.reos.test' }));
    expect(router().push).toHaveBeenCalledWith({ pathname: '/connections/[id]', params: { id: '7' } });
  });

  it('invites a first connection when there is none', async () => {
    mockData.connections = [];
    await renderScreen(<ConnectionsHub />);

    expect(screen.getByText('No tools connected yet')).toBeOnTheScreen();
    await fireEvent.press(screen.getAllByRole('button', { name: 'Add a connection' })[1]!);
    expect(router().push).toHaveBeenCalledWith('/connections/add');
  });
});

describe('Add a connection (screens 2–4)', () => {
  it('shows each tab’s tools with what you can do', async () => {
    await renderScreen(<AddConnection />);

    expect(screen.getByText('Follow Up Boss')).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Notify me · Follow Up Boss' })).toBeOnTheScreen();
    expect(screen.getByText('We\'ll tell you')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('radio', { name: 'Calendar' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Connect Google Calendar and Gmail' }));
    expect(router().push).toHaveBeenCalledWith({ pathname: '/connections/consent/[tool]', params: { tool: 'google-workspace' } });

    await fireEvent.press(screen.getByRole('radio', { name: 'Deals' }));
    expect(screen.getByText('Temporarily unavailable')).toBeOnTheScreen();
    expect(screen.getByText('Files and deadlines. · Brokerage')).toBeOnTheScreen();
  });

  it('counts Notify me, with Undo', async () => {
    await renderScreen(<AddConnection />);

    await fireEvent.press(screen.getByRole('button', { name: 'Notify me · Follow Up Boss' }));

    await waitFor(() => expect(screen.getByText("Noted. We'll tell you when Follow Up Boss arrives.")).toBeOnTheScreen());
    expect(mockApi.POST).toHaveBeenCalledWith('/catalogue/{tool}/interest', { params: { path: { tool: 'follow-up-boss' } } });
  });

  it('asks the broker for a tool the brokerage sets up, with Undo', async () => {
    await renderScreen(<AddConnection />);

    await fireEvent.press(screen.getByRole('radio', { name: 'Deals' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Ask my broker · Lone Wolf Transactions' }));

    await waitFor(() => expect(screen.getByText('Asked your broker to set up Lone Wolf Transactions.')).toBeOnTheScreen());
    expect(mockApi.POST).toHaveBeenCalledWith('/catalogue/{tool}/interest', { params: { path: { tool: 'lone-wolf' } } });
  });

  it('searches every tab at once', async () => {
    await renderScreen(<AddConnection />);

    await fireEvent.changeText(screen.getByLabelText('Search 5 tools'), 'sky');
    expect(screen.getByText('SkySlope')).toBeOnTheScreen();
    expect(screen.queryByText('Follow Up Boss')).toBeNull();
  });

  it('asks for a tool that isn’t listed', async () => {
    await renderScreen(<AddConnection />);

    await fireEvent.press(screen.getByRole('button', { name: "My tool isn't listed" }));
    await fireEvent.changeText(screen.getByLabelText('Tool name'), 'Sierra Interactive');
    await fireEvent.press(screen.getByRole('button', { name: 'Send' }));

    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/tool-requests', { body: { name: 'Sierra Interactive', note: null } }));
  });
});

describe('Consent, then connected with smart defaults (screens 5–7)', () => {
  it('says what REOS can and never does, then signs in and shows the defaults', async () => {
    mockParams.tool = 'google-workspace';
    mockApi.POST.mockResolvedValue({ data: { authorization_url: 'https://accounts.example/auth' }, response: { ok: true } });
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({ type: 'success', url: 'reos://connections?outcome=connected&connection=7' });
    await renderScreen(<ConsentScreen />);

    expect(screen.getByText('Connect Google Calendar and Gmail')).toBeOnTheScreen();
    expect(screen.getByText('See the events on your calendar')).toBeOnTheScreen();
    expect(screen.getByText('Never change or delete your events')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Continue to Google Calendar and Gmail' }));

    await waitFor(() => expect(router().replace).toHaveBeenCalledWith({ pathname: '/connections/connected/[id]', params: { id: '7' } }));
  });

  it('shows what syncs, which way and how far back', async () => {
    mockParams.id = '7';
    await renderScreen(<ConnectedScreen />);

    expect(screen.getByText('Google Calendar and Gmail is connected')).toBeOnTheScreen();
    expect(screen.getByText('Calendar events')).toBeOnTheScreen();
    expect(screen.getByText('REOS reads')).toBeOnTheScreen();
    expect(screen.getByText('Last 3 months')).toBeOnTheScreen();
  });

  it('changes how far back it syncs', async () => {
    mockParams.id = '7';
    await renderScreen(<SyncSettings />);

    await fireEvent.press(screen.getByRole('radio', { name: 'Last 12 months' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(mockApi.PATCH).toHaveBeenCalledWith('/connections/{connection}', { params: { path: { connection: 7 } }, body: { history: '12m' } }));
    expect(router().back).toHaveBeenCalled();
  });
});

describe('A connected tool', () => {
  it('syncs now, and disconnects after confirming', async () => {
    mockParams.id = '7';
    await renderScreen(<ConnectionDetail />);

    expect(screen.getByText('12 items synced')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Sync now' }));
    await waitFor(() => expect(screen.getByText('Syncing now.')).toBeOnTheScreen());

    await fireEvent.press(screen.getByRole('button', { name: 'Disconnect' }));
    expect(screen.getByText('REOS stops syncing and removes the Google Calendar and Gmail data it copied. Nothing is deleted in Google Calendar and Gmail itself.')).toBeOnTheScreen();
    await fireEvent.press(screen.getAllByRole('button', { name: 'Disconnect' }).at(-1)!);

    await waitFor(() => expect(mockApi.DELETE).toHaveBeenCalledWith('/connections/{connection}', { params: { path: { connection: 7 } } }));
    expect(router().back).toHaveBeenCalled();
  });

  it('speaks Spanish', async () => {
    await i18n.changeLanguage('es');
    mockParams.id = '7';
    await renderScreen(<ConnectionDetail />);

    expect(screen.getByText('12 elementos sincronizados')).toBeOnTheScreen();
    expect(screen.getByText('Al día')).toBeOnTheScreen();
  });
});
