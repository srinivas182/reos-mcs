import type { TFunction } from 'i18next';

/** Plans, prices and checkout (B5.1, ADR 0021). Amounts are in cents, USD. */
export type Interval = 'once' | 'month' | 'year';
export type ProductStatus = 'draft' | 'live' | 'retired';
export type ProductKind =
    | 'plan'
    | 'course'
    | 'package'
    | 'credit_pack'
    | 'seat_pack';
export type Buyer =
    | 'candidate'
    | 'agent'
    | 'broker'
    | 'school'
    | 'provider'
    | 'association';

export type PriceView = {
    id: number;
    product: string;
    kind: ProductKind;
    description: string | null;
    includes: string[];
    state: string;
    interval: Interval;
    amount: number;
};

/** "$499.00" in the person's language. */
export function money(cents: number, locale: string): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
    }).format(cents / 100);
}

/** "$499", "$79 / month" or "$790 / year". */
export function priceLabel(
    t: TFunction,
    interval: Interval,
    cents: number,
    locale: string,
): string {
    const amount = money(cents, locale);

    if (interval === 'month') return t('billing.checkout.perMonth', { amount });
    if (interval === 'year') return t('billing.checkout.perYear', { amount });

    return amount;
}

/** A feature key in plain words ("learn.qualifying" → "Qualifying courses"), or the key itself. */
export function featureLabel(t: TFunction, feature: string): string {
    const key = `billing.features.${feature}`;
    const label = t(key as never) as unknown as string;

    return label === key ? feature : label;
}

/** Payment gateways REOS takes payments through (B5.2a, screen 66). */
export type GatewayId = 'stripe' | 'paypal' | 'authorize-net';

/** What checkout gets back when it starts an order. */
export type StartedCheckout = {
    order: string;
    gateway: GatewayId;
    client_secret: string | null;
    redirect: {
        url: string;
        method: 'GET' | 'POST';
        fields: Record<string, string>;
    } | null;
    simulator: boolean;
    tax: number | null;
    total: number;
};

/** Sends the buyer to a gateway's own hosted page (a link, or a form to post). */
export function goToGateway(
    redirect: NonNullable<StartedCheckout['redirect']>,
): void {
    if (redirect.method === 'GET') {
        window.location.assign(redirect.url);

        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = redirect.url;

    for (const [name, value] of Object.entries(redirect.fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
}
