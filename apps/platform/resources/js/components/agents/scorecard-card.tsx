import { Target } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { Scorecard } from './types';

/**
 * This week's scorecard (A11.1b; agent screen 110): conversations,
 * appointments, active clients and CE against the plan's targets, and the
 * one thing to fix.
 */
export function ScorecardCard({ scorecard }: { scorecard: Scorecard }) {
    const { t, i18n } = useTranslation();
    const weekOf = new Intl.DateTimeFormat(i18n.language, {
        month: 'long',
        day: 'numeric',
    }).format(new Date(`${scorecard.week_of}T12:00:00`));

    return (
        <section
            aria-labelledby="scorecard"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="scorecard" className="text-lg font-semibold">
                    {t('agents.scorecard.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('agents.scorecard.weekOf', { date: weekOf })}
                </p>
            </div>
            <dl className="grid gap-3 sm:grid-cols-2">
                {scorecard.metrics.map((m) => {
                    const share =
                        m.target > 0
                            ? Math.min(
                                  100,
                                  Math.round((m.done / m.target) * 100),
                              )
                            : 100;
                    return (
                        <div key={m.key} className="space-y-2">
                            <dt className="text-base text-muted-foreground">
                                {t(`agents.scorecard.metrics.${m.key}`)}
                            </dt>
                            <dd className="space-y-2">
                                <span className="text-xl font-semibold">
                                    {t('agents.scorecard.value', {
                                        done: m.done,
                                        target: m.target,
                                    })}
                                </span>
                                <span
                                    aria-hidden
                                    className="block h-2 overflow-hidden rounded-reos-pill bg-muted"
                                >
                                    <span
                                        className={cn(
                                            'block h-full',
                                            m.key === scorecard.fix
                                                ? 'bg-destructive'
                                                : 'bg-primary',
                                        )}
                                        style={{ width: `${share}%` }}
                                    />
                                </span>
                            </dd>
                        </div>
                    );
                })}
            </dl>
            <div className="flex items-start gap-3 rounded-reos-md bg-muted p-4">
                <Target aria-hidden className="mt-0.5 size-5 shrink-0" />
                <p className="text-base">
                    <span className="font-semibold">
                        {t('agents.scorecard.fixTitle')}:{' '}
                    </span>
                    {scorecard.fix
                        ? t(`agents.scorecard.fix.${scorecard.fix}`)
                        : t('agents.scorecard.onTrack')}
                </p>
            </div>
        </section>
    );
}
