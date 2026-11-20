import { apiBaseUrl, fetchHealth } from '@/lib/api';

const respond = (status: number, body: unknown) =>
  jest.fn().mockResolvedValue({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(body),
  }) as unknown as typeof fetch;

describe('fetchHealth', () => {
  it('returns the platform health when the API is up', async () => {
    const fetcher = respond(200, { status: 'ok', app: 'REOS', version: '0.1.0-dev', time: 'now' });

    await expect(fetchHealth(fetcher)).resolves.toMatchObject({ status: 'ok', app: 'REOS' });
    expect(fetcher).toHaveBeenCalledWith(`${apiBaseUrl()}/api/v1/health`, expect.any(Object));
  });

  it('throws on a server error', async () => {
    await expect(fetchHealth(respond(503, {}))).rejects.toThrow('status 503');
  });

  it('throws on an unexpected body', async () => {
    await expect(fetchHealth(respond(200, { status: 'down' }))).rejects.toThrow('Unexpected');
  });
});
