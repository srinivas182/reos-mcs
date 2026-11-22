import { Head } from '@inertiajs/react';
import { Download, Info, Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PayoutAccountCard } from '@/components/payout-account';
import type { PayoutTotals } from '@/components/payout-breakdown';
import { PayoutBreakdown } from '@/components/payout-breakdown';
import { Button } from '@/components/ui/button';
import type { Interval } from '@/lib/billing';
import { money, priceLabel } from '@/lib/billing';
import type { PayoutAccount, PayoutRow } from '@/lib/payouts';
import { shortDate } from '@/lib/payouts';
import { connect, connectOwn, exportMethod } from '@/routes/sales';

type ProductRow = {
    id: number;
    product: string;
    state: string;
    amount: number;
    floor: number | null;
    interval: Interval;
    sold: number;
    discounts: { sponsor: string; amount: number }[];
};

/**
 * Sales and payouts (school admin web screen 53, B5.2b): products with
 * prices and sponsor discounts, this month's payout breakdown with fees
 * shown, where payouts go (Stripe), recent payouts, and Export.
 */
export default function SalesAndPayouts({
    statement,
}: {
    school: { name: string };
    statement: {
        month: string;
        products: ProductRow[];
        totals: PayoutTotals;
        fee_percent: number;
        account: PayoutAccount;
        own_account_allowed: boolean;
        pending: number;
        next_payout: string;
        payouts: PayoutRow[];
    };
}) {
    const { t, i18n } = useTranslation();
    const collectedBy =
        statement.account?.kind === 'standard'
            ? t('payouts.sales.ownStripe')
            : t('payouts.sales.reosStripe');

    return (
        <>
            <Head title={t('payouts.sales.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('payouts.sales.title')}
                    description={t('payouts.sales.description', {
                        collected: collectedBy,
                    })}
                    actions={
                        <Button asChild>
                            <a
                                href={
                                    exportMethod({
                                        query: { month: statement.month },
                                    }).url
                                }
                            >
                                <Download aria-hidden />
                                {t('payouts.sales.export')}
                            </a>
                        </Button>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <section
                        aria-labelledby="products"
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="products" className="font-semibold">
                            {t('payouts.sales.products')}
                        </h2>
                        {statement.products.length === 0 ? (
                            <EmptyState
                                icon={Store}
                                title={t('payouts.sales.emptyTitle')}
                                description={t('payouts.sales.empty')}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-base">
                                    <caption className="sr-only">
                                        {t('payouts.sales.products')}
                                    </caption>
                                    <thead className="text-sm text-muted-foreground uppercase">
                                        <tr className="border-b border-border">
                                            {(
                                                [
                                                    'product',
                                                    'state',
                                                    'price',
                                                    'sold',
                                                    'discounts',
                                                ] as const
                                            ).map((c) => (
                                                <th
                                                    key={c}
                                                    scope="col"
                                                    className="px-3 py-3 font-semibold"
                                                >
                                                    {t(
                                                        `payouts.sales.columns.${c}`,
                                                    )}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {statement.products.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border align-top last:border-b-0"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-3 py-3 font-medium"
                                                >
                                                    {row.product}
                                                </th>
                                                <td className="px-3 py-3">
                                                    {row.state}
                                                </td>
                                                <td className="px-3 py-3 whitespace-nowrap">
                                                    {priceLabel(
                                                        t,
                                                        row.interval,
                                                        row.amount,
                                                        i18n.language,
                                                    )}
                                                </td>
                                                <td className="px-3 py-3 tabular-nums">
                                                    {row.sold}
                                                </td>
                                                <td className="px-3 py-3 text-muted-foreground">
                                                    {row.discounts.length === 0
                                                        ? '—'
                                                        : row.discounts
                                                              .map(
                                                                  (d) =>
                                                                      `${d.sponsor} −${money(d.amount, i18n.language)}`,
                                                              )
                                                              .join(', ')}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <div className="space-y-6">
                        <section
                            aria-labelledby="payouts"
                            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                        >
                            <h2 id="payouts" className="font-semibold">
                                {t('payouts.sales.payouts')}
                            </h2>
                            <PayoutBreakdown
                                totals={statement.totals}
                                feePercent={statement.fee_percent}
                            />
                            <p className="flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                                <Info aria-hidden className="size-5 shrink-0" />
                                {t('payouts.sales.note', {
                                    amount: money(
                                        statement.pending,
                                        i18n.language,
                                    ),
                                    date: shortDate(
                                        statement.next_payout,
                                        i18n.language,
                                    ),
                                })}
                            </p>
                        </section>
                        <PayoutAccountCard
                            account={statement.account}
                            connectUrl={connect().url}
                            ownUrl={
                                statement.own_account_allowed
                                    ? connectOwn().url
                                    : null
                            }
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
                                                {p.status === 'failed'
                                                    ? ` · ${t('payouts.failed')}`
                                                    : ''}
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
