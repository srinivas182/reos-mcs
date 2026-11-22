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
