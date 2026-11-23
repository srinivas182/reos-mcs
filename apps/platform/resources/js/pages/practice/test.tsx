import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Flag,
    Flame,
    Lightbulb,
    Pause,
    Play,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { Calculator } from '@/components/practice/calculator';
import type { Attempt, TestQuestion } from '@/components/practice/types';
import { countdown, isExam } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { postJson, putJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import {
    answer as answerRoute,
    hint as hintRoute,
    pause as pauseRoute,
    submit,
} from '@/routes/practice';

/**
 * A test (candidate web screen 7, A8.3): the timer on the server's clock,
 * the question and options, Previous, Flag and Next, and the question map
 * with answered and flagged. Every click is saved at once. Review before
 * submitting warns once about blanks; when time runs out the test submits
 * itself. Exam mode (the diagnostic) shows no feedback until the report;
 * practice mode (topic quizzes) shows why after each answer. A9.1: the
 * National and Texas portions and the full simulation, one timed portion
 * at a time ("National portion, 1 of 2"), the calculator, and Pause in
 * practice mode; submitting a simulation's first portion starts the next.
 * A9.2b: the smart drill (screen 47) with its streak and Hint, which
 * strikes out one wrong option; a fix plan's retest.
 */
export default function TestPage({ attempt }: { attempt: Attempt }) {
    const { t } = useTranslation();
    const [questions, setQuestions] = useState<TestQuestion[]>(
        attempt.questions,
    );
    const [at, setAt] = useState(() =>
        Math.max(
            0,
            attempt.questions.findIndex((q) => q.choice === null),
        ),
    );
    const [left, setLeft] = useState(attempt.seconds_left);
    const [paused, setPaused] = useState(attempt.paused);
    const [reviewing, setReviewing] = useState(false);
    const [error, setError] = useState(false);
    const [eliminated, setEliminated] = useState<Record<number, number>>({});
    const submitted = useRef(false);
    const question = questions[at];
    const blanks = questions.filter((q) => q.choice === null).length;
    const topicName = attempt.topic
        ? t(`practice.topics.${attempt.topic}`)
        : '';
    const drill = attempt.kind === 'drill';
    // The run of right answers, counting back from the last one answered.
    const answered = questions.filter((q) => q.feedback !== null);
    const streak =
        answered.length -
        1 -
        answered.findLastIndex((q) => q.feedback?.correct !== true);
    const title =
        attempt.kind === 'diagnostic'
            ? t('practice.diagnostic.title')
            : attempt.kind === 'challenge'
              ? t('practice.challenges.testTitle')
              : drill
                ? attempt.topic
                    ? t('practice.drill.topicTitle', { topic: topicName })
                    : t('practice.drill.title')
                : attempt.kind === 'retest'
                  ? t('practice.drill.retestTitle', { topic: topicName })
                  : attempt.kind === 'topic'
                    ? t('practice.topicQuiz', {
                          topic: attempt.topic
                              ? t(`practice.topics.${attempt.topic}`)
                              : '',
                      })
                    : t(
                          `practice.portions.${isExam(attempt.kind) ? attempt.kind : 'simulation'}.title`,
                      );
    const last =
        attempt.section === null ||
        attempt.section.index === attempt.section.of;

    const togglePause = async () => {
        try {
            const next = await postJson<{
                data: { paused: boolean; seconds_left: number | null };
            }>(pauseRoute(attempt.id).url, { paused: !paused });
            setPaused(next.data.paused);
            setLeft(next.data.seconds_left);
        } catch {
            setError(true);
        }
    };

    const finish = () => {
        if (submitted.current) return;
        submitted.current = true;
        router.post(submit(attempt.id).url);
    };

    // The server's clock decides; this only shows it and submits at zero.
    useEffect(() => {
        if (left === null || paused) return;
        const timer = window.setInterval(
            () => setLeft((s) => (s === null ? s : Math.max(0, s - 1))),
            1000,
        );

        return () => window.clearInterval(timer);
    }, [left === null, paused]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (left === 0) finish();
    }, [left]); // eslint-disable-line react-hooks/exhaustive-deps

    const save = async (change: { choice?: number; flagged?: boolean }) => {
        if (!question) return;
        const next = {
            choice: change.choice ?? question.choice,
            flagged: change.flagged ?? question.flagged,
        };
        setQuestions((list) =>
            list.map((q) => (q.id === question.id ? { ...q, ...next } : q)),
        );

        try {
            const saved = await putJson<{ data: TestQuestion }>(
                answerRoute({ attempt: attempt.id, question: question.id }).url,
                next,
            );
            setQuestions((list) =>
                list.map((q) => (q.id === saved.data.id ? saved.data : q)),
            );
            setError(false);
        } catch {
            setError(true);
        }
    };

    const hint = async () => {
        if (!question) return;
        try {
            const got = await postJson<{ data: { eliminated: number } }>(
                hintRoute({ attempt: attempt.id, question: question.id }).url,
                {},
            );
            setEliminated((all) => ({
                ...all,
                [question.id]: got.data.eliminated,
            }));
        } catch {
            setError(true);
        }
    };

    if (!question) return null;
    const ruledOut = eliminated[question.id];

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={title}
                    description={
                        attempt.section
                            ? t('practice.test.section', {
                                  portion: t(
                                      `practice.portions.${attempt.section.portion}.title`,
                                  ),
                                  n: attempt.section.index,
                                  of: attempt.section.of,
                                  count: attempt.total,
                                  mode: t(`practice.modes.${attempt.mode}`),
                              })
                            : t('practice.test.rules', {
                                  count: attempt.total,
                                  mode: t(`practice.modes.${attempt.mode}`),
                              })
                    }
                    actions={
                        <>
                            {left !== null ? (
                                <p
                                    role="timer"
                                    aria-live="off"
                                    aria-label={t('practice.test.timeLeft', {
                                        time: countdown(left),
                                    })}
                                    className={cn(
                                        'rounded-reos-md px-3 py-2 text-lg font-semibold tabular-nums',
                                        left < 300
                                            ? 'bg-reos-tone-warning-bg text-reos-tone-warning-fg'
                                            : 'bg-muted',
                                    )}
                                >
                                    {countdown(left)}
                                </p>
                            ) : null}
                            {attempt.mode === 'practice' && left !== null ? (
                                <Button
                                    variant="outline"
                                    onClick={() => void togglePause()}
                                >
                                    {paused ? (
                                        <Play aria-hidden />
                                    ) : (
                                        <Pause aria-hidden />
                                    )}
                                    {paused
                                        ? t('practice.test.resume')
                                        : t('practice.test.pause')}
                                </Button>
                            ) : null}
                            {drill ? (
                                <p
                                    aria-label={t('practice.drill.streak', {
                                        count: streak,
                                    })}
                                    className="inline-flex items-center gap-2 rounded-reos-md bg-reos-tone-warning-bg px-3 py-2 font-semibold text-reos-tone-warning-fg"
                                >
                                    <Flame aria-hidden className="size-5" />
                                    {streak}
                                </p>
                            ) : null}
                            {attempt.kind !== 'topic' ? <Calculator /> : null}
                            <Button
                                variant="outline"
                                onClick={() => setReviewing(true)}
                            >
                                {t('practice.test.review')}
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <section
                        aria-labelledby="question"
                        className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <p className="text-sm text-muted-foreground">
                            {t('practice.test.counter', {
                                n: question.n,
                                total: attempt.total,
                            })}{' '}
                            · {t(`practice.topics.${question.topic}`)}
                        </p>
                        <h2
                            id="question"
                            className="text-xl font-semibold text-foreground"
                        >
                            {question.prompt}
                        </h2>
                        {paused ? (
                            <p
                                role="status"
                                className="rounded-reos-md bg-muted p-3 text-body"
                            >
                                {t('practice.test.pausedNote')}
                            </p>
                        ) : null}
                        <fieldset
                            className="space-y-2"
                            disabled={question.feedback !== null || paused}
                        >
                            <legend className="sr-only">
                                {question.prompt}
                            </legend>
                            {question.options.map((option, i) => (
                                <label
                                    key={i}
                                    className={cn(
                                        'flex min-h-12 cursor-pointer items-center gap-3 rounded-reos-md border px-3 py-2 text-body',
                                        question.choice === i
                                            ? 'border-primary bg-accent'
                                            : 'border-border hover:bg-muted',
                                        question.feedback &&
                                            question.feedback.right === i &&
                                            'border-reos-tone-success-fg',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name={`q-${question.id}`}
                                        className="size-5 accent-primary"
                                        checked={question.choice === i}
                                        disabled={
                                            ruledOut === i &&
                                            question.feedback === null
                                        }
                                        onChange={() =>
                                            void save({ choice: i })
                                        }
                                    />
                                    {ruledOut === i &&
                                    question.feedback === null ? (
                                        <s>
                                            {option}
                                            <span className="sr-only">
                                                {' '}
                                                ({t('practice.drill.ruledOut')})
                                            </span>
                                        </s>
                                    ) : (
                                        option
                                    )}
                                </label>
                            ))}
                        </fieldset>
                        {(drill || attempt.kind === 'challenge') &&
                        question.feedback === null &&
                        ruledOut === undefined ? (
                            <Button
                                variant="outline"
                                disabled={paused}
                                onClick={() => void hint()}
                            >
                                <Lightbulb aria-hidden />
                                {attempt.kind === 'challenge'
                                    ? t('practice.challenges.hint')
                                    : t('practice.drill.hint')}
                            </Button>
                        ) : null}
                        {ruledOut !== undefined &&
                        question.feedback === null ? (
                            <p
                                role="status"
                                className="text-body text-muted-foreground"
                            >
                                {t('practice.drill.hintGiven', {
                                    option: question.options[ruledOut] ?? '',
                                })}
                            </p>
                        ) : null}
                        {question.feedback ? (
                            <div
                                role="status"
                                className="space-y-1 rounded-reos-md bg-muted p-3 text-body"
                            >
                                <p className="font-semibold">
                                    {question.feedback.correct
                                        ? t('player.checkin.right')
                                        : t('practice.test.wrong', {
                                              answer: question.options[
                                                  question.feedback.right
                                              ],
                                          })}
                                </p>
                                <p>{question.feedback.explanation}</p>
                                <p className="text-sm text-muted-foreground">
                                    {t('player.checkin.source', {
                                        source: question.feedback.source,
                                    })}
                                </p>
                            </div>
                        ) : null}
                        {error ? (
                            <p
                                role="alert"
                                className="text-body text-destructive"
                            >
                                {t('practice.errors.save')}
                            </p>
                        ) : null}
                        <div className="flex flex-wrap gap-3">
                            <Button
                                variant="outline"
                                disabled={at === 0}
                                onClick={() => setAt(at - 1)}
                            >
                                <ArrowLeft aria-hidden />
                                {t('practice.test.previous')}
                            </Button>
                            <Button
                                variant="outline"
                                aria-pressed={question.flagged}
                                onClick={() =>
                                    void save({ flagged: !question.flagged })
                                }
                            >
                                <Flag aria-hidden />
                                {question.flagged
                                    ? t('practice.test.unflag')
                                    : t('practice.test.flag')}
                            </Button>
                            {at < questions.length - 1 ? (
                                <Button onClick={() => setAt(at + 1)}>
                                    {t('practice.test.next')}
                                    <ArrowRight aria-hidden />
                                </Button>
                            ) : (
                                <Button onClick={() => setReviewing(true)}>
                                    {t('practice.test.review')}
                                </Button>
                            )}
                        </div>
                    </section>

                    <QuestionMap
                        questions={questions}
                        current={at}
                        onOpen={(i) => {
                            setAt(i);
                            setReviewing(false);
                        }}
                    />
                </div>
            </div>

            <Dialog open={reviewing} onOpenChange={setReviewing}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {t('practice.test.reviewTitle')}
                        </DialogTitle>
                        <DialogDescription>
                            {blanks > 0
                                ? t('practice.test.blanks', { count: blanks })
                                : t('practice.test.allAnswered')}
                            {left !== null
                                ? ` ${t('practice.test.timeLeft', { time: countdown(left) })}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    <QuestionMap
                        questions={questions}
                        current={at}
                        onOpen={(i) => {
                            setAt(i);
                            setReviewing(false);
                        }}
                    />
                    <DialogFooter>
                        {blanks > 0 ? (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setAt(
                                        questions.findIndex(
                                            (q) => q.choice === null,
                                        ),
                                    );
                                    setReviewing(false);
                                }}
                            >
                                {t('practice.test.firstBlank')}
                            </Button>
                        ) : null}
                        <Button onClick={finish}>
                            {!last
                                ? t('practice.test.nextPortion')
                                : blanks > 0
                                  ? t('practice.test.submitAnyway')
                                  : t('practice.test.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Every question: answered, flagged, blank and current (screens 41–42). */
function QuestionMap({
    questions,
    current,
    onOpen,
}: {
    questions: TestQuestion[];
    current: number;
    onOpen: (index: number) => void;
}) {
    const { t } = useTranslation();

    return (
        <nav
            aria-label={t('practice.test.map')}
            className="rounded-reos-lg border border-border bg-card p-4"
        >
            <ol className="grid grid-cols-5 gap-2 sm:grid-cols-8 lg:grid-cols-5">
                {questions.map((q, i) => (
                    <li key={q.id}>
                        <button
                            type="button"
                            onClick={() => onOpen(i)}
                            aria-current={i === current ? 'step' : undefined}
                            aria-label={t('practice.test.mapItem', {
                                n: q.n,
                                state: [
                                    q.choice !== null
                                        ? t('practice.test.answered')
                                        : t('practice.test.blank'),
                                    q.flagged
                                        ? t('practice.test.flagged')
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(', '),
                            })}
                            className={cn(
                                'relative flex size-12 items-center justify-center rounded-reos-md border text-base font-semibold tabular-nums focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                q.choice !== null
                                    ? 'border-primary bg-reos-action-tint'
                                    : 'border-border bg-background',
                                i === current && 'ring-2 ring-primary',
                            )}
                        >
                            {q.n}
                            {q.flagged ? (
                                <Flag
                                    aria-hidden
                                    className="absolute top-0.5 right-0.5 size-3 text-reos-tone-warning-fg"
                                />
                            ) : null}
                        </button>
                    </li>
                ))}
            </ol>
        </nav>
    );
}
