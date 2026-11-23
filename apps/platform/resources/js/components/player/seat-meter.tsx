import { CheckCircle2, Clock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PlayerState } from '@/lib/seat-time';
import { spent } from '@/lib/seat-time';

/**
 * How much of a lesson's required time has counted (A8.2), and, when the
 * last stretch didn't count, why in plain words.
 */
export function SeatMeter({
    state,
    failed = false,
}: {
    state: PlayerState;
    failed?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <section
            aria-label={t('player.meter.label')}
            className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="flex items-center gap-2 font-semibold">
                    {state.completed ? (
                        <CheckCircle2
                            aria-hidden
                            className="size-5 text-reos-success"
                        />
                    ) : (
                        <Clock aria-hidden className="size-5 text-primary" />
                    )}
                    {state.completed
                        ? t('player.meter.done')
                        : t('player.meter.counted', {
                              counted: spent(state.counted),
                              required: spent(state.required),
                          })}
                </p>
                <span className="text-sm text-muted-foreground tabular-nums">
                    {state.percent}%
                </span>
            </div>
            <div
                role="progressbar"
                aria-label={t('player.meter.progress')}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={state.percent}
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width]"
                    style={{ width: `${state.percent}%` }}
                />
            </div>
            {!state.completed && state.reason ? (
                <p className="text-body text-muted-foreground" role="status">
                    {t(`player.reasons.${state.reason}`)}
                </p>
            ) : null}
            {!state.completed &&
            state.today_left !== null &&
            state.today_left > 0 &&
            state.today_left < 1800 ? (
                <p className="text-body text-muted-foreground">
                    {t('player.meter.todayLeft', {
                        time: spent(state.today_left),
                    })}
                </p>
            ) : null}
            {failed ? (
                <p className="text-body text-destructive" role="alert">
                    {t('player.errors.heartbeat')}
                </p>
            ) : null}
        </section>
    );
}
