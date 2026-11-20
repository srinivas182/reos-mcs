/**
 * Phone-app error tracking (Sentry) and product analytics (PostHog),
 * switched on and off by the super admin in Settings › Integrations
 * (Sprint 2.4c). The app asks the server at start-up which are on, so a
 * change needs no app release; the response carries public values only.
 *
 * Privacy defaults match the web: no personal data in error reports, no
 * session replay, people identified only by their REOS id.
 */
import type { ApiClient, Schemas } from '@reos/api-client';
import * as Sentry from '@sentry/react-native';
import { PostHog } from 'posthog-react-native';

import { api } from '@/lib/api';

export type AppConfig = Schemas['AppConfigResource'];

type Analytics = {
  screen: (name: string) => unknown;
  identify: (id: string) => unknown;
  reset: () => unknown;
};

let analytics: Analytics | null = null;
let started = false;

/** Reads which services are switched on. Offline or failing: everything stays off. */
export async function fetchAppConfig(client: ApiClient = api()): Promise<AppConfig | null> {
  try {
    const { data, response } = await client.GET('/app-config');

    return response.ok && data !== undefined ? data : null;
  } catch {
    return null;
  }
}

/** Starts whatever is switched on. Runs once per app launch. */
export function startMonitoring(config: AppConfig | null): void {
  if (started || config === null) {
    return;
  }

  started = true;

  if (config.sentry) {
    Sentry.init({
      dsn: config.sentry.dsn,
      environment: config.environment,
      release: config.release,
      sendDefaultPii: false,
      tracesSampleRate: 0,
    });
  }

  if (config.posthog) {
    analytics = new PostHog(config.posthog.key, {
      host: config.posthog.host,
      personProfiles: 'identified_only',
      captureAppLifecycleEvents: true,
      enableSessionReplay: false,
    });
  }
}

/** One screen view per route (the path pattern, never typed text). */
export function trackScreen(pathname: string): void {
  analytics?.screen(pathname);
}

/** Links analytics to the signed-in person by REOS id only, or forgets them. */
export function identify(userId: number | null): void {
  if (userId === null) {
    analytics?.reset();
  } else {
    analytics?.identify(String(userId));
  }
}

/** For tests. */
export function resetMonitoring(): void {
  analytics = null;
  started = false;
}
