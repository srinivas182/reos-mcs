import { Head, router } from '@inertiajs/react';
import {
    EmbeddedCheckout,
    EmbeddedCheckoutProvider,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { Check, FlaskConical, Lock, ShieldCheck } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { OrderSummary } from '@/components/order-summary';
import { Button } from '@/components/ui/button';
import { HostedPayment } from '@/components/hosted-payment';
import type { GatewayId, PriceView, StartedCheckout } from '@/lib/billing';
import { goToGateway, money } from '@/lib/billing';
import { postJson } from '@/lib/content';
import { simulate, start } from '@/routes/checkout';

/**
 * Checkout on reos.com (web journey screen 3, B5.1, B5.2a): the product and
 * what's included, the payment fields from the gateway that serves this
 * state and product (Stripe's own embedded checkout; PayPal's or
 * Authorize.net's hosted page after the ZIP code for tax; or the
 * Simulator's test panel), and the order summary with the sponsor discount
 * and tax. Card data never touches REOS. Paying shows the receipt and
 * unlocks the web and the app at once.
 */
export default function CheckoutPage({
    price,
    quote,
    gateway,
}: {
    price: PriceView;
    quote: {
        amount: number;
        discount: number;
        sponsor: string | null;
        charge: number;
    };
    gateway: {
        id: GatewayId;
        simulator: boolean;
        needs_postal_code: boolean;
        publishable_key: string | null;
    } | null;
}) {
    const { t, i18n } = useTranslation();
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [tax, setTax] = useState<number | null>(null);
    const stripe = useMemo(
        () =>
            gateway?.publishable_key
                ? loadStripe(gateway.publishable_key)
                : null,
        [gateway?.publishable_key],
    );
    const fetchClientSecret = useCallback(async () => {
        const started = await postJson<StartedCheckout>(start(price.id).url);

        return started.client_secret ?? '';
    }, [price.id]);

    const paySimulated = async () => {
        setBusy(true);
        setError(null);

        try {
            const started = await postJson<StartedCheckout>(
                start(price.id).url,
            );
            router.post(simulate(started.order).url);
        } catch (e) {
            setError(
                e instanceof Error && !e.message.startsWith('HTTP')
                    ? e.message
                    : t('billing.checkout.startFailed'),
            );
            setBusy(false);
        }
    };

    const startWithPostalCode = async (postalCode: string) => {
        const started = await postJson<StartedCheckout>(start(price.id).url, {
            postal_code: postalCode,
        });
        setTax(started.tax);

        return started;
    };

    const payHosted = (started: StartedCheckout) => {
        if (started.simulator) {
            router.post(simulate(started.order).url);
        } else if (started.redirect) {
            goToGateway(started.redirect);
        }
    };

    return (
        <>
            <Head title={t('billing.checkout.title')} />
            <div className="min-h-screen bg-background">
                <header className="flex items-center justify-between border-b border-border bg-card px-4 py-4 md:px-10">
                    <span className="flex items-center gap-3 font-semibold">
                        <BrandMark className="size-9 text-sm" />
                        {t('billing.checkout.title')}
                    </span>
                    <span className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Lock aria-hidden className="size-4" />
                        {t('billing.checkout.secure')}
                    </span>
                </header>

                <main className="mx-auto grid max-w-6xl gap-8 px-4 py-8 md:px-10 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <h1 className="text-title font-semibold">
                                {price.product}
                            </h1>
                            <p className="text-body text-muted-foreground">
                                {[
                                    t(`plans.kinds.${price.kind}`),
                                    t(
                                        `billing.checkout.intervals.${price.interval}`,
                                    ),
                                    t('billing.checkout.setBy', {
                                        state: price.state,
                                    }),
                                ].join(' · ')}
                            </p>
                        </div>
                        {price.includes.length > 0 ? (
                            <section
                                aria-label={t('billing.checkout.includes')}
                                className="rounded-reos-lg border border-border bg-card p-5"
                            >
                                <ul className="space-y-2">
                                    {price.includes.map((item) => (
                                        <li
                                            key={item}
                                            className="flex gap-2 text-body"
                                        >
                                            <Check
                                                aria-hidden
                                                className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                                            />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ) : null}

                        <section
                            aria-labelledby="payment"
                            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                        >
                            <h2 id="payment" className="font-semibold">
                                {t('billing.checkout.paymentMethod')}
                            </h2>
                            {gateway === null ? (
                                <p
                                    role="alert"
                                    className="text-body text-destructive"
                                >
                                    {t('billing.errors.paused')}
                                </p>
                            ) : gateway.needs_postal_code ? (
                                <HostedPayment
                                    gateway={gateway.id}
                                    simulator={gateway.simulator}
                                    onStart={startWithPostalCode}
                                    onPay={payHosted}
                                />
                            ) : gateway.simulator ? (
                                <div className="space-y-3">
                                    <p className="flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                                        <FlaskConical
                                            aria-hidden
                                            className="size-5 shrink-0"
                                        />
                                        {t('billing.checkout.simulator')}
                                    </p>
                                    <p className="text-body">
                                        {t('billing.checkout.testCard')}:{' '}
                                        <span className="font-mono">
                                            4242 4242 4242 4242 · 08/29 · 123
                                        </span>
                                    </p>
                                    <Button
                                        onClick={() => void paySimulated()}
                                        disabled={busy}
                                        className="w-full"
                                    >
                                        {t('billing.checkout.simulatorPay', {
                                            amount: money(
                                                quote.charge,
                                                i18n.language,
                                            ),
                                        })}
                                    </Button>
                                </div>
                            ) : stripe ? (
                                <EmbeddedCheckoutProvider
                                    stripe={stripe}
                                    options={{ fetchClientSecret }}
                                >
                                    <EmbeddedCheckout />
                                </EmbeddedCheckoutProvider>
                            ) : (
                                <p
                                    role="alert"
                                    className="text-body text-destructive"
                                >
                                    {t('billing.checkout.startFailed')}
                                </p>
                            )}
                            {error ? (
                                <p
                                    role="alert"
                                    className="text-body text-destructive"
                                >
                                    {error}
                                </p>
                            ) : null}
                        </section>
                        <p className="text-sm text-muted-foreground">
                            {t('billing.checkout.processedBy', {
                                state: price.state,
                                gateway: t(
                                    `gateways.names.${gateway?.id ?? 'stripe'}`,
                                ),
                            })}
                        </p>
                    </div>

                    <aside className="space-y-4">
                        <OrderSummary
                            product={price.product}
                            amount={quote.amount}
                            discount={quote.discount}
                            sponsor={quote.sponsor}
                            tax={tax}
                            state={price.state}
                        />
                        <p className="flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                            <ShieldCheck
                                aria-hidden
                                className="size-5 shrink-0"
                            />
                            {t('billing.checkout.webOnly')}
                        </p>
                    </aside>
                </main>
            </div>
        </>
    );
}
