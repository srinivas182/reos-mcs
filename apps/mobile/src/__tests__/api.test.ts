import { createApiClient } from '@reos/api-client';

import { apiBaseUrl, fetchHealth, withSession } from '@/lib/api';
import { i18n } from '@/lib/i18n';

const clientReturning = (status: number, body: unknown) => {
  const fetch = jest.fn(async (_request: Request) =>
    new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }),
  );

  return { fetch, client: createApiClient({ baseUrl: apiBaseUrl(), fetch }) };
};

describe('fetchHealth', () => {
  it('calls the versioned health endpoint and returns the typed body', async () => {
    const { fetch, client } = clientReturning(200, {
      status: 'ok',
      app: 'REOS',
      version: '0.1.0-dev',
      time: '2026-12-07T09:00:00+00:00',
    });

    await expect(fetchHealth(client)).resolves.toMatchObject({ status: 'ok', app: 'REOS' });

    const request = fetch.mock.calls[0]?.[0];
    expect(request?.url).toBe('http://localhost:8000/api/v1/health');
    expect(request?.headers.get('Accept')).toBe('application/json');
  });

  it('throws on a server error', async () => {
    await expect(fetchHealth(clientReturning(503, { message: 'down' }).client)).rejects.toThrow('status 503');
  });

  it('throws on an unexpected body', async () => {
    await expect(fetchHealth(clientReturning(200, { status: 'down' }).client)).rejects.toThrow('Unexpected');
  });
});

describe('withSession', () => {
  afterEach(() => i18n.changeLanguage('en'));

  it('asks for answers in the language on screen', async () => {
    await i18n.changeLanguage('es');
    const { fetch, client } = clientReturning(200, { status: 'ok', app: 'REOS', version: '0.1.0', time: '2026-12-07T09:00:00+00:00' });

    await fetchHealth(withSession(client));

    expect(fetch.mock.calls[0]?.[0].headers.get('Accept-Language')).toBe('es');
  });
});
