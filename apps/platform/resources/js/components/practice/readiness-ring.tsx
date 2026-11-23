import { useTranslation } from 'react-i18next';
import type { Topic } from './types';

/**
 * Readiness as a ring (A8.3): the share of the exam outline answered right
 * in recent tests. Before the first test it says so instead of showing 0.
 */
export function ReadinessRing({
    percent,
    size = 120,
}: {
    percent: number | null;
    size?: number;
}) {
    const { t } = useTranslation();
    const stroke = 10;
    const r = (size - stroke) / 2;
    const circumference = 2 * Math.PI * r;
    const value = percent ?? 0;

    return (
        <div
            role="img"
            aria-label={
                percent === null
                    ? t('practice.readiness.unknown')
                    : t('practice.readiness.label', { percent })
            }
            className="relative inline-flex items-center justify-center"
            style={{ width: size, height: size }}
        >
            <svg width={size} height={size} aria-hidden className="-rotate-90">
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={r}
                    strokeWidth={stroke}
                    className="fill-none stroke-muted"
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={r}
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference * (1 - value / 100)}
                    className="fill-none stroke-primary transition-[stroke-dashoffset]"
                />
            </svg>
            <span className="absolute text-center">
                <span className="block text-2xl font-semibold tabular-nums">
                    {percent === null ? '—' : `${percent}%`}
                </span>
                <span className="block text-sm text-muted-foreground">
                    {t('practice.readiness.title')}
                </span>
            </span>
        </div>
    );
}

/** Each topic's share right, as bars, with the pass line marked. */
export function TopicBars({
    topics,
    passLine = 70,
}: {
    topics: { topic: Topic; percent: number | null; weight?: number }[];
    passLine?: number;
}) {
    const { t } = useTranslation();

    return (
        <ul className="space-y-3">
            {topics.map(({ topic, percent, weight }) => (
                <li key={topic} className="space-y-1">
                    <div className="flex flex-wrap items-baseline justify-between gap-2 text-body">
                        <span className="font-medium">
                            {t(`practice.topics.${topic}`)}
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            {percent === null
                                ? t('practice.readiness.notYet')
                                : `${percent}%`}
                            {weight
                                ? ` · ${t('practice.results.weight', { count: weight })}`
                                : ''}
                        </span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label={t(`practice.topics.${topic}`)}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={percent ?? 0}
                        className="relative h-2 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            className={
                                percent !== null && percent >= passLine
                                    ? 'h-full rounded-full bg-reos-tone-success-fg'
                                    : 'h-full rounded-full bg-reos-tone-warning-fg'
                            }
                            style={{ width: `${percent ?? 0}%` }}
                        />
                        <span
                            aria-hidden
                            className="absolute inset-y-0 w-0.5 bg-foreground/40"
                            style={{ left: `${passLine}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}
