import type { Schemas } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

export type Connection = Schemas['ConnectionResource'];

/** The tools the server can connect (from the API contract). */
export type ConnectableTool = Schemas['StartConnectionRequest']['integration'];

export type ConnectOutcome = { outcome: 'connected' | 'cancelled' | 'failed'; reason: string | null };

/** Your connections to outside tools, reloaded whenever the screen comes back into view. */
export function useConnections() {
  const { status } = useAuth();
  const [connections, setConnections] = useState<Connection[] | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn') {
      return;
    }

    const { data } = await api().GET('/connections');
    setConnections(data?.data ?? []);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { connections, reload: load };
}

/**
 * Connect (or reconnect) a tool (B3.1): REOS hands back the tool's sign-in
 * page, which opens in a secure browser sheet. After signing in, the tool
 * returns to REOS, which sends the sheet back to `reos://connections`.
 */
export async function connectTool(integration: string): Promise<ConnectOutcome> {
  // The id comes from the server's own connection list.
  const { data, error } = await api().POST('/connections', { body: { integration: integration as ConnectableTool } });

  if (!data) {
    const message = error && typeof error === 'object' && 'message' in error && typeof error.message === 'string' ? error.message : null;

    return { outcome: 'failed', reason: message };
  }

  const result = await WebBrowser.openAuthSessionAsync(data.authorization_url, 'reos://connections');

  if (result.type !== 'success') {
    return { outcome: 'cancelled', reason: null };
  }

  const params = new URL(result.url).searchParams;
  const outcome = params.get('outcome');

  return {
    outcome: outcome === 'connected' || outcome === 'cancelled' ? outcome : 'failed',
    reason: params.get('reason'),
  };
}

/** Two letters for a tool's tile: "Google Calendar and Gmail" → "GC". */
export function toolInitials(name: string): string {
  return name
    .split(/\s+/)
    .filter((word) => /^[A-Za-z]/.test(word) && word.toLowerCase() !== 'and')
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase() ?? '')
    .join('');
}
