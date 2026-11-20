/**
 * Minimal REOS API helper for Sprint 1.
 *
 * Sprint 1.3 replaces hand-written types with the client generated from the
 * platform's OpenAPI document (packages/api-client).
 */

export type Health = {
  status: 'ok';
  app: string;
  version: string;
  time: string;
};

export const apiBaseUrl = (): string =>
  (process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/+$/, '');

export async function fetchHealth(fetcher: typeof fetch = fetch): Promise<Health> {
  const response = await fetcher(`${apiBaseUrl()}/api/v1/health`, {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`Health check failed with status ${response.status}`);
  }

  const body = (await response.json()) as Partial<Health>;

  if (body.status !== 'ok' || typeof body.app !== 'string') {
    throw new Error('Unexpected health response');
  }

  return body as Health;
}
