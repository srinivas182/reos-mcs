import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { TenantLimits } from '@/types';

/** "Limits and data" (screen 60): seats, credits, rate limit, queue, region and retention. */
export function TenantLimitsCard({
    limits,
    region,
}: {
    limits: TenantLimits;
    region: string;
}) {
    const { t, i18n } = useTranslation();
    const number = (value: number) => value.toLocaleString(i18n.language);

    const rows: [string, string][] = [
        [
            t('tenants.limits.seats'),
            t('tenants.limits.seatsValue', {
                seats:
                    limits.seats === null
                        ? t('tenants.limits.noLimit')
                        : number(limits.seats),
                payer: t(`tenants.plan.payers.${limits.payer}`),
            }),
        ],
        [
            t('tenants.limits.aiCredits'),
            t('tenants.limits.aiCreditsValue', {
                value: number(limits.credits_per_seat),
            }) +
                (limits.credits_pooled ? t('tenants.limits.pooledSuffix') : ''),
        ],
        [
            t('tenants.limits.candidatePool'),
            t('tenants.limits.credits', {
                count: limits.candidate_pool,
                value: number(limits.candidate_pool),
            }),
        ],
        [
            t('tenants.limits.rateLimit'),
            t('tenants.limits.rateValue', { value: number(limits.rate_limit) }),
        ],
        [
            t('tenants.limits.queueWeight'),
            t(`tenants.limits.weights.${limits.queue_weight}`),
        ],
        [
            t('tenants.limits.region'),
            t('tenants.limits.regionValue', { region }),
        ],
        [
            t('tenants.limits.retention'),
            t('tenants.limits.retentionValue', {
                years: limits.log_retention_years,
                days: limits.deleted_user_days,
            }),
        ],
    ];

    return (
        <section
            className="rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="limits-title"
        >
            <h2 id="limits-title" className="mb-2 font-semibold">
                {t('tenants.limits.title')}
            </h2>
            <dl className="divide-y divide-border">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex min-h-11 items-center justify-between gap-4 py-1"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="text-right font-semibold">{value}</dd>
                    </div>
                ))}
            </dl>
            <p className="mt-4 flex gap-3 rounded-reos-md bg-reos-tone-warning-bg p-3 text-sm text-reos-tone-warning-fg">
                <AlertTriangle aria-hidden className="mt-0.5 size-4 shrink-0" />
                {t('tenants.limits.supportNote')}
            </p>
        </section>
    );
}
