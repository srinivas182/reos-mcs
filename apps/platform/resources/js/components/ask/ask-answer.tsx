import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Calculator,
    CalendarPlus,
    Info,
    Languages,
    Lightbulb,
    ListChecks,
    PlayCircle,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SaveFlashcard } from '@/components/practice/save-flashcard';
import { Button } from '@/components/ui/button';
import { index as bookingsIndex } from '@/routes/bookings';
import { lesson as lessonRoute } from '@/routes/learn';
import { math } from '@/routes/practice';
import { clockOf, type AskMessage, type AskMode } from './types';

/**
 * One answer in Ask (A10.1, screens 13, f02 and k11): where it comes from
 * (the course, beyond it with who paid, or not covered), its source with
 * Watch at, and the follow-ups: Quiz me, Explain simpler, In Spanish, Save
 * as flashcard and, for math, Try a similar one.
 */
export function AskAnswer({
    answer,
    question,
    state,
    last,
    busy,
    onFollow,
}: {
    answer: AskMessage;
    question: string;
    state: string;
    /** Follow-ups act on the thread's last answer only. */
    last: boolean;
    busy: boolean;
    onFollow: (mode: AskMode) => void;
}) {
    const { t } = useTranslation();
    const [picked, setPicked] = useState<number | null>(null);
    const source = answer.sources[0] ?? null;

    return (
        <article className="space-y-3 rounded-reos-lg border border-border bg-card p-4">
            <p className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                {answer.tier === 'content' ? (
                    <BookOpen aria-hidden className="size-4 text-primary" />
                ) : answer.tier === 'sponsored' ? (
                    <Sparkles aria-hidden className="size-4 text-primary" />
                ) : (
                    <Info
                        aria-hidden
                        className="size-4 text-reos-tone-warning-fg"
                    />
                )}
                {answer.tier === 'content'
                    ? t('ask.fromCourse', { state })
                    : answer.tier === 'sponsored'
                      ? answer.sponsor
                          ? t('ask.beyondSponsored', {
                                sponsor: answer.sponsor,
                            })
                          : t('ask.beyond')
                      : t('ask.notCovered')}
            </p>
            <p className="text-body whitespace-pre-line">{answer.body}</p>

            {source ? (
                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span>{t('ask.source', { lesson: source.lesson })}</span>
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={
                                lessonRoute(source.lesson_id, {
                                    query:
                                        source.seconds !== null
                                            ? { t: source.seconds }
                                            : source.page !== null
                                              ? { page: source.page }
                                              : {},
                                }).url
                            }
                        >
                            <PlayCircle aria-hidden />
                            {source.seconds !== null
                                ? t('ask.watchAt', {
                                      time: clockOf(source.seconds),
                                  })
                                : t('ask.openLesson')}
                        </Link>
                    </Button>
                </div>
            ) : null}

            {answer.quiz ? (
                <fieldset className="space-y-2">
                    <legend className="font-semibold">
                        {answer.quiz.prompt}
                    </legend>
                    {answer.quiz.options.map((option, i) => (
                        <Button
                            key={option}
                            variant={
                                picked === null
                                    ? 'outline'
                                    : i === answer.quiz?.correct
                                      ? 'default'
                                      : picked === i
                                        ? 'destructive'
                                        : 'outline'
                            }
                            className="h-auto w-full justify-start py-3 text-left whitespace-normal"
                            onClick={() => setPicked(i)}
                        >
                            {option}
                        </Button>
                    ))}
                    {picked !== null ? (
                        <p className="text-sm" aria-live="polite">
                            {picked === answer.quiz.correct
                                ? t('learn.ask.right')
                                : t('learn.ask.wrong')}{' '}
                            {answer.quiz.explanation}
                        </p>
                    ) : null}
                </fieldset>
            ) : null}

            <div className="flex flex-wrap gap-2">
                {last && answer.tier !== 'limited' ? (
                    <>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => onFollow('quiz')}
                        >
                            <ListChecks aria-hidden />
                            {t('learn.ask.quiz')}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => onFollow('simpler')}
                        >
                            <Lightbulb aria-hidden />
                            {t('learn.ask.simpler')}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => onFollow('spanish')}
                        >
                            <Languages aria-hidden />
                            {t('learn.ask.spanish')}
                        </Button>
                    </>
                ) : null}
                {answer.tier !== 'limited' ? (
                    <SaveFlashcard
                        source="ask"
                        front={question}
                        back={answer.body}
                    />
                ) : null}
                {answer.tier === 'limited' ? (
                    <Button variant="outline" asChild>
                        <Link href={bookingsIndex().url}>
                            <CalendarPlus aria-hidden />
                            {t('ask.bookInstructor')}
                        </Link>
                    </Button>
                ) : null}
                {answer.math_kind ? (
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                math({ query: { kind: answer.math_kind } }).url
                            }
                        >
                            <Calculator aria-hidden />
                            {t('ask.similar')}
                        </Link>
                    </Button>
                ) : null}
            </div>
        </article>
    );
}
