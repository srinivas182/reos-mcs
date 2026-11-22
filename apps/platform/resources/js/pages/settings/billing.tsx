import { Head, router } from '@inertiajs/react';
import { CreditCard, ExternalLink, Receipt } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { PlanCard } from '@/components/plan-card';
import { Button } from '@/components/ui/button';
import type { PriceView } from '@/lib/billing';
import { featureLabel, money } from '@/lib/billing';
import { portal } from '@/routes/billing';

type Plan = PriceView & {
    charge: number;
    sponsor: string | null;
    ai_help: string | null;
};

/**
 * Plan and billing (B5.1; web journey screen 29 and each role's equivalent):
 * plans for the person's role and state, what they have now, their
 * subscriptions (cards and cancelling at the gateway's portal) and every
 * receipt. Buying always happens here on the web, never in the app.
 */
export default function Billing({
    state,
    plans,
    access,
    subscriptions,
    orders,
}: {
    state: string;
    plans: Plan[];
    access: {
        feature: string;
        source: 'order' | 'subscription' | 'sponsor' | 'admin';
        state: string | null;
        ends_at: string | null;
    }[];
    subscriptions: {
        id: number;
        product: string;
        status: 'active' | 'past_due' | 'canceled';
        current_period_end: string | null;
        grace_ends_at: string | null;
    }[];
    orders: {
        uuid: string;
        product: string;
        status: 'paid' | 'refunded';
        total: number | null;
        paid_at: string | null;
        receipt_url: string | null;
    }[];
}) {
    const { t, i18n } = useTranslation();
    const date = (iso: string) =>
        new Date(iso).toLocaleDateString(i18n.language, {
            dateStyle: 'medium',
        });

    return (
        <>
            <Head title={t('billing.index.title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('billing.index.access')}
                    description={t('billing.index.description')}
                />
                {access.length === 0 ? (
                    <p className="text-body text-muted-foreground">
                        {t('billing.index.noAccess')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                        {access.map((row, i) => (
                            <li
                                key={`${row.feature}-${i}`}
                                className="flex flex-wrap justify-between gap-2 px-4 py-3"
                            >
                                <span className="font-medium">
                                    {featureLabel(t, row.feature)}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {[
                                        t(
                                            `billing.index.sources.${row.source}`,
                                        ),
                                        row.ends_at
                                            ? t('billing.index.until', {
                                                  date: date(row.ends_at),
                                              })
                                            : t('billing.index.forever'),
                                    ].join(' · ')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title={t('billing.index.plansTitle', { state })}
                />
                {plans.length === 0 ? (
                    <EmptyState
                        icon={CreditCard}
                        title={t('billing.index.noPlansTitle')}
                        description={t('billing.index.noPlans')}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {plans.map((plan) => (
                            <PlanCard
                                key={plan.id}
                                price={plan}
                                charge={plan.charge}
                                sponsor={plan.sponsor}
                                aiHelp={plan.ai_help}
                            />
                        ))}
                    </div>
                )}
            </div>

            {subscriptions.length > 0 ? (
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('billing.index.subscriptions')}
                    />
                    <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                        {subscriptions.map((s) => (
                            <li key={s.id} className="space-y-1 px-4 py-3">
                                <p className="font-medium">
                                    {s.product} ·{' '}
                                    {t(`billing.index.statuses.${s.status}`)}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {s.status === 'past_due' && s.grace_ends_at
                                        ? t('billing.index.grace', {
                                              date: date(s.grace_ends_at),
                                          })
                                        : s.status === 'active' &&
                                            s.current_period_end
                                          ? t('billing.index.renews', {
                                                date: date(
                                                    s.current_period_end,
                                                ),
                                            })
                                          : null}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <Button
                        variant="outline"
                        onClick={() => router.post(portal().url)}
                    >
                        <CreditCard aria-hidden />
                        {t('billing.index.manage')}
                    </Button>
                </div>
            ) : null}

            <div className="space-y-4">
                <Heading variant="small" title={t('billing.index.orders')} />
                {orders.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title={t('billing.index.noOrders')}
                        description={t('billing.index.noAccess')}
                    />
                ) : (
                    <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                        {orders.map((o) => (
                            <li
                                key={o.uuid}
                                className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                            >
                                <span>
                                    <span className="font-medium">
                                        {o.product}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {[
                                            o.paid_at ? date(o.paid_at) : null,
                                            o.total !== null
                                                ? money(o.total, i18n.language)
                                                : null,
                                            t(
                                                `billing.index.orderStatus.${o.status}`,
                                            ),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                </span>
                                {o.receipt_url ? (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={o.receipt_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink aria-hidden />
                                            {t('billing.index.receipt')}
                                        </a>
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
