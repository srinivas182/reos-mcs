import { useTranslation } from 'react-i18next';
import { money } from '@/lib/billing';

export type PayoutTotals = {
    gross: number;
    discounts: number;
    platform_fee: number;
    payees: number;
    net: number;
};

/**
 * A school's payout breakdown for a month (screen 53, B5.2b): gross sales,
 * sponsor discounts, REOS's platform fee (which covers card fees),
 * instructor and mentor shares, and what the school is paid.
 */
export function PayoutBreakdown({
    totals,
    feePercent,
}: {
    totals: PayoutTotals;
    feePercent: number;
}) {
    const { t, i18n } = useTranslation();
    const rows: [string, number][] = [
        [t('payouts.breakdown.gross'), totals.gross],
        [t('payouts.breakdown.discounts'), -totals.discounts],
        [
            t('payouts.breakdown.fee', { percent: feePercent }),
            -totals.platform_fee,
        ],
        [t('payouts.breakdown.payees'), -totals.payees],
    ];

    return (
        <dl className="divide-y divide-border">
            {rows.map(([label, value]) => (
                <div key={label} className="flex justify-between gap-4 py-2">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="text-right font-medium whitespace-nowrap tabular-nums">
                        {value < 0
                            ? `−${money(-value, i18n.language)}`
                            : money(value, i18n.language)}
                    </dd>
                </div>
            ))}
            <div className="flex justify-between gap-4 py-2">
                <dt className="font-semibold">{t('payouts.breakdown.net')}</dt>
                <dd className="text-right font-semibold tabular-nums">
                    {money(totals.net, i18n.language)}
                </dd>
            </div>
        </dl>
    );
}
