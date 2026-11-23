import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { TopicBars } from '@/components/practice/readiness-ring';
import type { ReadinessSummary, Results } from '@/components/practice/types';
import { countdown } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as practiceIndex } from '@/routes/practice';
import { show as studyPlan } from '@/routes/study-plan';

/**
 * A test's report (candidate web screen 8, A8.3): score and pass line, the
 * change since last time, topics weakest first with their exam weight, next
 * steps, and every answer with why and its source. The study plan has
 * already been rebuilt from this result.
 */
export default function ResultsPage({
    results,
    readiness,
}: {
    results: Results;
    readiness: ReadinessSummary;
}) {
    const { t } = useTranslation();
    const passed = results.score >= results.pass_line;
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
                        <Button asChild>
                            <Link href={studyPlan()}>
                                {results.weakest.length > 0
                                    ? t('practice.results.fixWeakest')
                                    : t('practice.results.seePlan')}
                            </Link>
                        </Button>
                    }
                />

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
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </>
    );
}
