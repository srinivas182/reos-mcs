import type { RefObject } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { postJson } from '@/lib/content';
import { checkin, heartbeat } from '@/routes/learn';

export type SeatReason =
    | 'gap'
    | 'paused'
    | 'hidden'
    | 'checkin_due'
    | 'daily_max';

export type Checkpoint = {
    key: string;
    at: number;
    kind: 'question' | 'presence';
    question: { id: number; prompt: string; options: string[] } | null;
};

/** What the server says has counted (A8.2, ADR 0030). */
export type PlayerState = {
    counted: number;
    required: number;
    percent: number;
    reason: SeatReason | null;
    checkin: Checkpoint | null;
    completed: boolean;
    today_left: number | null;
};

export type CheckinAnswer = {
    correct: boolean | null;
    explanation: string | null;
    source: string | null;
    state: PlayerState;
};

/** Heartbeats come this often; the server counts at most 20 seconds of each. */
export const BEAT_MS = 15_000;

const session = () =>
    globalThis.crypto?.randomUUID?.().slice(0, 36) ??
    Math.random().toString(36).slice(2);

/**
 * Seat time on the web lesson page: a heartbeat every 15 seconds with where
 * the video is, whether it plays and whether the tab is in front. The server
 * decides what counts; this only reports. A lesson with no video counts
 * while the page is open and in front (reading the slides or text).
 */
export function useSeatTime(
    lessonId: number,
    initial: PlayerState,
    video: RefObject<HTMLVideoElement | null>,
    hasVideo: boolean,
) {
    const [state, setState] = useState<PlayerState>(initial);
    const [failed, setFailed] = useState(false);
    const viewing = useRef<string>(session());

    const beat = useCallback(async () => {
        const player = video.current;
        const playing = hasVideo
            ? player !== null && !player.paused && !player.ended
            : true;

        try {
            const response = await postJson<{ data: PlayerState }>(
                heartbeat.url(lessonId),
                {
                    position: Math.floor(player?.currentTime ?? 0),
                    playing,
                    visible: document.visibilityState === 'visible',
                    session: viewing.current,
                    timezone:
                        Intl.DateTimeFormat().resolvedOptions().timeZone ??
                        null,
                },
            );
            setState(response.data);
            setFailed(false);
        } catch {
            setFailed(true);
        }
    }, [lessonId, video, hasVideo]);

    useEffect(() => {
        if (initial.completed) return;
        void beat();
        const timer = window.setInterval(() => void beat(), BEAT_MS);

        return () => window.clearInterval(timer);
    }, [beat, initial.completed]);

    // A check-in waits: the video stops until it is answered.
    useEffect(() => {
        if (state.checkin) video.current?.pause();
    }, [state.checkin, video]);

    const answer = useCallback(
        async (key: string, choice: number | null) => {
            const result = await postJson<CheckinAnswer>(
                checkin.url(lessonId),
                { key, choice },
            );
            setState(result.state);

            return result;
        },
        [lessonId],
    );

    return { state, failed, answer };
}

/** "1 h 12 m" / "45 m" / "30 s". */
export function spent(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);

    if (h > 0) return m > 0 ? `${h} h ${m} m` : `${h} h`;
    if (m > 0) return `${m} m`;

    return `${Math.max(0, Math.floor(seconds))} s`;
}
