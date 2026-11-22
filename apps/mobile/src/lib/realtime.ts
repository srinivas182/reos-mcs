/**
 * Live updates on the phone (B6.1): Laravel Echo over Reverb with the
 * device's token. Connected while the app is open; when it's in the
 * background, push takes over (B6.2). Without Reverb (app config says
 * `realtime: null`) screens refresh when they come into view instead.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js/react-native';

import { apiBaseUrl } from '@/lib/api';
import { appConfig } from '@/lib/monitoring';
import { currentToken } from '@/lib/session';

type Listener = (payload: unknown) => void;

let echo: Echo<'reverb'> | null | undefined;

async function connection(): Promise<Echo<'reverb'> | null> {
  if (echo !== undefined) return echo;

  const realtime = (await appConfig())?.realtime ?? null;

  if (realtime === null) {
    echo = null;

    return echo;
  }

  echo = new Echo({
    broadcaster: 'reverb',
    Pusher,
    key: realtime.key,
    wsHost: realtime.host,
    wsPort: realtime.port,
    wssPort: realtime.port,
    forceTLS: realtime.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiBaseUrl()}/broadcasting/auth`,
    auth: { headers: { Authorization: `Bearer ${currentToken() ?? ''}`, Accept: 'application/json' } },
  });

  return echo;
}

/**
 * Listens on a private channel for events (names as the server sends them,
 * such as `message.posted`). Returns a function that stops listening.
 */
export function listen(channel: string, events: string[], onEvent: Listener): () => void {
  let stopped = false;
  let leave = (): void => undefined;

  void connection().then((live) => {
    if (live === null || stopped) return;

    const subscription = live.private(channel);
    events.forEach((event) => subscription.listen(`.${event}`, onEvent));
    leave = () => events.forEach((event) => subscription.stopListening(`.${event}`));
  });

  return () => {
    stopped = true;
    leave();
  };
}

/** Signing out: drop the connection made with the old token. */
export function disconnectRealtime(): void {
  echo?.disconnect();
  echo = undefined;
}
