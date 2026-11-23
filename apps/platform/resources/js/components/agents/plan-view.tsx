import { Link } from '@inertiajs/react';
import {
    CircleCheck,
    Circle,
    HandHeart,
    PartyPopper,
    Repeat,
    Sparkles,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { planHref } from './links';
import type { AgentPlan, PlanTask, ScoreMetric } from './types';

const METRICS: ScoreMetric[] = [
    'conversations',
    'appointments',
    'clients',
    'ce_hours',
];

/**
 * The 90-day plan (A11.1b; agent screen 10): where the agent is in the
 * thirteen weeks, the phases, the weekly targets and this week's tasks to
 * tick off. Launch carries the First-Closing Promise.
 */
export function PlanView({
    plan,
    onTick,
    onPromise,
}: {
    plan: AgentPlan;
    onTick: (task: PlanTask, done: boolean) => void;
    onPromise: () => void;
}) {
    const { t } = useTranslation();
    const current = plan.phases.find((p) => p.current);
    const percent =
        plan.total > 0 ? Math.round((plan.done / plan.total) * 100) : 0;

    return (
        <div className="space-y-6">
            <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="text-xl font-semibold">
                        {t('agents.plan.week', {
                            week: plan.week,
                            weeks: plan.weeks,
                        })}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('agents.plan.progress', {
                            done: plan.done,
                            total: plan.total,
                        })}
                    </p>
                </div>
                <div
                    role="progressbar"
                    aria-label={t('agents.plan.progress', {
                        done: plan.done,
                        total: plan.total,
                    })}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={percent}
                    className="h-3 overflow-hidden rounded-reos-pill bg-muted"
                >
                    <div
                        className="h-full bg-primary"
                        style={{ width: `${percent}%` }}
                    />
                </div>
                <ol
                    aria-label={t('agents.plan.phasesLabel')}
                    className="grid gap-2 sm:grid-cols-4"
                >
                    {plan.phases.map((p) => (
                        <li
                            key={p.code}
                            aria-current={p.current ? 'step' : undefined}
                            className={cn(
                                'rounded-reos-md border p-3',
                                p.current
                                    ? 'border-primary bg-muted'
                                    : 'border-border',
                            )}
                        >
                            <p className="text-base font-semibold">
                                {t(`agents.plan.phases.${p.code}`)}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('agents.plan.phaseWeeks', {
                                    from: p.from,
                                    to: p.to,
                                })}
                            </p>
                        </li>
                    ))}
                </ol>
            </section>

            {plan.goal === 'launch' ? (
                <section className="flex flex-wrap items-center justify-between gap-4 rounded-reos-lg border border-border bg-card p-5">
                    <div className="space-y-1">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <HandHeart aria-hidden className="size-5" />
                            {t('agents.plan.promise.title')}
                        </h2>
                        <p className="text-base text-muted-foreground">
                            {t('agents.plan.promise.body')}
                        </p>
                    </div>
                    {plan.promised ? (
                        <p className="flex items-center gap-2 text-base font-semibold">
                            <PartyPopper aria-hidden className="size-5" />
                            {t('agents.plan.promise.made')}
                        </p>
                    ) : (
                        <Button onClick={onPromise}>
                            {t('agents.plan.promise.action')}
                        </Button>
                    )}
                </section>
            ) : null}

            <section aria-labelledby="plan-week" className="space-y-3">
                <h2 id="plan-week" className="text-lg font-semibold">
                    {t('agents.plan.thisWeek')}
                    {current ? (
                        <span className="ml-2 font-normal text-muted-foreground">
                            ·{' '}
                            {t('agents.plan.phase', {
                                phase: t(`agents.plan.phases.${current.code}`),
                            })}
                        </span>
                    ) : null}
                </h2>
                {plan.tasks.length === 0 ||
                plan.tasks.every((task) => task.done) ? (
                    <EmptyState
                        icon={CircleCheck}
                        title={t('agents.plan.emptyTitle')}
                        description={t('agents.plan.emptyBody')}
                    />
                ) : null}
                {plan.tasks.length > 0 ? (
                    <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                        {plan.tasks.map((task) => {
                            const href = planHref(task.link);
                            const name = t(`agents.plan.tasks.${task.code}`);
                            return (
                                <li
                                    key={`${task.code}-${task.week}`}
                                    className="flex flex-wrap items-center gap-3 p-4"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-12"
                                        aria-pressed={task.done}
                                        aria-label={`${t('agents.plan.done')}: ${name}`}
                                        disabled={task.auto}
                                        onClick={() => onTick(task, !task.done)}
                                    >
                                        {task.done ? (
                                            <CircleCheck
                                                aria-hidden
                                                className="size-6 text-primary"
                                            />
                                        ) : (
                                            <Circle
                                                aria-hidden
                                                className="size-6"
                                            />
                                        )}
                                    </Button>
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className={cn(
                                                'text-base font-semibold',
                                                task.done &&
                                                    'text-muted-foreground line-through',
                                            )}
                                        >
                                            {name}
                                        </p>
                                        <p className="flex flex-wrap gap-3 text-sm text-muted-foreground">
                                            {task.repeats ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <Repeat
                                                        aria-hidden
                                                        className="size-4"
                                                    />
                                                    {t('agents.plan.repeats')}
                                                </span>
                                            ) : null}
                                            {task.auto ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <Sparkles
                                                        aria-hidden
                                                        className="size-4"
                                                    />
                                                    {t('agents.plan.auto')}
                                                </span>
                                            ) : null}
                                        </p>
                                    </div>
                                    {href && !task.done ? (
                                        <Button asChild variant="outline">
                                            <Link href={href}>
                                                {t('agents.plan.open')}
                                            </Link>
                                        </Button>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                ) : null}
            </section>

            <section aria-labelledby="plan-targets" className="space-y-3">
                <h2 id="plan-targets" className="text-lg font-semibold">
                    {t('agents.plan.targets')}
                </h2>
                <dl className="grid gap-3 sm:grid-cols-4">
                    {METRICS.map((m) => (
                        <div
                            key={m}
                            className="rounded-reos-lg border border-border bg-card p-4"
                        >
                            <dt className="text-base text-muted-foreground">
                                {t(`agents.scorecard.metrics.${m}`)}
                            </dt>
                            <dd className="text-2xl font-semibold">
                                {plan.targets[m] ?? 0}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>
        </div>
    );
}
