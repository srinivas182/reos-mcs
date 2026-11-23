import type { Schemas } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

export type Connection = Schemas['ConnectionResource'];

/** The tools the server can connect (from the API contract). */
export type ConnectableTool = Schemas['StartConnectionRequest']['integration'];

export type ConnectOutcome = { outcome: 'connected' | 'cancelled' | 'failed'; reason: string | null; connectionId: number | null };

export type CatalogueItem = Schemas['CatalogueItemResource'];

export type LibraryGroup = CatalogueItem['group'];

export const LIBRARY_GROUPS: LibraryGroup[] = ['crm', 'deals', 'calendar', 'leads', 'more'];

/** "Up to date", "Syncing", "Delayed" or "Reconnect" (Connections and Sync journey, How sync works). */
export function syncState(connection: Connection): 'upToDate' | 'syncing' | 'delayed' | 'reconnect' {
  if (connection.status === 'reconnect_needed') return 'reconnect';
  if (connection.status === 'failing') return 'delayed';

  return connection.last_synced_at ? 'upToDate' : 'syncing';
}

/** The people's catalogue: what this person can connect (B3.2), reloaded on focus. */
export function useCatalogue() {
  const { status } = useAuth();
  const [items, setItems] = useState<CatalogueItem[] | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn') {
      return;
    }

    const { data } = await api().GET('/catalogue');
    setItems(data?.data ?? []);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { items, reload: load };
}

/** "Notify me" / "Join beta", or undo it. */
export async function setInterest(tool: string, interested: boolean): Promise<boolean> {
  const call = interested
    ? api().POST('/catalogue/{tool}/interest', { params: { path: { tool } } })
    : api().DELETE('/catalogue/{tool}/interest', { params: { path: { tool } } });
  const { response } = await call;

  return response.ok;
}

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
export async function connectTool(integration: string, apiKey?: string): Promise<ConnectOutcome> {
  // The id comes from the server's own connection list.
  const { data, error } = await api().POST('/connections', {
    body: { integration: integration as ConnectableTool, ...(apiKey !== undefined ? { api_key: apiKey } : {}) },
  });

  if (!data) {
    const message = error && typeof error === 'object' && 'message' in error && typeof error.message === 'string' ? error.message : null;

    return { outcome: 'failed', reason: message, connectionId: null };
  }

  // Tools connected with an API key (B7.2) are connected already: no sign-in page.
  if (data.connection_id) {
    return { outcome: 'connected', reason: null, connectionId: data.connection_id };
  }

  if (!data.authorization_url) {
    return { outcome: 'failed', reason: null, connectionId: null };
  }

  const result = await WebBrowser.openAuthSessionAsync(data.authorization_url, 'reos://connections');

  if (result.type !== 'success') {
    return { outcome: 'cancelled', reason: null, connectionId: null };
  }

  const params = new URL(result.url).searchParams;
  const outcome = params.get('outcome');

  const connection = Number(params.get('connection'));

  return {
    outcome: outcome === 'connected' || outcome === 'cancelled' ? outcome : 'failed',
    reason: params.get('reason'),
    connectionId: Number.isInteger(connection) && connection > 0 ? connection : null,
  };
}

/** Two letters for a tool's tile: "Google Calendar" → "GC". */
export function toolInitials(name: string): string {
  return name
    .split(/\s+/)
    .filter((word) => /^[A-Za-z]/.test(word) && word.toLowerCase() !== 'and')
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase() ?? '')
    .join('');
}
