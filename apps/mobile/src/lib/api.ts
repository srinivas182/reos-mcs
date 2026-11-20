/**
 * The mobile app's single REOS API client.
 *
 * Every request and response type comes from @reos/api-client, which is
 * generated from the platform's OpenAPI document; never hand-write them.
 */
import { createApiClient, type ApiClient, type Schemas } from '@reos/api-client';

export type Health = Schemas['HealthResource'];

export const apiBaseUrl = (): string =>
  `${(process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/+$/, '')}/api/v1`;

let client: ApiClient | undefined;

/** Shared client; tests pass their own `fetch` through `createApiClient`. */
export const api = (): ApiClient => (client ??= createApiClient({ baseUrl: apiBaseUrl() }));

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
