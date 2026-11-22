/**
 * Video sessions on the phone (B6.3): your sessions, one session, joining
 * its room (LiveKit, or a simulated room where LiveKit isn't set up) and
 * the shared notes. A 404 on the list means sessions aren't rolled out to
 * this person yet (flag `video_rooms`), and the entries stay hidden.
 * Instructors and mentors schedule here or on the web (B6.3b).
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
        : listen(`users.${userId}`, ['session.ended', 'session.shared', 'session.recording'], (event) => {
            const data = event as { session?: string } | undefined;

            if (data?.session !== id) return;

            // Ended closes the room; shared (B6.3b) just brings in what the host shared.
            if (!sharedOnly(event)) onEnded();
            load();
          }),
    [userId, id, load, onEnded],
  );

  return { session, failed, reload: load, setSession };
}

/** `session.shared` carries only the session; `session.ended` also says when it ended. */
function sharedOnly(event: unknown): boolean {
  return !Object.prototype.hasOwnProperty.call(event ?? {}, 'ended_at');
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

type Plan = NonNullable<paths['/sessions']['post']['requestBody']>['content']['application/json'];

/** Schedule a session (B6.3b on the phone; instructors and mentors). */
export async function scheduleSession(plan: Plan): Promise<Result<Session>> {
  const { data, error } = await api().POST('/sessions', { body: plan });

  return data ? { ok: true, data } : { ok: false, message: problem(error) };
}

export type Recording = Session['recordings'][number];

/** The recording being asked for or made, if any (B6.3c). */
export function activeRecording(session: Session): Recording | null {
  return session.recordings.find((r) => r.status === 'asking' || r.status === 'recording' || r.status === 'stopping') ?? null;
}

export async function askToRecord(id: string): Promise<Result<Recording>> {
  const { data, error } = await api().POST('/sessions/{session}/recordings', { params: { path: { session: id } } });

  return data ? { ok: true, data } : { ok: false, message: problem(error) };
}

export async function answerRecording(id: string, recording: string, agreed: boolean): Promise<Result<Recording>> {
  const { data, error } = await api().POST('/sessions/{session}/recordings/{recording}/answer', {
    params: { path: { session: id, recording } },
    body: { agreed },
  });

  return data ? { ok: true, data } : { ok: false, message: problem(error) };
}

export async function stopRecording(id: string, recording: string): Promise<void> {
  await api().POST('/sessions/{session}/recordings/{recording}/stop', { params: { path: { session: id, recording } } });
}

export async function deleteRecording(id: string, recording: string): Promise<void> {
  await api().DELETE('/sessions/{session}/recordings/{recording}', { params: { path: { session: id, recording } } });
}

/** Out of the room: recording no longer waits for this person (B6.3c). */
export async function leaveSession(id: string): Promise<void> {
  await api().POST('/sessions/{session}/leave', { params: { path: { session: id } } });
}
