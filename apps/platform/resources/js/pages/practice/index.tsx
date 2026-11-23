import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, GraduationCap, Target } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { CoachPanel } from '@/components/practice/coach';
import { ReadinessRing, TopicBars } from '@/components/practice/readiness-ring';
import { TestIntroDialog } from '@/components/practice/test-intro-dialog';
import type {
    Coach,
    HubTest,
    ReadinessSummary,
    Topic,
} from '@/components/practice/types';
import { isExam } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import {
    attempt as openAttempt,
    readiness as readinessPage,
    results as openResults,
    start,
} from '@/routes/practice';

/**
 * Practice and exams (candidate web nav, A8.3): the 45-minute diagnostic and
 * a 10-question quiz per topic, each with the best and last score, and
 * readiness by topic; and (A9.1) the National and Texas portion tests and
 * the full simulation in the real exam's format, started from the test
 * intro (Exam or Practice mode).
 */
export default function PracticeIndex({
    tests,
    readiness,
    weights,
    coach,
}: {
    tests: HubTest[];
    readiness: ReadinessSummary;
    weights: Record<Topic, number>;
    coach?: Coach;
}) {
    const { t } = useTranslation();
    const diagnostic = tests.find((x) => x.kind === 'diagnostic');
    const topics = tests.filter((x) => x.kind === 'topic');
    const exams = tests.flatMap((x) =>
        isExam(x.kind) ? [{ test: x, kind: x.kind }] : [],
    );
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

                {coach ? <CoachPanel coach={coach} /> : null}

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

                {exams.length > 0 ? (
                    <section
                        id="simulations"
                        aria-labelledby="exams-title"
                        className="space-y-3"
                    >
                        <h2 id="exams-title" className="text-lg font-semibold">
                            {t('practice.portions.title')}
                        </h2>
                        <ul className="grid gap-3 md:grid-cols-3">
                            {exams.map(({ test, kind }) => (
                                <li
                                    key={kind}
                                    className="flex flex-col justify-between gap-3 rounded-reos-lg border border-border bg-card p-4"
                                >
                                    <div className="space-y-1">
                                        <h3 className="flex items-center gap-2 font-semibold">
                                            <GraduationCap
                                                aria-hidden
                                                className="size-5 text-primary"
                                            />
                                            {t(
                                                `practice.portions.${kind}.title`,
                                            )}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {t('practice.intro.format', {
                                                questions: test.questions,
                                                minutes: test.minutes ?? 0,
                                            })}
                                        </p>
                                        {test.best !== null ? (
                                            <p className="text-body">
                                                {t('practice.scores', {
                                                    best: test.best,
                                                    last: test.last,
                                                })}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex flex-wrap gap-3">
                                        {test.last_attempt !== null ? (
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={openResults(
                                                        test.last_attempt,
                                                    )}
                                                >
                                                    {t('practice.seeResults')}
                                                </Link>
                                            </Button>
                                        ) : null}
                                        {test.open !== null ? (
                                            <Button onClick={() => begin(test)}>
                                                {t('practice.carryOn')}
                                            </Button>
                                        ) : (
                                            <TestIntroDialog
                                                test={test}
                                                kind={kind}
                                                label={t('practice.start')}
                                            />
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
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
                        <Link
                            href={readinessPage()}
                            className="inline-flex rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <ReadinessRing percent={readiness.percent} />
                        </Link>
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
