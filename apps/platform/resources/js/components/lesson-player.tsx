import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { clock } from '@/lib/content';
import { cn } from '@/lib/utils';

export type LessonVideo = {
    type: 'hls' | 'file';
    url: string;
    duration: number | null;
    poster: string | null;
};

export type Cue = { seconds: number; text: string };

/**
 * A lesson's video with its synced transcript (A5.3b; the first shape of the
 * course player, web journey screen 5). Opens at the second a search result
 * points to; clicking a transcript line jumps there. Mux streams (HLS) play
 * through hls.js where the browser can't play them itself.
 */
export function LessonPlayer({
    video,
    transcript,
    startAt = null,
    title,
}: {
    video: LessonVideo | null;
    transcript: Cue[];
    startAt?: number | null;
    title: string;
}) {
    const { t } = useTranslation();
    const element = useRef<HTMLVideoElement>(null);
    const [now, setNow] = useState(startAt ?? 0);

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
    }, [video, startAt]);

    const jump = (seconds: number) => {
        setNow(seconds);

        if (element.current) {
            element.current.currentTime = seconds;
            void element.current.play().catch(() => undefined);
        }
    };

    const active = transcript.reduce<number>(
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
                    >
                        <track kind="captions" />
                    </video>
                    {startAt !== null ? (
                        <p className="text-sm text-muted-foreground">
                            {t('learn.lesson.startsAt', {
                                time: clock(startAt),
                            })}
                        </p>
                    ) : null}
                </div>
            ) : null}
            {transcript.length > 0 ? (
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
                        {transcript.map((cue, index) => (
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
