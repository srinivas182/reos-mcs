import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Target } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { ReadinessRing, TopicBars } from '@/components/practice/readiness-ring';
import type {
    HubTest,
    ReadinessSummary,
    Topic,
} from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import {
    attempt as openAttempt,
    results as openResults,
    start,
} from '@/routes/practice';

/**
 * Practice and exams (candidate web nav, A8.3): the 45-minute diagnostic and
 * a 10-question quiz per topic, each with the best and last score, and
 * readiness by topic. Portion tests and the full simulation join in A9.1.
 */
export default function PracticeIndex({
    tests,
    readiness,
    weights,
}: {
    tests: HubTest[];
    readiness: ReadinessSummary;
    weights: Record<Topic, number>;
}) {
    const { t } = useTranslation();
    const diagnostic = tests.find((x) => x.kind === 'diagnostic');
    const topics = tests.filter((x) => x.kind === 'topic');
    const begin = (test: HubTest) =>
        test.open !== null
            ? router.visit(openAttempt(test.open).url)
            : router.post(start().url, {
                  kind: test.kind,
                  topic: test.topic,
              });

    return (
        <>
            <Head title={t('practice.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.title')}
                    description={t('practice.description')}
                />

                {diagnostic ? (
                    <section className="grid gap-4 rounded-reos-lg border border-border bg-card p-5 md:grid-cols-[1fr_auto] md:items-center">
                        <div className="space-y-2">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <ClipboardCheck
                                    aria-hidden
                                    className="size-5 text-primary"
                                />
                                {t('practice.diagnostic.title')}
                            </h2>
                            <p className="text-body text-muted-foreground">
                                {t('practice.diagnostic.description', {
                                    questions: diagnostic.questions,
                                    minutes: diagnostic.minutes,
                                })}
                            </p>
                            {diagnostic.best !== null ? (
                                <p className="text-body">
                                    {t('practice.scores', {
                                        best: diagnostic.best,
                                        last: diagnostic.last,
                                    })}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap gap-3">
                            {diagnostic.last_attempt !== null ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={openResults(
                                            diagnostic.last_attempt,
                                        )}
                                    >
                                        {t('practice.seeResults')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button onClick={() => begin(diagnostic)}>
                                {diagnostic.open !== null
                                    ? t('practice.carryOn')
                                    : diagnostic.best !== null
                                      ? t('practice.diagnostic.again')
                                      : t('practice.diagnostic.start')}
                            </Button>
                        </div>
                    </section>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            {t('practice.topicQuizzes')}
                        </h2>
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {topics.map((test) => (
                                <li
                                    key={test.topic}
                                    className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
                                >
                                    <div className="space-y-1">
                                        <h3 className="flex items-center gap-2 font-semibold">
                                            <Target
                                                aria-hidden
                                                className="size-5 text-primary"
                                            />
                                            {test.topic
                                                ? t(
                                                      `practice.topics.${test.topic}`,
                                                  )
                                                : null}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                t('practice.questions', {
                                                    count: test.questions,
                                                }),
                                                test.topic
                                                    ? t(
                                                          'practice.results.weight',
                                                          {
                                                              count: weights[
                                                                  test.topic
                                                              ],
                                                          },
                                                      )
                                                    : null,
                                                test.best !== null
                                                    ? t('practice.best', {
                                                          best: test.best,
                                                      })
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        onClick={() => begin(test)}
                                        aria-label={t(
                                            test.open !== null
                                                ? 'practice.carryOnTopic'
                                                : 'practice.startTopic',
                                            {
                                                topic: test.topic
                                                    ? t(
                                                          `practice.topics.${test.topic}`,
                                                      )
                                                    : '',
                                            },
                                        )}
                                    >
                                        {test.open !== null
                                            ? t('practice.carryOn')
                                            : t('practice.start')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>
                    <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                        <ReadinessRing percent={readiness.percent} />
                        <TopicBars
                            topics={(
                                Object.entries(readiness.topics) as [
                                    Topic,
                                    number | null,
                                ][]
                            ).map(([topic, percent]) => ({ topic, percent }))}
                        />
                    </section>
                </div>
            </div>
        </>
    );
}
