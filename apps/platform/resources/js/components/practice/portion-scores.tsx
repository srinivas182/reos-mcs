import { CheckCircle2, Clock, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PortionScore } from '@/components/practice/types';
import { cn } from '@/lib/utils';

/**
 * Score per portion against its pass mark (candidate screen 53, A9.1), the
 * change since last time, and the timing insight: seconds per question
 * against the pace the real exam allows.
 */
export function PortionScores({
    portions,
    timing,
}: {
    portions: PortionScore[];
    timing: { seconds_per_question: number; pace: number } | null;
}) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="portions-title"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2 id="portions-title" className="text-lg font-semibold">
                {t('practice.results.portions')}
            </h2>
            <ul className="grid gap-3 sm:grid-cols-2">
                {portions.map((p) => (
                    <li
                        key={p.portion}
                        className={cn(
                            'space-y-1 rounded-reos-md border p-4',
                            p.passed
                                ? 'border-reos-tone-success-fg'
                                : 'border-reos-tone-warning-fg',
                        )}
                    >
                        <p className="flex items-center gap-2 font-semibold">
                            {p.passed ? (
                                <CheckCircle2
                                    aria-hidden
                                    className="size-5 text-reos-tone-success-fg"
                                />
                            ) : (
                                <XCircle
                                    aria-hidden
                                    className="size-5 text-reos-tone-warning-fg"
                                />
                            )}
                            {t(`practice.portions.${p.portion}.title`)}
                        </p>
                        <p className="text-2xl font-semibold tabular-nums">
                            {t('practice.results.portionScore', {
                                correct: p.correct,
                                total: p.total,
                            })}
                        </p>
                        <p className="text-body">
                            {p.passed
                                ? t('practice.results.portionPassed', {
                                      pass: p.pass,
                                  })
                                : t('practice.results.portionShort', {
                                      pass: p.pass,
                                      short: p.pass - p.correct,
                                  })}
                        </p>
                        {p.previous !== null ? (
                            <p className="text-sm text-muted-foreground">
                                {t('practice.results.portionChange', {
                                    previous: p.previous,
                                    now: p.percent,
                                })}
                            </p>
                        ) : null}
                    </li>
                ))}
            </ul>
            {timing ? (
                <p className="flex items-start gap-2 text-body">
                    <Clock
                        aria-hidden
                        className="mt-0.5 size-5 shrink-0 text-primary"
                    />
                    {timing.seconds_per_question > timing.pace
                        ? t('practice.results.timingSlow', {
                              seconds: timing.seconds_per_question,
                              pace: timing.pace,
                          })
                        : t('practice.results.timingOk', {
                              seconds: timing.seconds_per_question,
                              pace: timing.pace,
                          })}
                </p>
            ) : null}
        </section>
    );
}
