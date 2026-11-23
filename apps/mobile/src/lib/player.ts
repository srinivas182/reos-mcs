/**
 * The course player's seat time on the phone (A8.2, ADR 0030): heartbeats
 * every 15 seconds online, stretches recorded on the phone offline (A8.2b),
 * check-ins either way. The server decides what counts.
 */
import type { paths } from '@reos/api-client';
import * as Crypto from 'expo-crypto';
import { useNetworkState } from 'expo-network';
import type { VideoPlayer } from 'expo-video';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';

import { api } from '@/lib/api';
import type { LearnLesson } from '@/lib/learn';
import { flush, offlineState, recordCheckin, recordWatch, type SavedLesson } from '@/lib/offline';

type Posted<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type PlayerState = LearnLesson['player'];
export type CheckinResult = Posted<'/learn/lessons/{lesson}/checkins'>;

export const BEAT_MS = 15_000;
export const SPEEDS = [0.75, 1, 1.25, 1.5, 2] as const;

/** "1 h 12 m" / "45 m" / "30 s". */
export function spent(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);

  if (h > 0) return m > 0 ? `${h} h ${m} m` : `${h} h`;
  if (m > 0) return `${m} m`;

  return `${Math.max(0, Math.floor(seconds))} s`;
}

const zone = () => Intl.DateTimeFormat().resolvedOptions().timeZone ?? null;

/**
 * Seat time for the lesson on screen. Online: a heartbeat every 15 seconds
 * with whether the video plays and REOS is in front. Offline with the lesson
 * saved: a stretch recorded on the phone instead, sent when back online. A
 * waiting check-in pauses the video.
 */
export function useSeatTime(lesson: LearnLesson, player: VideoPlayer | null, saved: SavedLesson | undefined) {
  const { isConnected } = useNetworkState();
  const online = isConnected !== false;
  const [state, setState] = useState<PlayerState>(() => (saved && !online ? offlineState(saved) : lesson.player));
  const viewing = useRef(Crypto.randomUUID());

  const beat = useCallback(async () => {
    // In front unless the phone says otherwise (a just-opened app can report "unknown").
    const active = AppState.currentState !== 'background' && AppState.currentState !== 'inactive';
    const playing = player ? player.playing : true;

    if (!online) {
      if (!saved) return;
      if (playing && active && !offlineState(saved).checkin) recordWatch(saved.grant);
      setState(offlineState(saved));

      return;
    }

    const { data } = await api()
      .POST('/learn/lessons/{lesson}/heartbeat', {
        params: { path: { lesson: lesson.id } },
        body: { position: Math.floor(player?.currentTime ?? 0), playing, visible: active, session: viewing.current, timezone: zone() },
      })
      .catch(() => ({ data: undefined }));
    if (data) setState(data.data);
  }, [lesson.id, online, player, saved]);

  // Back online: what was watched offline goes first.
  useEffect(() => {
    if (online && saved) void flush(saved.grant).catch(() => null);
  }, [online, saved]);

  useEffect(() => {
    if (lesson.player.completed) return;
    // The first heartbeat right away, then every 15 seconds.
    const first = setTimeout(() => void beat(), 0);
    const timer = setInterval(() => void beat(), BEAT_MS);

    return () => {
      clearTimeout(first);
      clearInterval(timer);
    };
  }, [beat, lesson.player.completed]);

  useEffect(() => {
    if (state.checkin) player?.pause();
  }, [state.checkin, player]);

  const answer = useCallback(
    async (key: string, choice: number | null): Promise<CheckinResult | null> => {
      if (!online && saved) {
        recordCheckin(saved.grant, key, choice);
        const point = saved.data.checkpoints.find((c) => c.key === key);
        const next = offlineState(saved);
        setState(next);

        return {
          correct: point?.answer ? choice === point.answer.correct : null,
          explanation: point?.answer?.explanation ?? null,
          source: point?.answer?.source ?? null,
          state: next,
        };
      }

      const { data } = await api().POST('/learn/lessons/{lesson}/checkins', { params: { path: { lesson: lesson.id } }, body: { key, choice } });
      if (!data) return null;
      setState(data.state);

      return data;
    },
    [lesson.id, online, saved],
  );

  return { state, online, answer };
}

/** Speed, and audio only (keeps playing with the screen locked, with the lock-screen controls). */
export function configurePlayer(player: VideoPlayer, change: { rate?: number; background?: boolean }): void {
  if (change.rate !== undefined) player.playbackRate = change.rate;
  if (change.background !== undefined) {
    player.staysActiveInBackground = change.background;
    player.showNowPlayingNotification = change.background;
  }
}

export type ReportReason = 'wrong_answer' | 'unclear' | 'out_of_date' | 'other';

export async function reportQuestion(question: number, reason: ReportReason): Promise<boolean> {
  const { data } = await api().POST('/questions/{question}/report', { params: { path: { question } }, body: { reason } });

  return data !== undefined;
}
