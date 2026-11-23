import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ExternalLink } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import type { Cue, LessonVideo } from '@/components/lesson-player';
import { LessonPlayer } from '@/components/lesson-player';
import { PageHeader } from '@/components/page-header';
import { AskInstructor } from '@/components/player/ask-instructor';
import { CheckinDialog } from '@/components/player/checkin-dialog';
import { SeatMeter } from '@/components/player/seat-meter';
import { Button } from '@/components/ui/button';
import type { Language } from '@/lib/content';
import type { PlayerState } from '@/lib/seat-time';
import { useSeatTime } from '@/lib/seat-time';
import { cn } from '@/lib/utils';
import { index, lesson as openLesson } from '@/routes/learn';

type Lesson = {
    id: number;
    title: string;
    language: Language;
    minutes: number | null;
    course: { id: number; title: string };
    sections: { heading: string | null; text: string }[];
    documents: {
        id: number;
        kind: 'slides' | 'document';
        name: string;
        url: string;
        pages: { page: number; text: string }[];
    }[];
    video: LessonVideo | null;
    transcript: Cue[];
    captions: Partial<Record<Language, Cue[]>> | null;
    previous: number | null;
    next: number | null;
};

const anchor = (value: string) =>
    `h-${value.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;

/**
 * The course player (web journey screen 5; A5.3b, A8.2): video with its
 * synced transcript, speed and captions, slides page by page and the lesson
 * text, opened at the moment, page or heading a search result points to.
 * Seat time counts while the video plays with the tab in front; check-ins
 * stop the video until answered; the meter shows what has counted and why
 * not when it didn't.
 */
export default function LessonPage({
    lesson,
    at,
    player,
}: {
    lesson: Lesson;
    at: { page: number | null; seconds: number | null; heading: string | null };
    player: PlayerState;
}) {
    const { t, i18n } = useTranslation();
    const video = useRef<HTMLVideoElement>(null);
    const seat = useSeatTime(lesson.id, player, video, lesson.video !== null);
    const target =
        at.page !== null
            ? lesson.documents.find((d) =>
                  d.pages.some((p) => p.page === at.page),
              )
            : undefined;

    useEffect(() => {
        const id =
            target && at.page !== null
                ? `page-${target.id}-${at.page}`
                : at.heading !== null
                  ? anchor(at.heading)
                  : null;
        if (id)
            document.getElementById(id)?.scrollIntoView({ block: 'center' });
    }, [target, at.page, at.heading]);

    return (
        <>
            <Head title={lesson.title} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={lesson.title}
                    description={lesson.course.title}
                    back={
                        <Link
                            href={index()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('learn.lesson.back')}
                        </Link>
                    }
                    actions={
                        <>
                            {lesson.previous !== null ? (
                                <Button variant="outline" asChild>
                                    <Link href={openLesson(lesson.previous)}>
                                        <ArrowLeft aria-hidden />
                                        {t('learn.lesson.previous')}
                                    </Link>
                                </Button>
                            ) : null}
                            {lesson.next !== null ? (
                                <Button asChild>
                                    <Link href={openLesson(lesson.next)}>
                                        {t('learn.lesson.next')}
                                        <ArrowRight aria-hidden />
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <SeatMeter state={seat.state} failed={seat.failed} />
                <CheckinDialog
                    checkpoint={seat.state.checkin}
                    onAnswer={seat.answer}
                />

                {lesson.video || lesson.transcript.length > 0 ? (
                    <LessonPlayer
                        video={lesson.video}
                        transcript={lesson.transcript}
                        captions={lesson.captions}
                        language={i18n.language.startsWith('es') ? 'es' : 'en'}
                        startAt={at.seconds}
                        title={lesson.title}
                        videoRef={video}
                    />
                ) : null}

                <AskInstructor lessonId={lesson.id} videoRef={video} />

                {lesson.documents.map((document) => (
                    <section
                        key={document.id}
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-semibold">
                                {t(`content.kinds.${document.kind}`)}
                            </h2>
                            <Button variant="outline" asChild>
                                <a
                                    href={`${document.url}${target?.id === document.id && at.page !== null ? `#page=${at.page}` : ''}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink aria-hidden />
                                    {t('learn.lesson.open', {
                                        name: document.name,
                                    })}
                                </a>
                            </Button>
                        </div>
                        <ol className="grid gap-3 md:grid-cols-2">
                            {document.pages.map((page) => {
                                const here =
                                    target?.id === document.id &&
                                    page.page === at.page;

                                return (
                                    <li
                                        key={page.page}
                                        id={`page-${document.id}-${page.page}`}
                                        className={cn(
                                            'space-y-1 rounded-reos-md border p-4',
                                            here
                                                ? 'border-primary bg-accent'
                                                : 'border-border',
                                        )}
                                    >
                                        <p className="text-sm font-semibold text-muted-foreground">
                                            {t('learn.lesson.page', {
                                                page: page.page,
                                            })}
                                            {here
                                                ? ` · ${t('learn.lesson.here')}`
                                                : ''}
                                        </p>
                                        <p className="text-body whitespace-pre-line">
                                            {page.text}
                                        </p>
                                    </li>
                                );
                            })}
                        </ol>
                    </section>
                ))}

                {lesson.sections.length > 0 ? (
                    <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="font-semibold">
                            {t('learn.lesson.text')}
                        </h2>
                        {lesson.sections.map((section, i) => (
                            <div
                                key={i}
                                id={
                                    section.heading !== null
                                        ? anchor(section.heading)
                                        : undefined
                                }
                                className={cn(
                                    'space-y-2 rounded-reos-md p-2',
                                    section.heading !== null &&
                                        section.heading === at.heading
                                        ? 'bg-accent'
                                        : undefined,
                                )}
                            >
                                {section.heading !== null ? (
                                    <h3 className="text-lg font-semibold">
                                        {section.heading}
                                    </h3>
                                ) : null}
                                {section.text
                                    .split(/\n\s*\n/)
                                    .map((paragraph, j) => (
                                        <p
                                            key={j}
                                            className="text-body whitespace-pre-line"
                                        >
                                            {paragraph}
                                        </p>
                                    ))}
                            </div>
                        ))}
                    </section>
                ) : null}

                {!lesson.video &&
                lesson.transcript.length === 0 &&
                lesson.documents.length === 0 &&
                lesson.sections.length === 0 ? (
                    <p className="text-body text-muted-foreground">
                        {t('learn.lesson.empty')}
                    </p>
                ) : null}
            </div>
        </>
    );
}
