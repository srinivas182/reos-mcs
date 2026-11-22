import { useTranslation } from 'react-i18next';
import { money } from '@/lib/billing';

/**
 * The order summary on checkout (web journey screen 3, B5.1): the price,
 * the sponsor's discount (applied automatically), sales tax and the total.
 * Before paying, tax shows "Calculated at payment": the gateway works it
 * out from the billing address.
 */
export function OrderSummary({
    product,
    amount,
    discount,
    sponsor,
    tax,
    state,
}: {
    product: string;
    amount: number;
    discount: number;
    sponsor: string | null;
    /** Null until the gateway has worked it out. */
    tax: number | null;
    state: string;
}) {
    const { t, i18n } = useTranslation();
    const total = amount - discount + (tax ?? 0);

    return (
        <section
            aria-labelledby="order-summary"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2 id="order-summary" className="font-semibold">
                {t('billing.checkout.summary')}
            </h2>
            <dl className="divide-y divide-border text-body">
                <div className="flex justify-between gap-4 py-2">
                    <dt>{product}</dt>
                    <dd className="font-semibold tabular-nums">
                        {money(amount, i18n.language)}
                    </dd>
                </div>
                {discount > 0 ? (
                    <div className="flex justify-between gap-4 py-2">
                        <dt>
                            {t('billing.checkout.discount', {
                                sponsor: sponsor ?? '',
                            })}
                        </dt>
                        <dd className="font-semibold tabular-nums">
                            −{money(discount, i18n.language)}
                        </dd>
                    </div>
                ) : null}
                <div className="flex justify-between gap-4 py-2">
                    <dt>{t('billing.checkout.tax', { state })}</dt>
                    <dd className="tabular-nums">
                        {tax === null
                            ? t('billing.checkout.taxLater')
                            : money(tax, i18n.language)}
                    </dd>
                </div>
                <div className="flex justify-between gap-4 py-2 font-semibold">
                    <dt>
                        {tax === null
                            ? t('billing.checkout.totalBeforeTax')
                            : t('billing.checkout.total')}
                    </dt>
                    <dd className="tabular-nums">
                        {money(total, i18n.language)}
                    </dd>
                </div>
            </dl>
        </section>
    );
}
