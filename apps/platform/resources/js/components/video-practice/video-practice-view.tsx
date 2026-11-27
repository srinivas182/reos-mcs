import {
    CircleStop,
    Gauge,
    Lightbulb,
    ShieldCheck,
    TriangleAlert,
    Video,
} from 'lucide-react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { speechLang, useDictation } from '@/components/role-play/use-dictation';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { Lesson } from '@/components/role-play/types';
import {
    VIDEO_DIMENSIONS,
    type ComplianceCode,
    type VideoKind,
    type VideoReview,
    type VideoSummary,
} from './types';

const camera = (): boolean =>
    typeof navigator !== 'undefined' &&
    !!navigator.mediaDevices?.getUserMedia &&
    typeof MediaRecorder !== 'undefined';

/**
 * Recording in the browser (A14.1b): camera and microphone, up to the
 * longest allowed. The video stays in the browser for the agent to watch;
 * the browser's speech recognition writes the words, which the agent can
 * correct before asking for the review.
 */
export function VideoRecorder({
    kinds,
    maxSeconds,
    busy,
    onReview,
}: {
    kinds: VideoKind[];
    maxSeconds: number;
    busy: boolean;
    onReview: (kind: VideoKind, transcript: string, seconds: number) => void;
}) {
    const { t, i18n } = useTranslation();
    const [kind, setKind] = useState<VideoKind>(kinds[0] ?? 'listing_pitch');
    const [state, setState] = useState<'idle' | 'recording' | 'recorded'>(
        'idle',
    );
    const [seconds, setSeconds] = useState(0);
    const [transcript, setTranscript] = useState('');
    const [playback, setPlayback] = useState<string | null>(null);
    const [available] = useState(camera);
    const preview = useRef<HTMLVideoElement | null>(null);
    const recorder = useRef<MediaRecorder | null>(null);
    const stream = useRef<MediaStream | null>(null);
    const started = useRef(0);
    const transcriptId = useId();
    const dictation = useDictation(speechLang(i18n.language), (words) =>
        setTranscript((now) =>
            now.trim() === '' ? words : `${now.trimEnd()} ${words}`,
        ),
    );

    const stop = useCallback(() => {
        if (recorder.current?.state === 'recording') recorder.current.stop();
        dictation.stop();
        stream.current?.getTracks().forEach((track) => track.stop());
        setSeconds(
            Math.max(1, Math.round((Date.now() - started.current) / 1000)),
        );
        setState('recorded');
    }, [dictation]);

    useEffect(() => {
        if (state !== 'recording') return;
        const timer = window.setInterval(() => {
            const elapsed = Math.round((Date.now() - started.current) / 1000);
            setSeconds(elapsed);
            if (elapsed >= maxSeconds) stop();
        }, 500);

        return () => window.clearInterval(timer);
    }, [state, maxSeconds, stop]);

    useEffect(
        () => () => {
            stream.current?.getTracks().forEach((track) => track.stop());
        },
        [],
    );

    useEffect(
        () => () => {
            if (playback) URL.revokeObjectURL(playback);
        },
        [playback],
    );

    const start = async () => {
        const media = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true,
        });
        stream.current = media;
        const chunks: Blob[] = [];
        const r = new MediaRecorder(media);
        r.ondataavailable = (e) => chunks.push(e.data);
        r.onstop = () =>
            setPlayback(
                URL.createObjectURL(new Blob(chunks, { type: r.mimeType })),
            );
        recorder.current = r;
        if (preview.current) preview.current.srcObject = media;
        setTranscript('');
        setPlayback(null);
        setSeconds(0);
        started.current = Date.now();
        r.start();
        if (dictation.supported) dictation.start();
        setState('recording');
    };

    if (!available) {
        return (
            <p className="rounded-reos-lg border bg-card p-5 text-base text-muted-foreground">
                {t('videopractice.noCamera')}
            </p>
        );
    }

    return (
        <div className="space-y-4 rounded-reos-lg border bg-card p-5">
            <div className="space-y-2">
                <p className="text-base font-medium">
                    {t('videopractice.choose')}
                </p>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={kind}
                    disabled={state === 'recording'}
                    onValueChange={(value) =>
                        value && setKind(value as VideoKind)
                    }
                >
                    {kinds.map((k) => (
                        <ToggleGroupItem
                            key={k}
                            value={k}
                            className="h-12 px-4"
                        >
                            {t(`videopractice.kinds.${k}`)}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <p className="text-base text-muted-foreground">
                    {t(`videopractice.kindHints.${kind}`)}
                </p>
            </div>

            <video
                ref={preview}
                muted
                autoPlay
                playsInline
                aria-hidden
                className={cn(
                    'aspect-video w-full max-w-xl rounded-reos-md bg-muted',
                    state !== 'recording' && 'hidden',
                )}
            />
            {state === 'recorded' && playback ? (
                <figure className="space-y-1">
                    <video
                        src={playback}
                        controls
                        playsInline
                        className="aspect-video w-full max-w-xl rounded-reos-md bg-muted"
                    >
                        <track kind="captions" />
                    </video>
                    <figcaption className="text-base text-muted-foreground">
                        {t('videopractice.yourRecording')}
                    </figcaption>
                </figure>
            ) : null}

            {state === 'recording' ? (
                <>
                    <p className="text-base font-medium" aria-live="polite">
                        {t('videopractice.recording', {
                            seconds,
                            max: maxSeconds,
                        })}
                    </p>
                    <Button variant="destructive" onClick={stop}>
                        <CircleStop aria-hidden />
                        {t('videopractice.stop')}
                    </Button>
                </>
            ) : (
                <Button
                    variant={state === 'recorded' ? 'outline' : 'default'}
                    onClick={() => void start()}
                >
                    <Video aria-hidden />
                    {state === 'recorded'
                        ? t('videopractice.again')
                        : t('videopractice.record')}
                </Button>
            )}

            {state === 'recorded' ? (
                <div className="space-y-2">
                    <Label htmlFor={transcriptId} className="text-base">
                        {t('videopractice.transcript')}
                    </Label>
                    <Textarea
                        id={transcriptId}
                        rows={6}
                        maxLength={5000}
                        value={transcript}
                        aria-describedby={`${transcriptId}-hint`}
                        onChange={(e) => setTranscript(e.target.value)}
                    />
                    <p
                        id={`${transcriptId}-hint`}
                        className="text-base text-muted-foreground"
                    >
                        {transcript.trim() === ''
                            ? t('videopractice.noWords')
                            : t('videopractice.transcriptHint')}
                    </p>
                    <Button
                        disabled={busy || transcript.trim() === ''}
                        onClick={() =>
                            onReview(kind, transcript.trim(), seconds)
                        }
                    >
                        {t('videopractice.getReview')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

/** The review: score, pace, filler words, five parts, three fixes, and what to fix before posting. */
export function ReviewView({
    review,
    lessonHref,
}: {
    review: VideoReview;
    lessonHref: (lesson: Lesson) => string;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-6">
            <section className="space-y-4 rounded-reos-lg border bg-card p-5">
                <h2 className="text-2xl font-semibold">
                    {t('videopractice.score', { score: review.score })}
                </h2>
                <div className="flex flex-wrap gap-3 text-base">
                    <span className="flex items-center gap-2">
                        <Gauge aria-hidden className="size-5" />
                        {t('videopractice.pace.value', { wpm: review.wpm })}
                    </span>
                    <span className="text-muted-foreground">
                        {t('videopractice.duration', {
                            seconds: review.duration_seconds,
                        })}
                    </span>
                </div>
                <p
                    className={cn(
                        'text-base font-medium',
                        review.pace === 'good'
                            ? 'text-reos-tone-success-fg'
                            : 'text-reos-tone-warning-fg',
                    )}
                >
                    {t(`videopractice.pace.${review.pace}`)}
                </p>
                <div className="space-y-2">
                    <h3 className="text-lg font-semibold">
                        {t('videopractice.fillersTitle')}
                    </h3>
                    {review.fillers.length === 0 ? (
                        <p className="text-base">
                            {t('videopractice.noFillers')}
                        </p>
                    ) : (
                        <ul className="flex flex-wrap gap-2">
                            {review.fillers.map((f) => (
                                <li key={f.word}>
                                    <Badge
                                        variant="outline"
                                        className="text-base"
                                    >
                                        {t('videopractice.fillerCount', {
                                            word: f.word,
                                            count: f.count,
                                        })}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <ul className="grid gap-2 sm:grid-cols-5">
                    {VIDEO_DIMENSIONS.map((d) => (
                        <li
                            key={d}
                            className="rounded-reos-md bg-muted px-3 py-2 text-base"
                        >
                            {t('videopractice.part', {
                                label: t(`videopractice.dimensions.${d}`),
                                score: review.scores[d],
                            })}
                        </li>
                    ))}
                </ul>
            </section>

            <section className="space-y-3">
                <h2 className="text-lg font-semibold">
                    {t('videopractice.fixesTitle')}
                </h2>
                <ol className="space-y-3">
                    {review.fixes.map((fix) => (
                        <li
                            key={fix.dimension}
                            className="space-y-1 rounded-reos-md bg-reos-tone-info-bg p-4 text-reos-tone-info-fg"
                        >
                            <p className="flex items-center gap-2 font-semibold">
                                <Lightbulb aria-hidden className="size-5" />
                                {t(`videopractice.dimensions.${fix.dimension}`)}
                            </p>
                            <p className="text-base">{fix.text}</p>
                            <p className="text-base italic">
                                {t('videopractice.tryLine', {
                                    line: fix.example,
                                })}
                            </p>
                            <a
                                href={lessonHref(fix.lesson)}
                                className="inline-flex min-h-12 items-center text-base font-medium underline underline-offset-4"
                            >
                                {t('videopractice.lesson', {
                                    name: t(`roleplay.lessons.${fix.lesson}`),
                                })}
                            </a>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="space-y-2 rounded-reos-lg border bg-card p-5">
                <h2 className="flex items-center gap-2 text-lg font-semibold">
                    {review.compliance.length === 0 ? (
                        <ShieldCheck aria-hidden className="size-5" />
                    ) : (
                        <TriangleAlert aria-hidden className="size-5" />
                    )}
                    {t('videopractice.compliance.title')}
                </h2>
                {review.compliance.length === 0 ? (
                    <p className="text-base">
                        {t('videopractice.compliance.clean')}
                    </p>
                ) : (
                    <>
                        <p className="text-base">
                            {t('videopractice.compliance.hint')}
                        </p>
                        <ul className="list-disc space-y-1 pl-6 text-base">
                            {review.compliance.map((issue, i) => (
                                <li key={`${issue.code}-${i}`}>
                                    {t(
                                        `compliance.engine.${issue.code as ComplianceCode}`,
                                    )}
                                    {issue.phrase ? `: “${issue.phrase}”` : ''}
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </section>

            <details className="rounded-reos-lg border bg-card p-5">
                <summary className="min-h-12 cursor-pointer text-base font-medium">
                    {t('videopractice.transcript')}
                </summary>
                <p className="pt-2 text-base">{review.transcript}</p>
            </details>
            <p className="text-base text-muted-foreground">
                {t(`videopractice.engine.${review.engine}`)}
            </p>
        </div>
    );
}

/** Recent reviews, newest first. */
export function VideoRecentList({
    recent,
    href,
}: {
    recent: VideoSummary[];
    href: (id: string) => string;
}) {
    const { t, i18n } = useTranslation();

    if (recent.length === 0) {
        return (
            <EmptyState
                icon={Video}
                title={t('videopractice.none')}
                description={t('videopractice.noneHint')}
            />
        );
    }

    const day = new Intl.DateTimeFormat(i18n.language, {
        month: 'short',
        day: 'numeric',
    });

    return (
        <ul className="divide-y rounded-reos-lg border bg-card">
            {recent.map((item) => (
                <li key={item.id}>
                    <a
                        href={href(item.id)}
                        className="flex min-h-12 flex-wrap items-center justify-between gap-2 px-4 py-3 text-base hover:bg-muted"
                    >
                        <span>
                            <span className="font-medium">
                                {t(`videopractice.kinds.${item.kind}`)}
                            </span>
                            <span className="text-muted-foreground">
                                {' · '}
                                {t('videopractice.pace.value', {
                                    wpm: item.wpm,
                                })}
                                {' · '}
                                {day.format(new Date(item.created_at))}
                            </span>
                        </span>
                        <Badge variant="outline">
                            {t('videopractice.score', { score: item.score })}
                        </Badge>
                    </a>
                </li>
            ))}
        </ul>
    );
}
