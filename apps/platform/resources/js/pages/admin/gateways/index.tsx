import { Head, Link, router } from '@inertiajs/react';
import {
    CreditCard,
    Pause,
    Pencil,
    Play,
    Plus,
    Receipt,
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
import type { RefundableOrder } from '@/components/refund-dialog';
import { RefundDialog } from '@/components/refund-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/billing';
import { shortDate } from '@/lib/payouts';
import { cn } from '@/lib/utils';
import { index, resume } from '@/routes/gateways';
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
type ReconciliationRow = {
    day: string;
    gateway: string;
    environment: string;
    status: 'matched' | 'differences' | 'failed';
    matched: number;
    differences: {
        kind: string;
        reference: string;
        ledger: number | null;
        gateway: number | null;
    }[];
    difference_count: number;
};

const RUN_TONE = {
    matched: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    differences: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    failed: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
} as const;

export default function PaymentGateways({
    gateways,
    fees,
    payouts,
    search,
    orders,
    reconciliation,
    options,
}: {
    search: string;
    orders: RefundableOrder[];
    reconciliation: ReconciliationRow[];
    gateways: GatewayRow[];
    fees: { platform_fee_percent: number; refund_days: number };
    payouts: {
        pending: number;
        ready: number;
        setting_up: number;
        sent_this_month: number;
        next_payout: string;
    };
    options: GatewayOptions;
}) {
    const { t, i18n } = useTranslation();
    const [editing, setEditing] = useState<GatewayRow | null>(null);
    const [pausing, setPausing] = useState<GatewayRow | null>(null);
    const [refunding, setRefunding] = useState<RefundableOrder | null>(null);
    const [query, setQuery] = useState(search);
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

                <div className="grid gap-6 lg:grid-cols-3">
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
                        aria-labelledby="payouts"
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="payouts" className="font-semibold">
                            {t('gateways.payouts.title')}
                        </h2>
                        <dl className="divide-y divide-border">
                            {(
                                [
                                    [
                                        'pending',
                                        money(payouts.pending, i18n.language),
                                    ],
                                    [
                                        'next',
                                        shortDate(
                                            payouts.next_payout,
                                            i18n.language,
                                        ),
                                    ],
                                    [
                                        'sent',
                                        money(
                                            payouts.sent_this_month,
                                            i18n.language,
                                        ),
                                    ],
                                    ['ready', String(payouts.ready)],
                                    ['settingUp', String(payouts.setting_up)],
                                ] as const
                            ).map(([key, value]) => (
                                <div
                                    key={key}
                                    className="flex justify-between gap-4 py-2"
                                >
                                    <dt className="text-muted-foreground">
                                        {t(`gateways.payouts.${key}`)}
                                    </dt>
                                    <dd className="text-right font-medium tabular-nums">
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

            <div className="space-y-6 px-4 pb-8 md:px-8">
                <section
                    aria-labelledby="orders"
                    className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                >
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div className="space-y-1">
                            <h2 id="orders" className="font-semibold">
                                {t('billing.refunds.ordersTitle')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('billing.refunds.ordersHelp')}
                            </p>
                        </div>
                        <form
                            role="search"
                            className="flex gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    index({ query: query ? { q: query } : {} })
                                        .url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                );
                            }}
                        >
                            <Label htmlFor="order-search" className="sr-only">
                                {t('billing.refunds.search')}
                            </Label>
                            <Input
                                id="order-search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder={t('billing.refunds.search')}
                                className="w-64"
                            />
                            <Button type="submit" variant="outline">
                                {t('billing.refunds.find')}
                            </Button>
                        </form>
                    </div>
                    {orders.length === 0 ? (
                        <EmptyState
                            icon={Receipt}
                            title={t('billing.refunds.noOrdersTitle')}
                            description={t('billing.refunds.noOrders')}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-base">
                                <caption className="sr-only">
                                    {t('billing.refunds.ordersTitle')}
                                </caption>
                                <thead className="text-sm text-muted-foreground uppercase">
                                    <tr className="border-b border-border">
                                        {(
                                            [
                                                'product',
                                                'buyer',
                                                'gateway',
                                                'total',
                                                'paid',
                                                'status',
                                            ] as const
                                        ).map((c) => (
                                            <th
                                                key={c}
                                                scope="col"
                                                className="px-3 py-3 font-semibold"
                                            >
                                                {t(
                                                    `billing.refunds.columns.${c}`,
                                                )}
                                            </th>
                                        ))}
                                        <th scope="col" className="sr-only">
                                            {t('gateways.actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.map((o) => (
                                        <tr
                                            key={o.uuid}
                                            className="border-b border-border align-top last:border-b-0"
                                        >
                                            <th
                                                scope="row"
                                                className="px-3 py-3 font-medium"
                                            >
                                                {o.product}
                                            </th>
                                            <td className="px-3 py-3">
                                                {o.buyer?.name ?? '—'}
                                                {o.buyer?.email ? (
                                                    <span className="block text-sm text-muted-foreground">
                                                        {o.buyer.email}
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-3">
                                                {t(
                                                    `gateways.names.${o.gateway as GatewayRow['id']}`,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 tabular-nums">
                                                {money(
                                                    o.total ?? 0,
                                                    i18n.language,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 whitespace-nowrap">
                                                {o.paid_at
                                                    ? shortDate(
                                                          o.paid_at.slice(
                                                              0,
                                                              10,
                                                          ),
                                                          i18n.language,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                {o.status === 'refunded'
                                                    ? t(
                                                          'billing.refunds.refunded',
                                                      )
                                                    : o.policy.refundable <
                                                        (o.total ?? 0)
                                                      ? t(
                                                            'billing.refunds.partlyRefunded',
                                                            {
                                                                amount: money(
                                                                    (o.total ??
                                                                        0) -
                                                                        o.policy
                                                                            .refundable,
                                                                    i18n.language,
                                                                ),
                                                            },
                                                        )
                                                      : t(
                                                            'billing.refunds.paid',
                                                        )}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                {o.status === 'paid' &&
                                                o.policy.refundable > 0 ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setRefunding(o)
                                                        }
                                                    >
                                                        {t(
                                                            'billing.refunds.refund',
                                                        )}
                                                    </Button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section
                    aria-labelledby="reconciliation"
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                >
                    <h2 id="reconciliation" className="font-semibold">
                        {t('billing.reconciliation.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('billing.reconciliation.help')}
                    </p>
                    {reconciliation.length === 0 ? (
                        <p className="text-body text-muted-foreground">
                            {t('billing.reconciliation.none')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {reconciliation.map((run) => (
                                <li
                                    key={`${run.day}-${run.gateway}-${run.environment}`}
                                    className="space-y-1 py-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <span className="font-medium">
                                            {shortDate(run.day, i18n.language)}{' '}
                                            ·{' '}
                                            {t(
                                                `gateways.names.${run.gateway as GatewayRow['id']}`,
                                            )}{' '}
                                            ·{' '}
                                            {t(
                                                `gateways.status.${run.environment as GatewayRow['mode']}`,
                                            )}
                                        </span>
                                        <span
                                            className={cn(
                                                PILL,
                                                RUN_TONE[run.status],
                                            )}
                                        >
                                            {t(
                                                `billing.reconciliation.status.${run.status}`,
                                                {
                                                    matched: run.matched,
                                                    count: run.difference_count,
                                                },
                                            )}
                                        </span>
                                    </div>
                                    {run.differences.map((d) => (
                                        <p
                                            key={`${d.kind}-${d.reference}`}
                                            className="text-sm text-muted-foreground"
                                        >
                                            {t(
                                                'billing.reconciliation.difference',
                                                {
                                                    kind: t(
                                                        `billing.reconciliation.kinds.${d.kind as 'order'}`,
                                                    ),
                                                    reference: d.reference,
                                                    ledger:
                                                        d.ledger === null
                                                            ? '—'
                                                            : money(
                                                                  d.ledger,
                                                                  i18n.language,
                                                              ),
                                                    gateway:
                                                        d.gateway === null
                                                            ? '—'
                                                            : money(
                                                                  d.gateway,
                                                                  i18n.language,
                                                              ),
                                                },
                                            )}
                                        </p>
                                    ))}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <RefundDialog
                order={refunding}
                onOpenChange={(open) => (open ? null : setRefunding(null))}
            />
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
