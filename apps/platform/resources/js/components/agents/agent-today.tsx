import { Link } from '@inertiajs/react';
import { Check, CircleCheck, ListChecks, Sun, Sunrise } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { planHref } from './links';
import type { AgentTodayData, TodayItem, TodayReason } from './types';

const TONE: Record<TodayReason, string> = {
    overdue: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
    deadline: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    appointment: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    follow_up: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    plan: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
};

/**
 * Today for agents (A11.1b; agent screens 14 and 15, on the dashboard,
 * screen 12): the mode's stats, then one ranked list from every tool, each
 * item naming its source. Done is done at the source where the tool allows
 * it, with Undo. Until there are 20 contacts or a connected tool, three
 * first steps take the list's place.
 */
export function AgentToday({
    today,
    planUrl,
    briefUrl,
    onDone,
}: {
    today: AgentTodayData;
    planUrl: string;
    /** The morning brief (A11.1c). */
    briefUrl?: string;
    /** Marks an item done (true) or not done (false) at its source. */
    onDone: (item: TodayItem, done: boolean) => Promise<unknown>;
}) {
    const { t, i18n } = useTranslation();
    const [hidden, setHidden] = useState<string[]>([]);
    const items = today.items.filter((i) => !hidden.includes(i.key));
    const time = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, { timeStyle: 'short' }).format(
            new Date(iso),
        );
    const when = (item: TodayItem) => {
        if (item.at === null) return null;
        if (item.all_day || item.reason === 'overdue') {
            return new Intl.DateTimeFormat(i18n.language, {
                month: 'short',
                day: 'numeric',
            }).format(new Date(item.at));
        }
        return time(item.at);
    };
    const done = (item: TodayItem) => {
        setHidden((h) => [...h, item.key]);
        void onDone(item, true).catch(() =>
            setHidden((h) => h.filter((k) => k !== item.key)),
        );
        undoToast({
            message: t('agents.plan.ticked'),
            onUndo: async () => {
                await onDone(item, false);
                setHidden((h) => h.filter((k) => k !== item.key));
            },
        });
    };

    return (
        <section aria-labelledby="agent-today" className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="agent-today" className="text-lg font-semibold">
                    {t('agents.today.title')}
                </h2>
                <div className="flex flex-wrap gap-4">
                    <Link
                        href={planUrl}
                        className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary underline-offset-4 hover:underline"
                    >
                        <ListChecks aria-hidden className="size-5" />
                        {t('agents.plan.title')}
                    </Link>
                    {briefUrl ? (
                        <Link
                            href={briefUrl}
                            className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <Sunrise aria-hidden className="size-5" />
                            {t('agents.brief.title')}
                        </Link>
                    ) : null}
                </div>
            </div>
            <dl className="grid gap-3 sm:grid-cols-3">
                {today.stats.map((s) => (
                    <div
                        key={s.key}
                        className="rounded-reos-lg border border-border bg-card p-4"
                    >
                        <dt className="text-base text-muted-foreground">
                            {t(`agents.today.stats.${s.key}`)}
                        </dt>
                        <dd className="text-2xl font-semibold">
                            {s.target === null
                                ? s.value
                                : t('agents.today.statOf', {
                                      value: s.value,
                                      target: s.target,
                                  })}
                        </dd>
                    </div>
                ))}
            </dl>
            {today.day_one ? (
                <div className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <h3 className="flex items-center gap-2 text-lg font-semibold">
                        <Sun aria-hidden className="size-5" />
                        {t('agents.today.dayOne.title')}
                    </h3>
                    <p className="text-base text-muted-foreground">
                        {t('agents.today.dayOne.body')}
                    </p>
                    <ol className="space-y-2">
                        {today.day_one.map((step) => (
                            <li
                                key={step.code}
                                className="flex flex-wrap items-center justify-between gap-3"
                            >
                                <span
                                    className={cn(
                                        'flex items-center gap-2 text-base',
                                        step.done &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {step.done ? (
                                        <CircleCheck
                                            aria-hidden
                                            className="size-5"
                                        />
                                    ) : null}
                                    {t(
                                        `agents.today.dayOne.steps.${step.code}`,
                                    )}
                                    <span className="text-muted-foreground">
                                        ·{' '}
                                        {t('agents.today.dayOne.minutes', {
                                            minutes: step.minutes,
                                        })}
                                    </span>
                                </span>
                                {step.done ? null : (
                                    <Button asChild variant="outline">
                                        <Link
                                            href={
                                                planHref(step.link) ?? planUrl
                                            }
                                        >
                                            {t('agents.plan.open')}
                                        </Link>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ol>
                </div>
            ) : null}
            {items.length === 0 && !today.day_one ? (
                <EmptyState
                    icon={CircleCheck}
                    title={t('agents.today.emptyTitle')}
                    description={t('agents.today.emptyBody')}
                />
            ) : (
                <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                    {items.map((item) => (
                        <li
                            key={item.key}
                            className="flex flex-wrap items-center justify-between gap-3 p-4"
                        >
                            <div className="min-w-0 flex-1 space-y-1">
                                <p className="flex flex-wrap items-center gap-2 text-base font-semibold">
                                    <span
                                        className={cn(
                                            'rounded-reos-pill px-3 py-0.5 text-sm font-semibold',
                                            TONE[item.reason],
                                        )}
                                    >
                                        {t(
                                            `agents.today.reasons.${item.reason}`,
                                        )}
                                    </span>
                                    {item.title}
                                </p>
                                <p className="text-base text-muted-foreground">
                                    {[
                                        when(item),
                                        item.detail,
                                        t('agents.today.from', {
                                            source: item.source,
                                        }),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                            {item.kind === 'event' ? null : (
                                <Button
                                    variant="outline"
                                    onClick={() => done(item)}
                                    aria-label={`${t('agents.today.markDone')}: ${item.title}`}
                                >
                                    <Check aria-hidden />
                                    {t('agents.today.done')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {today.more > 0 ? (
                <p className="text-base text-muted-foreground">
                    {t('agents.today.more', { count: today.more })}
                </p>
            ) : null}
        </section>
    );
}
