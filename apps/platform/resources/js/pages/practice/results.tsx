import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    MessageCircleQuestion,
    PlayCircle,
    Shuffle,
    Sigma,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { show as challengeShow } from '@/routes/challenges';
import { PortionScores } from '@/components/practice/portion-scores';
import { TopicBars } from '@/components/practice/readiness-ring';
import { SaveFlashcard } from '@/components/practice/save-flashcard';
import type { ReadinessSummary, Results } from '@/components/practice/types';
import { countdown } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { lesson as openLesson } from '@/routes/learn';
import {
    index as practiceIndex,
    math as mathLab,
    similar as similarRoute,
    why as whyRoute,
} from '@/routes/practice';
import { show as studyPlan } from '@/routes/study-plan';

/**
 * A test's report (candidate web screen 8, A8.3): score and pass line, the
 * change since last time, topics weakest first with their exam weight, next
 * steps, and every answer with why and its source. The study plan has
 * already been rebuilt from this result.
 */
type SimilarQuestion = {
    id: number;
    topic: string;
    prompt: string;
    options: string[];
    right: number;
    explanation: string;
    source: string;
};

export default function ResultsPage({
    results,
    readiness,
}: {
    results: Results;
    readiness: ReadinessSummary;
}) {
    const { t } = useTranslation();
    const passed = results.passed ?? results.score >= results.pass_line;
    const [noWhy, setNoWhy] = useState<Record<number, boolean>>({});
    // Ask why (A9.2e): the lesson that teaches it, with the question asked.
    const askWhy = async (id: number) => {
        const response = await fetch(
            whyRoute({ attempt: results.id, question: id }).url,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
        const body = (await response.json().catch(() => ({ data: null }))) as {
            data: {
                lesson_id: number;
                seconds: number | null;
                question: string;
            } | null;
        };
        if (!body.data) {
            setNoWhy((all) => ({ ...all, [id]: true }));

            return;
        }
        router.visit(
            openLesson(body.data.lesson_id, {
                query: {
                    ask: body.data.question,
                    ...(body.data.seconds !== null
                        ? { t: body.data.seconds }
                        : {}),
                },
            }).url,
        );
    };
    const [similar, setSimilar] = useState<
        Record<number, SimilarQuestion | null>
    >({});
    const [revealed, setRevealed] = useState<Record<number, boolean>>({});

    const more = async (id: number) => {
        const response = await fetch(
            similarRoute({ attempt: results.id, question: id }).url,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
        const body = (await response.json().catch(() => ({ data: null }))) as {
            data: SimilarQuestion | null;
        };
        setSimilar({ ...similar, [id]: body.data });
    };
    const change =
        results.previous === null ? null : results.score - results.previous;

    return (
        <>
            <Head title={t('practice.results.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.results.title')}
                    description={
                        results.kind === 'diagnostic'
                            ? t('practice.results.diagnosticPurpose')
                            : t('practice.results.purpose')
                    }
                    back={
                        <Link
                            href={practiceIndex()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('practice.title')}
                        </Link>
                    }
                    actions={
                        <>
                            {results.challenge ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={challengeShow(results.challenge)}
                                    >
                                        {t('practice.challenges.board')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button asChild>
                                <Link href={studyPlan()}>
                                    {results.weakest.length > 0
                                        ? t('practice.results.fixWeakest')
                                        : t('practice.results.seePlan')}
                                </Link>
                            </Button>
                        </>
                    }
                />

                {results.portions.length > 0 ? (
                    <PortionScores
                        portions={results.portions}
                        timing={results.timing}
                    />
                ) : null}

                <section className="grid gap-4 rounded-reos-lg border border-border bg-card p-5 sm:grid-cols-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {t('practice.results.score')}
                        </p>
                        <p className="text-3xl font-semibold tabular-nums">
                            {`${results.score}%`}
                        </p>
                        <p
                            className={cn(
                                'text-body font-medium',
                                passed
                                    ? 'text-reos-tone-success-fg'
                                    : 'text-reos-tone-warning-fg',
                            )}
                        >
                            {passed
                                ? t('practice.results.above', {
                                      line: results.pass_line,
                                  })
                                : t('practice.results.below', {
                                      line: results.pass_line,
                                  })}
                        </p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {t('practice.results.right')}
                        </p>
                        <p className="text-3xl font-semibold tabular-nums">
                            {`${results.correct}/${results.total}`}
                        </p>
                        {change !== null ? (
                            <p className="text-body text-muted-foreground">
                                {t('practice.results.change', {
                                    change: change > 0 ? `+${change}` : change,
                                })}
                            </p>
                        ) : null}
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {t('practice.results.time')}
                        </p>
                        <p className="text-3xl font-semibold tabular-nums">
                            {countdown(results.seconds)}
                        </p>
                        {readiness.percent !== null ? (
                            <p className="text-body text-muted-foreground">
                                {t('practice.readiness.label', {
                                    percent: readiness.percent,
                                })}
                            </p>
                        ) : null}
                    </div>
                </section>

                <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <h2 className="text-lg font-semibold">
                        {t('practice.results.byTopic')}
                    </h2>
                    <TopicBars
                        topics={results.topics}
                        passLine={results.pass_line}
                    />
                    {results.weakest.length > 0 ? (
                        <p className="text-body">
                            {t('practice.results.next', {
                                topics: results.weakest
                                    .map((x) => t(`practice.topics.${x}`))
                                    .join(', '),
                            })}
                        </p>
                    ) : null}
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('practice.results.review')}
                    </h2>
                    <ol className="space-y-3">
                        {results.review.map((q) => (
                            <li
                                key={q.id}
                                className="space-y-2 rounded-reos-lg border border-border bg-card p-4"
                            >
                                <p className="flex items-start gap-2 font-medium">
                                    {q.correct ? (
                                        <CheckCircle2
                                            aria-label={t(
                                                'practice.results.gotRight',
                                            )}
                                            className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                                        />
                                    ) : (
                                        <XCircle
                                            aria-label={t(
                                                'practice.results.gotWrong',
                                            )}
                                            className="mt-0.5 size-5 shrink-0 text-reos-tone-danger-fg"
                                        />
                                    )}
                                    <span>{`${q.n}. ${q.prompt}`}</span>
                                </p>
                                {!q.scored ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('practice.results.pretest')}
                                    </p>
                                ) : null}
                                <p className="text-body">
                                    {q.choice === null
                                        ? t('practice.results.blank')
                                        : t('practice.results.yours', {
                                              answer: q.options[q.choice],
                                          })}
                                    {q.correct
                                        ? ''
                                        : ` · ${t('practice.results.rightAnswer', { answer: q.options[q.right] })}`}
                                </p>
                                <p className="text-body text-muted-foreground">
                                    {q.explanation}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {t('player.checkin.source', {
                                        source: q.source,
                                    })}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {q.lesson ? (
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={openLesson(q.lesson.id, {
                                                    query:
                                                        q.lesson.seconds ===
                                                        null
                                                            ? {}
                                                            : {
                                                                  t: q.lesson
                                                                      .seconds,
                                                              },
                                                })}
                                            >
                                                <PlayCircle aria-hidden />
                                                {t('practice.results.watch')}
                                            </Link>
                                        </Button>
                                    ) : null}
                                    <SaveFlashcard
                                        source="review"
                                        front={q.prompt}
                                        back={`${q.options[q.right] ?? ''}. ${q.explanation}`}
                                        questionId={q.id}
                                    />
                                    {!q.correct && !noWhy[q.id] ? (
                                        <Button
                                            variant="outline"
                                            onClick={() => void askWhy(q.id)}
                                        >
                                            <MessageCircleQuestion
                                                aria-hidden
                                            />
                                            {t('practice.results.askWhy')}
                                        </Button>
                                    ) : null}
                                    {noWhy[q.id] ? (
                                        <p className="text-sm text-muted-foreground">
                                            {t('practice.results.noWhy')}
                                        </p>
                                    ) : null}
                                    {!q.correct && q.math ? (
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={mathLab({
                                                    query: { kind: q.math },
                                                })}
                                            >
                                                <Sigma aria-hidden />
                                                {t('practice.math.practise')}
                                            </Link>
                                        </Button>
                                    ) : null}
                                    {!q.correct &&
                                    similar[q.id] === undefined ? (
                                        <Button
                                            variant="outline"
                                            onClick={() => void more(q.id)}
                                        >
                                            <Shuffle aria-hidden />
                                            {t('practice.results.similar')}
                                        </Button>
                                    ) : null}
                                </div>
                                {similar[q.id] === null ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('practice.results.noSimilar')}
                                    </p>
                                ) : null}
                                {similar[q.id] ? (
                                    <div className="space-y-2 rounded-reos-md bg-muted p-3">
                                        <p className="font-medium">
                                            {similar[q.id]?.prompt}
                                        </p>
                                        <ol className="list-[upper-alpha] space-y-1 pl-6 text-body">
                                            {similar[q.id]?.options.map(
                                                (option, i) => (
                                                    <li
                                                        key={option}
                                                        className={
                                                            revealed[q.id] &&
                                                            i ===
                                                                similar[q.id]
                                                                    ?.right
                                                                ? 'font-semibold text-reos-tone-success-fg'
                                                                : undefined
                                                        }
                                                    >
                                                        {option}
                                                    </li>
                                                ),
                                            )}
                                        </ol>
                                        {revealed[q.id] ? (
                                            <p className="text-body">
                                                {similar[q.id]?.explanation}
                                            </p>
                                        ) : (
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    setRevealed({
                                                        ...revealed,
                                                        [q.id]: true,
                                                    })
                                                }
                                            >
                                                {t(
                                                    'practice.results.showAnswer',
                                                )}
                                            </Button>
                                        )}
                                    </div>
                                ) : null}
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </>
    );
}
