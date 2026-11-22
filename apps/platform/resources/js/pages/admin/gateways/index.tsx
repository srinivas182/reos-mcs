import { Head, Link, router } from '@inertiajs/react';
import {
    CreditCard,
    Pause,
    Pencil,
    Play,
    Plus,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import type {
    GatewayHealth,
    GatewayOptions,
    GatewayRow,
} from '@/components/gateway-dialogs';
import {
    GatewayDialog,
    PauseGatewayDialog,
} from '@/components/gateway-dialogs';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { resume } from '@/routes/gateways';
import { index as integrations } from '@/routes/integrations';

const PILL =
    'inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium whitespace-nowrap';
const TONE = {
    success: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    warning: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    danger: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
    info: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    neutral: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
} as const;

type StatusKey = 'paused' | 'off' | 'needsKeys' | GatewayRow['mode'];

function status(row: GatewayRow): { key: StatusKey; tone: keyof typeof TONE } {
    if (row.paused) {
        return { key: 'paused', tone: 'danger' };
    }

    if (!row.running) {
        return {
            key: row.mode === 'off' ? 'off' : 'needsKeys',
            tone: 'neutral',
        };
    }

    return {
        key: row.mode,
        tone: row.mode === 'live' ? 'success' : 'info',
    };
}

const HEALTH_TONE: Record<NonNullable<GatewayHealth>, keyof typeof TONE> = {
    healthy: 'success',
    simulator: 'info',
    failing: 'danger',
    autoPaused: 'warning',
};

/**
 * Payment gateways (web journey screen 66, B5.2a): each gateway with its
 * status, states and currency, what it's used for, payouts, tenant-owned
 * accounts and health; fees and tax; the rules. Edit how a gateway is used,
 * pause it (the kill switch), or add one in Integrations, where its keys live.
 */
export default function PaymentGateways({
    gateways,
    fees,
    options,
}: {
    gateways: GatewayRow[];
    fees: { platform_fee_percent: number; refund_days: number };
    options: GatewayOptions;
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<GatewayRow | null>(null);
    const [pausing, setPausing] = useState<GatewayRow | null>(null);
    const stateNames = Object.fromEntries(
        options.states.map((s) => [s.code, s.name]),
    );
    const list = <T extends string>(
        values: T[] | null,
        label: (v: T) => string,
    ) => (values === null ? t('gateways.all') : values.map(label).join(', '));

    return (
        <>
            <Head title={t('gateways.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('gateways.title')}
                    description={t('gateways.description')}
                    actions={
                        <Button asChild>
                            <Link href={integrations()}>
                                <Plus aria-hidden />
                                {t('gateways.add')}
                            </Link>
                        </Button>
                    }
                />

                <section className="rounded-reos-lg border border-border bg-card p-5">
                    {gateways.length === 0 ? (
                        <EmptyState
                            icon={CreditCard}
                            title={t('gateways.emptyTitle')}
                            description={t('gateways.empty')}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-base">
                                <caption className="sr-only">
                                    {t('gateways.title')}
                                </caption>
                                <thead className="text-sm text-muted-foreground uppercase">
                                    <tr className="border-b border-border">
                                        {(
                                            [
                                                'gateway',
                                                'status',
                                                'states',
                                                'usedFor',
                                                'payouts',
                                                'tenantAccounts',
                                                'health',
                                            ] as const
                                        ).map((column) => (
                                            <th
                                                key={column}
                                                scope="col"
                                                className="px-3 py-3 font-semibold"
                                            >
                                                {t(
                                                    `gateways.columns.${column}`,
                                                )}
                                            </th>
                                        ))}
                                        <th scope="col" className="sr-only">
                                            {t('gateways.actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {gateways.map((row) => {
                                        const s = status(row);

                                        return (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border align-top last:border-b-0"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-3 py-3 font-medium"
                                                >
                                                    {t(
                                                        `gateways.names.${row.id}`,
                                                    )}
                                                </th>
                                                <td className="px-3 py-3">
                                                    <span
                                                        className={cn(
                                                            PILL,
                                                            TONE[s.tone],
                                                        )}
                                                    >
                                                        {t(
                                                            `gateways.status.${s.key}`,
                                                        )}
                                                        {row.is_default
                                                            ? ` · ${t('gateways.default')}`
                                                            : ''}
                                                    </span>
                                                    {row.paused &&
                                                    row.paused_reason ? (
                                                        <p className="mt-1 max-w-56 text-sm text-muted-foreground">
                                                            {row.paused_reason}
                                                        </p>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {list(
                                                        row.states,
                                                        (c) =>
                                                            stateNames[c] ?? c,
                                                    )}{' '}
                                                    · USD
                                                </td>
                                                <td className="max-w-xs px-3 py-3">
                                                    {list(row.used_for, (k) =>
                                                        t(`plans.kinds.${k}`),
                                                    )}
                                                    {row.capabilities
                                                        .recurring ? null : (
                                                        <p className="text-sm text-muted-foreground">
                                                            {t(
                                                                'gateways.onceOnly',
                                                            )}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="max-w-xs px-3 py-3 text-muted-foreground">
                                                    {row.capabilities.payouts
                                                        ? t(
                                                              'gateways.payoutsConnect',
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.capabilities
                                                        .tenant_accounts ? (
                                                        <span
                                                            className={cn(
                                                                PILL,
                                                                row.tenant_accounts
                                                                    ? TONE.success
                                                                    : TONE.neutral,
                                                            )}
                                                        >
                                                            {row.tenant_accounts
                                                                ? t(
                                                                      'gateways.allowed',
                                                                  )
                                                                : t(
                                                                      'gateways.notAllowed',
                                                                  )}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.health ? (
                                                        <span
                                                            className={cn(
                                                                PILL,
                                                                TONE[
                                                                    HEALTH_TONE[
                                                                        row
                                                                            .health
                                                                    ]
                                                                ],
                                                            )}
                                                        >
                                                            {t(
                                                                `gateways.health.${row.health}`,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                    {row.running ? (
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            {t(
                                                                'gateways.ordersToday',
                                                                {
                                                                    paid: row
                                                                        .orders_today
                                                                        .paid,
                                                                    expired:
                                                                        row
                                                                            .orders_today
                                                                            .expired,
                                                                },
                                                            )}
                                                        </p>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                setEditing(row)
                                                            }
                                                            aria-label={t(
                                                                'gateways.edit',
                                                                {
                                                                    name: row.name,
                                                                },
                                                            )}
                                                        >
                                                            <Pencil
                                                                aria-hidden
                                                            />
                                                        </Button>
                                                        {row.paused ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    router.delete(
                                                                        resume(
                                                                            row.id,
                                                                        ).url,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Play
                                                                    aria-hidden
                                                                />
                                                                {t(
                                                                    'gateways.resume',
                                                                )}
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setPausing(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                <Pause
                                                                    aria-hidden
                                                                />
                                                                {t(
                                                                    'gateways.pause',
                                                                )}
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section
                        aria-labelledby="fees"
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="fees" className="font-semibold">
                            {t('gateways.fees.title')}
                        </h2>
                        <dl className="divide-y divide-border">
                            {(
                                [
                                    [
                                        'sessions',
                                        `${fees.platform_fee_percent}%`,
                                    ],
                                    [
                                        'schoolSales',
                                        `${fees.platform_fee_percent}%`,
                                    ],
                                    [
                                        'providerPlans',
                                        t('gateways.fees.allReos'),
                                    ],
                                    ['salesTax', t('gateways.fees.stripeTax')],
                                    [
                                        'refunds',
                                        t('gateways.fees.refundRule', {
                                            count: fees.refund_days,
                                        }),
                                    ],
                                ] as const
                            ).map(([key, value]) => (
                                <div
                                    key={key}
                                    className="flex justify-between gap-4 py-2"
                                >
                                    <dt className="text-muted-foreground">
                                        {t(`gateways.fees.${key}`)}
                                    </dt>
                                    <dd className="text-right font-medium">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                    <section
                        aria-labelledby="rules"
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="rules" className="font-semibold">
                            {t('gateways.rules.title')}
                        </h2>
                        <ul className="space-y-2">
                            {(
                                [
                                    'routing',
                                    'tenantAccounts',
                                    'killSwitch',
                                    'tax',
                                    'pci',
                                ] as const
                            ).map((rule) => (
                                <li key={rule} className="flex gap-2">
                                    <ShieldCheck
                                        aria-hidden
                                        className="mt-0.5 size-5 shrink-0 text-primary"
                                    />
                                    {t(`gateways.rules.${rule}`)}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>

            <GatewayDialog
                gateway={editing}
                options={options}
                onOpenChange={(open) => (open ? null : setEditing(null))}
            />
            <PauseGatewayDialog
                gateway={pausing}
                onOpenChange={(open) => (open ? null : setPausing(null))}
            />
        </>
    );
}
