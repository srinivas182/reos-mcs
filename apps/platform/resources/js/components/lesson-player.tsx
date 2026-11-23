import type { RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
import { RotateCcw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { Language } from '@/lib/content';
import { clock } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { cn } from '@/lib/utils';

export type LessonVideo = {
    type: 'hls' | 'file';
    url: string;
    duration: number | null;
    poster: string | null;
};

export type Cue = { seconds: number; text: string };

/** Playback speeds offered. Faster playback never adds seat time (ADR 0030). */
export const SPEEDS = [0.75, 1, 1.25, 1.5, 1.75, 2] as const;

/**
 * The course player's video (web journey screen 5; A5.3b, A8.2): the video
 * with its synced transcript, a speed control and, for an AI lesson, the
 * captions in either language. Opens at the second a search result points
 * to; clicking a transcript line jumps there. Mux streams (HLS) play through
 * hls.js where the browser can't play them itself. Seat time is the page's
 * job (`useSeatTime`), through `videoRef`.
 */
export function LessonPlayer({
    video,
    transcript,
    captions = null,
    language = 'en',
    startAt = null,
    title,
    videoRef,
}: {
    video: LessonVideo | null;
    transcript: Cue[];
    captions?: Partial<Record<Language, Cue[]>> | null;
    language?: Language;
    startAt?: number | null;
    title: string;
    videoRef?: RefObject<HTMLVideoElement | null>;
}) {
    const { t } = useTranslation();
    const own = useRef<HTMLVideoElement>(null);
    const element = videoRef ?? own;
    const [now, setNow] = useState(startAt ?? 0);
    const [speed, setSpeed] = useState(1);
    const languages = (Object.keys(captions ?? {}) as Language[]).filter(
        (l) => (captions?.[l]?.length ?? 0) > 0,
    );
    const [shown, setShown] = useState<Language>(
        languages.includes(language) ? language : (languages[0] ?? language),
    );
    const cues = captions?.[shown] ?? transcript;

    useEffect(() => {
        const player = element.current;

        if (!player || !video) {
            return;
        }

        let destroy: (() => void) | undefined;
        const seek = () => {
            if (startAt !== null) {
                player.currentTime = startAt;
            }
        };

        if (
            video.type === 'hls' &&
            !player.canPlayType('application/vnd.apple.mpegurl')
        ) {
            void import('hls.js').then(({ default: Hls }) => {
                if (!Hls.isSupported()) return;
                const hls = new Hls();
                hls.loadSource(video.url);
                hls.attachMedia(player);
                destroy = () => hls.destroy();
            });
        } else {
            player.src = video.url;
        }

        player.addEventListener('loadedmetadata', seek, { once: true });

        return () => {
            player.removeEventListener('loadedmetadata', seek);
            destroy?.();
        };
    }, [video, startAt, element]);

    useEffect(() => {
        if (element.current) element.current.playbackRate = speed;
    }, [speed, element]);

    const jump = (seconds: number) => {
        setNow(seconds);

        if (element.current) {
            element.current.currentTime = seconds;
            void element.current.play().catch(() => undefined);
        }
    };

    const active = cues.reduce<number>(
        (found, cue, index) => (cue.seconds <= now ? index : found),
        -1,
    );

    return (
        <section
            className="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
            aria-label={t('learn.lesson.video')}
        >
            {video ? (
                <div className="space-y-2">
                    <video
                        ref={element}
                        controls
                        playsInline
                        preload="metadata"
                        poster={video.poster ?? undefined}
                        className="aspect-video w-full rounded-reos-lg bg-muted"
                        aria-label={title}
                        onTimeUpdate={(e) =>
                            setNow(Math.floor(e.currentTarget.currentTime))
                        }
                        onLoadedMetadata={(e) => {
                            e.currentTarget.playbackRate = speed;
                        }}
                    >
                        <track kind="captions" />
                    </video>
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (element.current) {
                                    element.current.currentTime = Math.max(
                                        0,
                                        element.current.currentTime - 10,
                                    );
                                }
                            }}
                        >
                            <RotateCcw aria-hidden />
                            {t('player.back10')}
                        </Button>
                        <label className="flex items-center gap-2 text-body">
                            {t('player.speed')}
                            <select
                                className={`${nativeSelect} w-28`}
                                value={speed}
                                onChange={(e) =>
                                    setSpeed(Number(e.target.value))
                                }
                            >
                                {SPEEDS.map((s) => (
                                    <option key={s} value={s}>
                                        {`${s}×`}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {languages.length > 1 ? (
                            <label className="flex items-center gap-2 text-body">
                                {t('player.captions')}
                                <select
                                    className={`${nativeSelect} w-40`}
                                    value={shown}
                                    onChange={(e) =>
                                        setShown(e.target.value as Language)
                                    }
                                >
                                    {languages.map((l) => (
                                        <option key={l} value={l}>
                                            {t(`player.languages.${l}`)}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}
                    </div>
                    {startAt !== null ? (
                        <p className="text-sm text-muted-foreground">
                            {t('learn.lesson.startsAt', {
                                time: clock(startAt),
                            })}
                        </p>
                    ) : null}
                </div>
            ) : null}
            {cues.length > 0 ? (
                <div className="space-y-2 rounded-reos-lg border border-border bg-card p-4">
                    <h2 className="font-semibold">
                        {t('learn.lesson.transcript')}
                    </h2>
                    {video ? null : (
                        <p className="text-sm text-muted-foreground">
                            {t('learn.lesson.noVideo')}
                        </p>
                    )}
                    <ol className="max-h-96 space-y-1 overflow-y-auto">
                        {cues.map((cue, index) => (
                            <li key={`${cue.seconds}-${index}`}>
                                {video ? (
                                    <button
                                        type="button"
                                        title={t('learn.lesson.jump', {
                                            time: clock(cue.seconds),
                                        })}
                                        aria-current={
                                            index === active
                                                ? 'true'
                                                : undefined
                                        }
                                        onClick={() => jump(cue.seconds)}
                                        className={cn(
                                            'flex min-h-12 w-full gap-3 rounded-reos-md px-2 py-2 text-left text-body focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                            index === active
                                                ? 'bg-accent text-foreground'
                                                : 'hover:bg-muted',
                                        )}
                                    >
                                        <span className="w-14 shrink-0 font-semibold text-primary tabular-nums">
                                            {clock(cue.seconds)}
                                        </span>
                                        <span>{cue.text}</span>
                                    </button>
                                ) : (
                                    // No video to play: the times are just times.
                                    <p className="flex gap-3 px-2 py-2 text-body">
                                        <span className="w-14 shrink-0 text-muted-foreground tabular-nums">
                                            {clock(cue.seconds)}
                                        </span>
                                        <span>{cue.text}</span>
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                </div>
            ) : null}
        </section>
    );
}
