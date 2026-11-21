/**
 * To-dos on the phone (B4.3b, ADR 0018), with a queue for no signal.
 *
 * - Every new to-do gets the phone's own id (`client_id`) before it is sent,
 *   so sending it again after being offline saves it once.
 * - With no signal (or the server down), new to-dos and Done are kept on the
 *   phone and sent, in order, when the app is back in front or the phone is
 *   back online. Anything the server refuses for good is dropped.
 */
import type { Schemas } from '@reos/api-client';
import { randomUUID } from 'expo-crypto';
import { useNetworkState } from 'expo-network';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { AppState } from 'react-native';
import { createMMKV } from 'react-native-mmkv';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

export type Todo = Schemas['TodoResource'];
type SaveBody = Schemas['SaveTodoRequest'];

/** A to-do as confirmed on the card, before the server has it. */
export type TodoDraft = {
  clientId: string;
  title: string;
  notes: string | null;
  due_at: string | null;
  due_date: string | null;
  remind_at: string | null;
  /** A contact on this phone. */
  contact: { id: string; name: string } | null;
  source: 'voice' | 'typed';
};

type Queued = { kind: 'create'; draft: TodoDraft } | { kind: 'done'; id: number | null; clientId: string | null; done: boolean };

/** What the list shows: saved to-dos, and new ones still waiting on the phone. */
export type TodoRow = { key: string; id: number | null; clientId: string | null; title: string; due_at: string | null; due_date: string | null; contact: string | null; waiting: boolean };

const store = createMMKV({ id: 'reos.todos' });
const QUEUE = 'queue';
const listeners = new Set<() => void>();

const newClientId = (): string => randomUUID();

export function blankDraft(source: TodoDraft['source']): TodoDraft {
  return { clientId: newClientId(), title: '', notes: null, due_at: null, due_date: null, remind_at: null, contact: null, source };
}

export function queued(): Queued[] {
  try {
    const raw = store.getString(QUEUE);

    return raw ? (JSON.parse(raw) as Queued[]) : [];
  } catch {
    return [];
  }
}

function writeQueue(items: Queued[]): void {
  if (items.length === 0) {
    store.remove(QUEUE);
  } else {
    store.set(QUEUE, JSON.stringify(items));
  }

  listeners.forEach((listener) => listener());
}

function enqueue(item: Queued): void {
  writeQueue([...queued(), item]);
}

/** The server's id for a to-do the phone named, once known. */
function rememberId(clientId: string | null, id: number): void {
  if (clientId !== null) {
    store.set(`id:${clientId}`, String(id));
  }
}

export function knownId(clientId: string | null): number | null {
  const raw = clientId === null ? undefined : store.getString(`id:${clientId}`);

  return raw === undefined ? null : Number(raw);
}

function body(draft: TodoDraft): SaveBody {
  return {
    client_id: draft.clientId,
    title: draft.title.trim(),
    notes: draft.notes?.trim() || null,
    due_at: draft.due_at,
    due_date: draft.due_at === null ? draft.due_date : null,
    remind_at: draft.remind_at,
    related_type: draft.contact ? 'phone_contact' : null,
    related_id: draft.contact?.id ?? null,
    related_label: draft.contact?.name ?? null,
    source: draft.source,
  };
}

type Outcome = 'saved' | 'offline' | 'refused';

/** No answer, or the server is down: try again later. A 4xx answer will never succeed. */
async function send(call: () => Promise<{ response: Response }>): Promise<{ outcome: Outcome; response?: Response }> {
  try {
    const { response } = await call();

    if (response.ok) return { outcome: 'saved', response };

    return { outcome: response.status >= 500 || response.status === 429 ? 'offline' : 'refused', response };
  } catch {
    return { outcome: 'offline' };
  }
}

/** Saves a confirmed to-do, or keeps it on the phone with no signal. */
export async function saveTodo(draft: TodoDraft): Promise<{ todo: Todo | null; outcome: Outcome }> {
  let todo: Todo | null = null;
  const { outcome } = await send(async () => {
    const result = await api().POST('/todos', { body: body(draft) });
    todo = result.data?.data ?? null;

    return result;
  });

  if (todo !== null) {
    rememberId(draft.clientId, (todo as Todo).id);
  }

  if (outcome === 'offline') {
    enqueue({ kind: 'create', draft });
  }

  return { todo, outcome };
}

/** Marks a to-do done (or not), by the server's id or, if it's still on the phone, its own. */
export async function setDone(target: { id: number | null; clientId: string | null }, done = true): Promise<Outcome> {
  const id = target.id ?? knownId(target.clientId);

  if (id === null) {
    enqueue({ kind: 'done', id: null, clientId: target.clientId, done });

    return 'offline';
  }

  const path = { params: { path: { todo: id } } };
  const { outcome } = await send(() => (done ? api().POST('/todos/{todo}/done', path) : api().DELETE('/todos/{todo}/done', path)));

  if (outcome === 'offline') {
    enqueue({ kind: 'done', id, clientId: target.clientId, done });
  }

  return outcome;
}

let flushing: Promise<number> | null = null;

/** Sends what the phone kept, in order; stops at the first that still can't reach the server. Returns how many were sent. */
export function flushTodoQueue(): Promise<number> {
  if (flushing !== null) {
    return flushing;
  }

  const run = sendQueued();
  flushing = run;
  void run.finally(() => {
    if (flushing === run) flushing = null;
  });

  return run;
}

async function sendQueued(): Promise<number> {
  let sent = 0;

  for (;;) {
    const [next, ...rest] = queued();

    if (next === undefined) break;

    let outcome: Outcome;

    if (next.kind === 'create') {
      let saved: Todo | undefined;
      ({ outcome } = await send(async () => {
        const result = await api().POST('/todos', { body: body(next.draft) });
        saved = result.data?.data;

        return result;
      }));

      if (saved !== undefined) rememberId(next.draft.clientId, saved.id);
    } else {
      const id = next.id ?? knownId(next.clientId);

      if (id === null) {
        // Its to-do never reached the server; nothing to mark.
        outcome = 'refused';
      } else {
        const path = { params: { path: { todo: id } } };
        ({ outcome } = await send(() => (next.done ? api().POST('/todos/{todo}/done', path) : api().DELETE('/todos/{todo}/done', path))));
      }
    }

    if (outcome === 'offline') break;

    writeQueue(rest);
    sent += outcome === 'saved' ? 1 : 0;
  }

  return sent;
}

/** Sends the queue when the app comes to the front or the phone is back online (mounted once, in the app's layout). */
export function useTodoQueueFlush(): void {
  const { status } = useAuth();
  const { isConnected } = useNetworkState();

  useEffect(() => {
    if (status === 'signedIn' && isConnected !== false) {
      void flushTodoQueue();
    }
  }, [status, isConnected]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active' && status === 'signedIn') void flushTodoQueue();
    });

    return () => subscription.remove();
  }, [status]);
}

function rowsFrom(todos: Todo[]): TodoRow[] {
  const waiting = queued();
  // The last Done (or Undo) kept on the phone for each to-do wins.
  const lastDone = new Map<number | string | null, boolean>();
  waiting.forEach((item) => item.kind === 'done' && lastDone.set(item.id ?? knownId(item.clientId) ?? item.clientId, item.done));
  const doneOnPhone = new Set([...lastDone].flatMap(([key, done]) => (done ? [key] : [])));
  const saved: TodoRow[] = todos
    .filter((todo) => !doneOnPhone.has(todo.id) && !doneOnPhone.has(todo.client_id))
    .map((todo) => ({ key: `t${todo.id}`, id: todo.id, clientId: todo.client_id, title: todo.title, due_at: todo.due_at, due_date: todo.due_date, contact: todo.related?.label ?? null, waiting: false }));
  const savedIds = new Set(todos.map((todo) => todo.client_id));
  const pending: TodoRow[] = waiting.flatMap((item) =>
    item.kind === 'create' && !savedIds.has(item.draft.clientId) && !doneOnPhone.has(item.draft.clientId)
      ? [{ key: `c${item.draft.clientId}`, id: null, clientId: item.draft.clientId, title: item.draft.title, due_at: item.draft.due_at, due_date: item.draft.due_date, contact: item.draft.contact?.name ?? null, waiting: true }]
      : [],
  );

  return [...pending, ...saved];
}

/** Open to-dos (the server's, then any still on the phone), reloaded on focus and whenever the queue changes. */
export function useOpenTodos() {
  const { status } = useAuth();
  const [todos, setTodos] = useState<Todo[] | null>(null);
  const [, setVersion] = useState(0);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;

    try {
      const { data } = await api().GET('/todos', { params: { query: { status: 'open' } } });

      if (data) setTodos(data.data);
    } catch {
      // No signal: show what the phone has.
      setTodos((current) => current ?? []);
    }
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  useEffect(() => {
    const listener = () => {
      setVersion((n) => n + 1);
      if (queued().length === 0) void load();
    };
    listeners.add(listener);

    return () => {
      listeners.delete(listener);
    };
  }, [load]);

  return { rows: todos === null ? null : rowsFrom(todos), reload: load };
}
