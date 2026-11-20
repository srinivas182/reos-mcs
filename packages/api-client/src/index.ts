/**
 * Typed REOS API client.
 *
 * `schema.d.ts` is generated from `openapi.json`, which the platform exports
 * with Scramble. Never edit either by hand; run `pnpm api:generate` at the repo
 * root after changing an API endpoint. CI fails if they're out of date.
 *
 * @example
 *   const api = createApiClient({ baseUrl: 'https://api.example.com/api/v1' });
 *   const { data, error } = await api.GET('/health');
 */
import createClient, { type ClientOptions } from 'openapi-fetch';

import type { components, paths } from './schema';

export type { components, paths } from './schema';

/** Every response schema in the API, by name (for example `Schemas['HealthResource']`). */
export type Schemas = components['schemas'];

export type ApiClient = ReturnType<typeof createClient<paths>>;

export type ApiClientOptions = Omit<ClientOptions, 'baseUrl'> & {
  /** Base URL including the version prefix, for example `https://reos.example/api/v1`. */
  baseUrl: string;
};

export function createApiClient({ baseUrl, headers, ...options }: ApiClientOptions): ApiClient {
  return createClient<paths>({
    baseUrl: baseUrl.replace(/\/+$/, ''),
    headers: { Accept: 'application/json', ...headers },
    ...options,
  });
}
