import { Link, router } from '@inertiajs/react';
import {
    BookOpen,
    CircleCheck,
    ClipboardCheck,
    ListChecks,
    RotateCcw,
    Target,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { lesson as openLesson } from '@/routes/learn';
import {
    attempt as openAttempt,
    readiness,
    results as openResults,
} from '@/routes/practice';
import { continueMethod as continuePlan } from '@/routes/practice/plans';
import type { TopicPlan } from './types';

const icons: Record<TopicPlan['steps'][number]['kind'], LucideIcon> = {
    lesson: BookOpen,
    review: ListChecks,
    practice: Target,
    flashcards: RotateCcw,
    retest: ClipboardCheck,
};

/**
 * Fix a weak topic (screen 46, A9.2b): now against the goal, 4 to 6 steps
 * with their minutes, Continue to the next, a done step to review it, and
 * the misses with why.
 */
export function FixPlan({
    plan,
    showMisses = false,
}: {
    plan: TopicPlan;
    showMisses?: boolean;
}) {
    const { t } = useTranslation();
    const topic = t(`practice.topics.${plan.topic}`);

    return (
        <div className="space-y-6">
            <section className="flex flex-wrap items-center justify-between gap-4 rounded-reos-lg border border-border bg-card p-5">
                <div className="space-y-1">
                    <p className="text-2xl font-semibold tabular-nums">
                        {plan.now === null
                            ? t('practice.fix.notYet')
                            : t('practice.fix.now', { percent: plan.now })}
                    </p>
                    <p className="text-body text-muted-foreground">
                        {t('practice.fix.goal', { percent: plan.goal })}
                        {plan.start !== null
                            ? ` · ${t('practice.fix.started', { percent: plan.start })}`
                            : ''}
                    </p>
                </div>
                <div
                    role="progressbar"
                    aria-label={t('practice.fix.goal', { percent: plan.goal })}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={plan.now ?? 0}
                    className="relative h-3 w-full max-w-sm overflow-hidden rounded-full bg-muted"
                >
                    <div
                        className="h-full rounded-full bg-primary"
                        style={{ width: `${plan.now ?? 0}%` }}
                    />
                    <span
                        aria-hidden
                        className="absolute inset-y-0 w-0.5 bg-foreground/50"
                        style={{ left: `${plan.goal}%` }}
                    />
                </div>
            </section>

            {plan.completed ? (
                <section
                    role="status"
                    className="space-y-3 rounded-reos-lg border border-border bg-reos-tone-success-bg p-5"
                >
                    <h2 className="text-lg font-semibold">
                        {t('practice.fix.completedTitle')}
                    </h2>
                    <p className="text-body">
                        {plan.weak
                            ? t('practice.fix.completedWeak', {
                                  goal: plan.goal,
                              })
                            : t('practice.fix.completedStrong', { topic })}
                    </p>
                    <Button asChild variant="outline">
                        <Link href={readiness()}>
                            {t('practice.readinessMap.title')}
                        </Link>
                    </Button>
                </section>
            ) : (
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        onClick={() => router.post(continuePlan(plan.id).url)}
                    >
                        {t('practice.fix.continue')}
                    </Button>
                    <p className="text-body text-muted-foreground">
                        {t('practice.fix.left', { count: plan.minutes })}
                    </p>
                </div>
            )}

            <ol className="space-y-3">
                {plan.steps.map((step) => {
                    const Icon =
                        step.status === 'done' ? CircleCheck : icons[step.kind];
                    const label =
                        step.kind === 'lesson' && step.lesson
                            ? t('practice.fix.steps.lesson', {
                                  title: step.lesson.title,
                              })
                            : t(`practice.fix.steps.${step.kind}`);

                    return (
                        <li
                            key={step.n}
                            className={cn(
                                'flex flex-wrap items-center gap-4 rounded-reos-lg border bg-card p-4',
                                step.status === 'current'
                                    ? 'border-primary'
                                    : 'border-border',
                            )}
                        >
                            <Icon
                                aria-hidden
                                className={cn(
                                    'size-6',
                                    step.status === 'done'
                                        ? 'text-reos-tone-success-fg'
                                        : 'text-primary',
                                )}
                            />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{label}</p>
                                <p className="text-sm text-muted-foreground">
                                    {t('practice.fix.minutes', {
                                        count: step.minutes,
                                    })}{' '}
                                    · {t(`practice.fix.status.${step.status}`)}
                                </p>
                            </div>
                            {step.status === 'done' ? (
                                <Review step={step} />
                            ) : null}
                        </li>
                    );
                })}
            </ol>

            {plan.misses.length > 0 ? (
                <details
                    open={showMisses}
                    className="rounded-reos-lg border border-border bg-card p-5"
                >
                    <summary className="min-h-touch cursor-pointer content-center text-lg font-semibold">
                        {t('practice.fix.missesTitle')}
                    </summary>
                    <ol className="mt-3 space-y-4">
                        {plan.misses.map((m) => (
                            <li key={m.id} className="space-y-1">
                                <p className="font-medium">{m.prompt}</p>
                                <p className="text-body">
                                    {t('practice.fix.answer', {
                                        answer: m.options[m.right] ?? '',
                                    })}
                                </p>
                                <p className="text-body text-muted-foreground">
                                    {m.explanation}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {t('player.checkin.source', {
                                        source: m.source,
                                    })}
                                </p>
                            </li>
                        ))}
                    </ol>
                </details>
            ) : null}
        </div>
    );
}

/** A done step can be opened again: its lesson, or its results. */
function Review({ step }: { step: TopicPlan['steps'][number] }) {
    const { t } = useTranslation();

    if (step.lesson) {
        return (
            <Button asChild variant="outline">
                <Link
                    href={openLesson(step.lesson.id, {
                        query:
                            step.lesson.seconds !== null
                                ? { t: step.lesson.seconds }
                                : {},
                    })}
                >
                    {t('practice.fix.review')}
                </Link>
            </Button>
        );
    }

    if (step.results_id !== null) {
        return (
            <Button asChild variant="outline">
                <Link href={openResults(step.results_id)}>
                    {t('practice.fix.seeResults')}
                </Link>
            </Button>
        );
    }

    return step.attempt_id !== null ? (
        <Button asChild variant="outline">
            <Link href={openAttempt(step.attempt_id)}>
                {t('practice.fix.review')}
            </Link>
        </Button>
    ) : null;
}
