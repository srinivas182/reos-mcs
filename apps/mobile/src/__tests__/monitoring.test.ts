import { createApiClient } from '@reos/api-client';

import { apiBaseUrl } from '@/lib/api';
import { fetchAppConfig, identify, resetMonitoring, startMonitoring, trackScreen } from '@/lib/monitoring';

const mockSentryInit = jest.fn();
const mockPostHog = { screen: jest.fn(), identify: jest.fn(), reset: jest.fn() };
const mockPostHogConstructor = jest.fn(() => mockPostHog);

jest.mock('@sentry/react-native', () => ({ init: (...args: unknown[]) => mockSentryInit(...args) }));
jest.mock('posthog-react-native', () => ({
  PostHog: function PostHog(...args: unknown[]) {
    return (mockPostHogConstructor as jest.Mock)(...args);
  },
}));

const off = { environment: 'production', release: '1.0.0', sentry: null, posthog: null, sign_in: { google: true, apple: true, phone: true, demo: false } };

const clientReturning = (status: number, body: unknown) => {
  const fetch = jest.fn(async (_request: Request) =>
    new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }),
  );

  return { fetch, client: createApiClient({ baseUrl: apiBaseUrl(), fetch }) };
};

beforeEach(() => {
  resetMonitoring();
  jest.clearAllMocks();
});

describe('fetchAppConfig', () => {
  it('reads the switches from the versioned API', async () => {
    const { fetch, client } = clientReturning(200, off);

    await expect(fetchAppConfig(client)).resolves.toEqual(off);
    expect(fetch.mock.calls[0]?.[0].url).toBe('http://localhost:8000/api/v1/app-config');
  });

  it('keeps everything off when the server cannot be reached', async () => {
    const client = createApiClient({
      baseUrl: apiBaseUrl(),
      fetch: async () => {
        throw new TypeError('Network request failed');
      },
    });

    await expect(fetchAppConfig(client)).resolves.toBeNull();
    await expect(fetchAppConfig(clientReturning(500, {}).client)).resolves.toBeNull();
  });
});

describe('startMonitoring', () => {
  it('loads nothing when everything is off', async () => {
    startMonitoring(off);
    trackScreen('/today');

    expect(mockSentryInit).not.toHaveBeenCalled();
    expect(mockPostHogConstructor).not.toHaveBeenCalled();
  });

  it('starts Sentry without personal data', async () => {
    startMonitoring({ ...off, sentry: { dsn: 'https://key@o1.ingest.sentry.io/3' } });

    expect(mockSentryInit).toHaveBeenCalledWith(
      expect.objectContaining({ dsn: 'https://key@o1.ingest.sentry.io/3', sendDefaultPii: false, release: '1.0.0' }),
    );
  });

  it('starts PostHog privately, tracks screens and identifies by REOS id only', async () => {
    startMonitoring({ ...off, posthog: { key: 'phc_key', host: 'https://us.i.posthog.com' } });

    expect(mockPostHogConstructor).toHaveBeenCalledWith(
      'phc_key',
      expect.objectContaining({ host: 'https://us.i.posthog.com', enableSessionReplay: false, personProfiles: 'identified_only' }),
    );

    trackScreen('/practice');
    identify(42);
    identify(null);

    expect(mockPostHog.screen).toHaveBeenCalledWith('/practice');
    expect(mockPostHog.identify).toHaveBeenCalledWith('42');
    expect(mockPostHog.reset).toHaveBeenCalled();
  });

  it('starts once per launch', async () => {
    const config = { ...off, sentry: { dsn: 'https://k@s.io/1' } };

    startMonitoring(config);
    startMonitoring(config);

    expect(mockSentryInit).toHaveBeenCalledTimes(1);
  });
});
