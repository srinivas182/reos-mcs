/**
 * The mobile app's single REOS API client.
 *
 * Every request and response type comes from @reos/api-client, which is
 * generated from the platform's OpenAPI document; never hand-write them.
 */
import { createApiClient, type ApiClient, type Schemas } from '@reos/api-client';

import { currentToken, expireSession } from '@/lib/session';

export type Health = Schemas['HealthResource'];

export const apiBaseUrl = (): string =>
  `${(process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/+$/, '')}/api/v1`;

/** The web app's address (browser sign-in opens pages there). */
export const webBaseUrl = (): string => apiBaseUrl().replace(/\/api\/v1$/, '');

let client: ApiClient | undefined;

/**
 * Adds this device's token to every request, and signs the app out when the
 * server answers 401 to a signed-in request (signed out from another device,
 * or 30 days unused).
 */
export function withSession(apiClient: ApiClient): ApiClient {
  apiClient.use({
    onRequest({ request }) {
      const token = currentToken();

      if (token !== null) {
        request.headers.set('Authorization', `Bearer ${token}`);
      }

      return request;
    },
    onResponse({ request, response }) {
      if (response.status === 401 && request.headers.has('Authorization')) {
        expireSession();
      }

      return response;
    },
  });

  return apiClient;
}

/** Shared client; tests pass their own `fetch` through `createApiClient`. */
export const api = (): ApiClient => (client ??= withSession(createApiClient({ baseUrl: apiBaseUrl() })));

export async function fetchHealth(apiClient: ApiClient = api()): Promise<Health> {
  const { data, response } = await apiClient.GET('/health');

  if (!response.ok || data === undefined) {
    throw new Error(`Health check failed with status ${response.status}`);
  }

  if (data.status !== 'ok') {
    throw new Error('Unexpected health response');
  }

  return data;
}
