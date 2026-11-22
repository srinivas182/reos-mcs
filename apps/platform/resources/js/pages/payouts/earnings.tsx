import { Head } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PayoutAccountCard } from '@/components/payout-account';
import { money } from '@/lib/billing';
import type { EarningLine, PayoutAccount, PayoutRow } from '@/lib/payouts';
import { shortDate } from '@/lib/payouts';
import { cn } from '@/lib/utils';
import { connect } from '@/routes/earnings';

const TONE: Record<EarningLine['status'], string> = {
    pending: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    paid: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    cancelled: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    reversed: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
};

/**
 * Earnings for instructors and mentors on the web (B5.2b; the phone's
 * screens 20 and 28): net this month, the next payout, each share of a
 * sale, recent payouts, and payouts set up on Stripe.
 */
export default function Earnings({
    statement,
}: {
    statement: {
        net_this_month: number;
        fee_percent: number;
        pending: number;
        next_payout: string;
        account: PayoutAccount;
        lines: EarningLine[];
        payouts: PayoutRow[];
    };
}) {
    const { t, i18n } = useTranslation();

    return (
        <>
            <Head title={t('payouts.earnings.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('payouts.earnings.title')}
                    description={t('payouts.earnings.description', {
                        percent: statement.fee_percent,
                    })}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-reos-lg border border-border bg-card p-5">
                        <p className="text-muted-foreground">
                            {t('payouts.earnings.netThisMonth')}
                        </p>
                        <p className="text-title font-semibold tabular-nums">
                            {money(statement.net_this_month, i18n.language)}
                        </p>
                    </div>
                    <div className="rounded-reos-lg border border-border bg-card p-5">
                        <p className="text-muted-foreground">
                            {t('payouts.earnings.nextPayout', {
                                date: shortDate(
                                    statement.next_payout,
                                    i18n.language,
                                ),
                            })}
                        </p>
                        <p className="text-title font-semibold tabular-nums">
                            {money(statement.pending, i18n.language)}
                        </p>
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <section
                        aria-labelledby="lines"
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="lines" className="font-semibold">
                            {t('payouts.earnings.lines')}
                        </h2>
                        {statement.lines.length === 0 ? (
                            <EmptyState
                                icon={Wallet}
                                title={t('payouts.earnings.emptyTitle')}
                                description={t('payouts.earnings.empty')}
                            />
                        ) : (
                            <ul className="divide-y divide-border">
                                {statement.lines.map((line) => (
                                    <li
                                        key={line.id}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-medium">
                                                {line.product}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {[
                                                    t(
                                                        `payouts.roles.${line.role}`,
                                                    ),
                                                    line.date
                                                        ? shortDate(
                                                              line.date,
                                                              i18n.language,
                                                          )
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-3">
                                            <span
                                                className={cn(
                                                    'rounded-full px-2.5 py-0.5 text-sm font-medium',
                                                    TONE[line.status],
                                                )}
                                            >
                                                {t(
                                                    `payouts.lineStatus.${line.status}`,
                                                )}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {money(
                                                    line.amount,
                                                    i18n.language,
                                                )}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                    <div className="space-y-6">
                        <PayoutAccountCard
                            account={statement.account}
                            connectUrl={connect().url}
                        />
                        <section
                            aria-labelledby="recent-payouts"
                            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                        >
                            <h2 id="recent-payouts" className="font-semibold">
                                {t('payouts.recent')}
                            </h2>
                            {statement.payouts.length === 0 ? (
                                <p className="text-body text-muted-foreground">
                                    {t('payouts.noPayouts')}
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {statement.payouts.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex justify-between gap-4 py-2"
                                        >
                                            <span>
                                                {p.date
                                                    ? shortDate(
                                                          p.date,
                                                          i18n.language,
                                                      )
                                                    : '—'}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {money(p.amount, i18n.language)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}
