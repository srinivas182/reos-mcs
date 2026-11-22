import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/billing';
import { cn } from '@/lib/utils';
import { dashboard, status as statusPage } from '@/routes';
import { index as gateways } from '@/routes/gateways';
import { index as integrations } from '@/routes/integrations';
import { index as tenants } from '@/routes/tenants';

type Overview = {
    range: '24h' | '30d';
    tenants: { total: number; by_type: Record<string, number> };
    people: { total: number; agents: number; candidates: number };
    online: number;
    active_today: number;
    p95: number | null;
    p95_target: number;
    uptime: number | null;
    uptime_target: number;
    health: { ok: boolean; checked_at: string } | null;
    traffic: { at: string; requests: number; errors: number }[];
    cells: {
        cell: string;
        region: string;
        tenants: number;
        status: 'healthy' | 'unhealthy';
    }[];
    integrations: {
        healthy: number;
        paused: string[];
        coming_soon: number;
        interest: number;
    };
    payments: {
        gateway: {
            name: string;
            health: string | null;
            paused: boolean;
        } | null;
        charges: number;
        amount: number;
        unfinished_percent: number;
        refunds: number;
        reconciliation: { day: string; status: string } | null;
    };
    queues: {
        waiting: { queue: string; size: number | null }[];
        failed: number;
    };
};

const TONE = {
    good: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    bad: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
    neutral: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
} as const;

function Stat({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <div className="rounded-reos-lg border border-border bg-card p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-title font-semibold tabular-nums">{value}</p>
            <p className="text-sm text-muted-foreground">{detail}</p>
        </div>
    );
}

function Rows({ rows }: { rows: [string, string][] }) {
    return (
        <dl className="divide-y divide-border">
            {rows.map(([label, value]) => (
                <div key={label} className="flex justify-between gap-4 py-2">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="text-right font-medium tabular-nums">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/**
 * Platform overview (web journey screen 58, A6.1): tenants, people, who is
 * online, latency and uptime against the targets (p95 under 300 ms, 99.95%),
 * traffic, the cell, integrations, today's payments and the queues; each
 * card links into its area, and the public status page is one click away.
 */
export default function PlatformOverview({ overview }: { overview: Overview }) {
    const { t, i18n } = useTranslation();
    const number = new Intl.NumberFormat(i18n.language);
    const peak = Math.max(1, ...overview.traffic.map((b) => b.requests));
    const daily = overview.range === '30d';
    const p95Good =
        overview.p95 !== null && overview.p95 <= overview.p95_target;
    const byType = Object.entries(overview.tenants.by_type)
        .map(([type, count]) =>
            t(`overview.typeCount.${type as 'school'}`, { count }),
        )
        .join(', ');

    return (
        <>
            <Head title={t('overview.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('overview.title')}
                    description={t('overview.description')}
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.get(
                                        dashboard({
                                            query: {
                                                range: daily ? '24h' : '30d',
                                            },
                                        }).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {daily
                                    ? t('overview.last24h')
                                    : t('overview.last30d')}
                            </Button>
                            <Button asChild>
                                <a href={statusPage().url}>
                                    {t('overview.statusPage')}
                                </a>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Stat
                        label={t('overview.tenants')}
                        value={number.format(overview.tenants.total)}
                        detail={byType || t('overview.noTenants')}
                    />
                    <Stat
                        label={t('overview.people')}
                        value={number.format(overview.people.total)}
                        detail={t('overview.peopleDetail', {
                            agents: number.format(overview.people.agents),
                            candidates: number.format(
                                overview.people.candidates,
                            ),
                        })}
                    />
                    <Stat
                        label={t('overview.online')}
                        value={number.format(overview.online)}
                        detail={t('overview.activeToday', {
                            count: overview.active_today,
                        })}
                    />
                    <Stat
                        label={t('overview.p95')}
                        value={
                            overview.p95 === null
                                ? '—'
                                : t('overview.ms', { value: overview.p95 })
                        }
                        detail={t('overview.p95Target', {
                            value: overview.p95_target,
                        })}
                    />
                    <Stat
                        label={t('overview.uptime')}
                        value={
                            overview.uptime === null
                                ? '—'
                                : `${overview.uptime}%`
                        }
                        detail={t('overview.uptimeTarget', {
                            value: overview.uptime_target,
                        })}
                    />
                </div>
                {overview.p95 !== null && !p95Good ? (
                    <p
                        role="status"
                        className={cn('rounded-reos-md p-3 text-sm', TONE.bad)}
                    >
                        {t('overview.p95Over')}
                    </p>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <section
                        aria-labelledby="traffic"
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="traffic" className="font-semibold">
                            {daily
                                ? t('overview.trafficDaily')
                                : t('overview.trafficHourly')}
                        </h2>
                        <div
                            role="img"
                            aria-label={t('overview.trafficSummary', {
                                total: number.format(
                                    overview.traffic.reduce(
                                        (sum, b) => sum + b.requests,
                                        0,
                                    ),
                                ),
                            })}
                            className="flex h-48 items-end gap-1"
                        >
                            {overview.traffic.map((bucket) => (
                                <div
                                    key={bucket.at}
                                    title={`${new Date(bucket.at).toLocaleString(i18n.language, daily ? { month: 'short', day: 'numeric' } : { hour: 'numeric' })}: ${number.format(bucket.requests)}`}
                                    className="flex-1 rounded-t-reos-sm bg-primary"
                                    style={{
                                        height:
                                            bucket.requests === 0
                                                ? '1px'
                                                : `${Math.max(2, (bucket.requests / peak) * 100)}%`,
                                    }}
                                />
                            ))}
                        </div>
                        <div className="flex justify-between text-sm text-muted-foreground">
                            <span>
                                {overview.traffic[0]
                                    ? new Date(
                                          overview.traffic[0].at,
                                      ).toLocaleString(
                                          i18n.language,
                                          daily
                                              ? {
                                                    month: 'short',
                                                    day: 'numeric',
                                                }
                                              : { hour: 'numeric' },
                                      )
                                    : ''}
                            </span>
                            <span>{t('overview.now')}</span>
                        </div>
                    </section>
                    <section
                        aria-labelledby="cells"
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="cells" className="font-semibold">
                            {t('overview.cells')}
                        </h2>
                        <table className="w-full text-left text-base">
                            <thead className="text-sm text-muted-foreground uppercase">
                                <tr className="border-b border-border">
                                    {(
                                        [
                                            'cell',
                                            'region',
                                            'tenants',
                                            'status',
                                        ] as const
                                    ).map((c) => (
                                        <th
                                            key={c}
                                            scope="col"
                                            className="py-2 pr-3 font-semibold"
                                        >
                                            {t(`overview.cellColumns.${c}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {overview.cells.map((cell) => (
                                    <tr key={cell.cell}>
                                        <th
                                            scope="row"
                                            className="py-2 pr-3 font-medium"
                                        >
                                            {cell.cell}
                                        </th>
                                        <td className="py-2 pr-3">
                                            {cell.region}
                                        </td>
                                        <td className="py-2 pr-3 tabular-nums">
                                            {number.format(cell.tenants)}
                                        </td>
                                        <td className="py-2">
                                            <span
                                                className={cn(
                                                    'rounded-full px-2.5 py-0.5 text-sm font-medium',
                                                    cell.status === 'healthy'
                                                        ? TONE.good
                                                        : TONE.bad,
                                                )}
                                            >
                                                {t(
                                                    `overview.cellStatus.${cell.status}`,
                                                )}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="text-sm text-muted-foreground">
                            {t('overview.oneCell')}
                        </p>
                    </section>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section
                        aria-labelledby="integrations"
                        className="space-y-2 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="integrations" className="font-semibold">
                            <Link
                                href={integrations()}
                                className="hover:underline"
                            >
                                {t('overview.integrations')}
                            </Link>
                        </h2>
                        <Rows
                            rows={[
                                [
                                    t('overview.liveHealthy'),
                                    String(overview.integrations.healthy),
                                ],
                                [
                                    t('overview.paused'),
                                    overview.integrations.paused.length === 0
                                        ? '0'
                                        : `${overview.integrations.paused.length} · ${overview.integrations.paused.join(', ')}`,
                                ],
                                [
                                    t('overview.comingSoon'),
                                    t('overview.comingSoonValue', {
                                        count: overview.integrations
                                            .coming_soon,
                                        taps: number.format(
                                            overview.integrations.interest,
                                        ),
                                    }),
                                ],
                            ]}
                        />
                    </section>
                    <section
                        aria-labelledby="payments"
                        className="space-y-2 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="payments" className="font-semibold">
                            <Link href={gateways()} className="hover:underline">
                                {t('overview.paymentsToday')}
                            </Link>
                        </h2>
                        <Rows
                            rows={[
                                [
                                    t('overview.gateway'),
                                    overview.payments.gateway
                                        ? `${overview.payments.gateway.name} · ${overview.payments.gateway.paused ? t('gateways.status.paused') : t(`gateways.health.${(overview.payments.gateway.health ?? 'healthy') as 'healthy'}`)}`
                                        : '—',
                                ],
                                [
                                    t('overview.charges'),
                                    `${number.format(overview.payments.charges)} · ${money(overview.payments.amount, i18n.language)}`,
                                ],
                                [
                                    t('overview.unfinished'),
                                    `${overview.payments.unfinished_percent}%`,
                                ],
                                [
                                    t('overview.refunds'),
                                    String(overview.payments.refunds),
                                ],
                                [
                                    t('overview.reconciliation'),
                                    overview.payments.reconciliation
                                        ? t(
                                              `overview.reconciliationStatus.${overview.payments.reconciliation.status as 'matched'}`,
                                          )
                                        : '—',
                                ],
                            ]}
                        />
                    </section>
                    <section
                        aria-labelledby="queues"
                        className="space-y-2 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="queues" className="font-semibold">
                            {t('overview.queues')}
                        </h2>
                        <Rows
                            rows={[
                                ...overview.queues.waiting.map(
                                    (q): [string, string] => [
                                        t(
                                            `overview.queueNames.${q.queue as 'default'}`,
                                        ),
                                        q.size === null
                                            ? t('overview.unreachable')
                                            : t('overview.waiting', {
                                                  count: q.size,
                                              }),
                                    ],
                                ),
                                [
                                    t('overview.failedJobs'),
                                    number.format(overview.queues.failed),
                                ],
                            ]}
                        />
                    </section>
                </div>
                <p className="text-sm text-muted-foreground">
                    <Link
                        href={tenants()}
                        className="text-primary hover:underline"
                    >
                        {t('overview.openTenants')}
                    </Link>
                </p>
            </div>
        </>
    );
}
