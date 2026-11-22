/**
 * Video sessions on the phone (B6.3): your sessions, one session, joining
 * its room (LiveKit, or a simulated room where LiveKit isn't set up) and
 * the shared notes. A 404 on the list means sessions aren't rolled out to
 * this person yet (flag `video_rooms`), and the entries stay hidden.
 * Instructors and mentors schedule on the web for now.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';

import { api } from '@/lib/api';
import { listen } from '@/lib/realtime';

type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type SessionList = Json<'/sessions'>;
export type Session = Json<'/sessions/{session}'>;
export type RoomConnection = NonNullable<
  paths['/sessions/{session}/join']['post']['responses'][200]['content']['application/json']
>;

/** Your sessions; null while loading or when sessions aren't on for this person. */
export function useSessions() {
  const [list, setList] = useState<SessionList | null>(null);

  const load = useCallback(() => {
    void api()
      .GET('/sessions')
      .then(({ data }) => setList(data ?? null))
      .catch(() => undefined);
  }, []);

  useFocusEffect(load);

  return { list, reload: load };
}

/** The next session that hasn't ended, for Today. */
export function nextSession(list: SessionList | null): Session | null {
  const now = Date.now();

  return (
    list?.sessions.find((s) => (s.status === 'scheduled' || s.status === 'live') && new Date(s.ends_at).getTime() > now) ?? null
  );
}

/**
 * One session, reloaded when it ends elsewhere (the host, or the booked
 * end) so an open room closes at once.
 */
export function useSession(id: string, userId: number | null, onEnded: () => void) {
  const [session, setSession] = useState<Session | null>(null);
  const [failed, setFailed] = useState(false);

  const load = useCallback(() => {
    void api()
      .GET('/sessions/{session}', { params: { path: { session: id } } })
      .then(({ data }) => {
        setFailed(!data);
        if (data) setSession(data);
      })
      .catch(() => setFailed(true));
  }, [id]);

  useFocusEffect(load);

  useEffect(
    () =>
      userId === null
        ? undefined
        : listen(`users.${userId}`, ['session.ended'], (event) => {
            if ((event as { session?: string } | undefined)?.session === id) {
              onEnded();
              load();
            }
          }),
    [userId, id, load, onEnded],
  );

  return { session, failed, reload: load, setSession };
}

type Result<T> = { ok: true; data: T } | { ok: false; message: string };

const problem = (error: unknown): string => {
  const message = (error as { message?: unknown } | undefined)?.message;

  return typeof message === 'string' ? message : '';
};

export async function joinSession(id: string): Promise<Result<RoomConnection>> {
  const { data, error } = await api().POST('/sessions/{session}/join', { params: { path: { session: id } } });

  return data ? { ok: true, data } : { ok: false, message: problem(error) };
}

export async function endSession(id: string): Promise<Session | null> {
  const { data } = await api().POST('/sessions/{session}/end', { params: { path: { session: id } } });

  return data ?? null;
}

export async function saveNotes(id: string, notes: string): Promise<Result<Session>> {
  const { data, error } = await api().PUT('/sessions/{session}/notes', {
    params: { path: { session: id } },
    body: { notes: notes.trim() === '' ? null : notes },
  });

  return data ? { ok: true, data } : { ok: false, message: problem(error) };
}

/** 2700 → "45:00". */
export function clock(seconds: number): string {
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}
