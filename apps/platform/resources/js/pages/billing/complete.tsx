import { Head, Link, usePoll } from '@inertiajs/react';
import { CircleCheck, Clock, ExternalLink } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { OrderSummary } from '@/components/order-summary';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { featureLabel } from '@/lib/billing';
import { index as billing } from '@/routes/billing';
import { index as learn } from '@/routes/learn';

type Order = {
    uuid: string;
    status: 'pending' | 'paid' | 'expired' | 'refunded';
    product: string;
    state: string;
    amount: number;
    discount: number;
    sponsor: string | null;
    tax: number | null;
    total: number | null;
    receipt_url: string | null;
    paid_at: string | null;
};

/**
 * Back from paying (screen 3, "Pay → receipt and instant unlock"): REOS has
 * already asked the gateway; paid orders show the receipt and what's
 * unlocked now. A payment still confirming refreshes itself.
 */
export default function CheckoutComplete({
    order,
    unlocked,
    tenant = null,
}: {
    /** A brokerage or school that signed up with this payment (A6.1b). */
    tenant?: { name: string; type: string } | null;
    order: Order;
    unlocked: string[];
}) {
    const { t } = useTranslation();
    const { start, stop } = usePoll(2000, {}, { autoStart: false });

    useEffect(() => {
        if (order.status === 'pending') start();
        else stop();

        return stop;
    }, [order.status, start, stop]);

    const title =
        order.status === 'paid'
            ? t('billing.complete.title')
            : order.status === 'pending'
              ? t('billing.complete.pendingTitle')
              : t('billing.complete.expiredTitle');

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-background">
                <header className="flex items-center gap-3 border-b border-border bg-card px-4 py-4 font-semibold md:px-10">
                    <BrandMark className="size-9 text-sm" />
                    {t('billing.checkout.title')}
                </header>
                <main className="mx-auto grid max-w-5xl gap-8 px-4 py-8 md:px-10 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                    <section className="space-y-4" aria-live="polite">
                        <h1 className="flex items-center gap-3 text-title font-semibold">
                            {order.status === 'paid' ? (
                                <CircleCheck
                                    aria-hidden
                                    className="size-8 text-reos-tone-success-fg"
                                />
                            ) : (
                                <Clock
                                    aria-hidden
                                    className="size-8 text-muted-foreground"
                                />
                            )}
                            {title}
                        </h1>
                        <p className="text-body">
                            {order.status === 'paid'
                                ? t('billing.complete.paid', {
                                      product: order.product,
                                  })
                                : order.status === 'pending'
                                  ? t('billing.complete.pending')
                                  : t('billing.complete.expired')}
                        </p>
                        {tenant ? (
                            <p className="rounded-reos-md bg-reos-tone-success-bg p-4 text-body text-reos-tone-success-fg">
                                {t('signup.ready', { name: tenant.name })}
                            </p>
                        ) : null}
                        {unlocked.length > 0 ? (
                            <div className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                                <h2 className="font-semibold">
                                    {t('billing.complete.unlocked')}
                                </h2>
                                <ul className="list-inside list-disc text-body">
                                    {unlocked.map((feature) => (
                                        <li key={feature}>
                                            {featureLabel(t, feature)}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : null}
                        <div className="flex flex-wrap gap-3">
                            {order.status === 'paid' && tenant ? (
                                <Button asChild>
                                    <Link href={dashboard()}>
                                        {t('signup.open', {
                                            name: tenant.name,
                                        })}
                                    </Link>
                                </Button>
                            ) : order.status === 'paid' ? (
                                <Button asChild>
                                    <Link href={learn()}>
                                        {t('billing.complete.learn')}
                                    </Link>
                                </Button>
                            ) : null}
                            {order.receipt_url ? (
                                <Button variant="outline" asChild>
                                    <a
                                        href={order.receipt_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink aria-hidden />
                                        {t('billing.complete.receipt')}
                                    </a>
                                </Button>
                            ) : null}
                            <Button variant="outline" asChild>
                                <Link href={billing()}>
                                    {t('billing.complete.billing')}
                                </Link>
                            </Button>
                        </div>
                    </section>
                    <OrderSummary
                        product={order.product}
                        amount={order.amount}
                        discount={order.discount}
                        sponsor={order.sponsor}
                        tax={order.tax}
                        state={order.state}
                    />
                </main>
            </div>
        </>
    );
}
