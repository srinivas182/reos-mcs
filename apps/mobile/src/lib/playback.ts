/**
 * Playback settings (candidate screen 26, Sprint 8 wrap-up): quality per
 * connection and data saver. On Wi-Fi, Auto lets the stream adapt; on
 * mobile data, data saver caps it at 360p (otherwise 540p, the journey's
 * default), unless the learner picked a quality. Kept on this phone only.
 */
import { NetworkStateType, useNetworkState } from 'expo-network';
import { useSyncExternalStore } from 'react';
import { createMMKV } from 'react-native-mmkv';

import type { LearnLesson } from '@/lib/learn';

export type Quality = 'auto' | '720p' | '540p' | '360p';
export type PlaybackSettings = { quality: Quality; dataSaver: boolean };

export const QUALITIES: Quality[] = ['auto', '720p', '540p', '360p'];

/** Rough data use an hour, for the labels (Mux's typical bitrates). */
export const USE_PER_HOUR: Record<Quality, string> = { auto: '1–2 GB', '720p': '1.1 GB', '540p': '700 MB', '360p': '300 MB' };

const storage = createMMKV({ id: 'reos.playback' });
const KEY = 'settings';
const DEFAULTS: PlaybackSettings = { quality: 'auto', dataSaver: true };
const listeners = new Set<() => void>();
let current: PlaybackSettings = read();

function read(): PlaybackSettings {
  try {
    const saved = JSON.parse(storage.getString(KEY) ?? '{}') as Partial<PlaybackSettings>;

    return {
      quality: QUALITIES.includes(saved.quality as Quality) ? (saved.quality as Quality) : DEFAULTS.quality,
      dataSaver: typeof saved.dataSaver === 'boolean' ? saved.dataSaver : DEFAULTS.dataSaver,
    };
  } catch {
    return DEFAULTS;
  }
}

export function setPlayback(change: Partial<PlaybackSettings>): void {
  current = { ...current, ...change };
  storage.set(KEY, JSON.stringify(current));
  listeners.forEach((listener) => listener());
}

export function usePlayback(): PlaybackSettings {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener);

      return () => listeners.delete(listener);
    },
    () => current,
  );
}

/** The quality that applies now: the learner's pick, else data saver or 540p on mobile data, else Auto. */
export function effectiveQuality(settings: PlaybackSettings, cellular: boolean): Quality {
  if (settings.quality !== 'auto') return settings.quality;
  if (!cellular) return 'auto';

  return settings.dataSaver ? '360p' : '540p';
}

/** The stream to play for that quality (the adaptive one when there's no capped copy). */
export function streamFor(video: NonNullable<LearnLesson['video']>, quality: Quality): string {
  return quality === 'auto' ? video.url : (video.qualities[quality] ?? video.url);
}

export function useCellular(): boolean {
  const { type } = useNetworkState();

  return type === NetworkStateType.CELLULAR;
}
