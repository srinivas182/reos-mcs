import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { PriceView } from '@/lib/billing';
import { money, priceLabel } from '@/lib/billing';
import { show as checkout } from '@/routes/checkout';

/**
 * A plan on Plan and billing (B5.1; web journey screen 29): its price in
 * the person's state, what it includes, any sponsor paying part, and
 * Choose plan, which opens checkout on reos.com (screen 3).
 */
export function PlanCard({
    price,
    charge,
    sponsor,
    aiHelp,
}: {
    price: PriceView;
    charge: number;
    sponsor: string | null;
    aiHelp: string | null;
}) {
    const { t, i18n } = useTranslation();

    return (
        <article className="flex flex-col gap-3 rounded-reos-lg border border-border bg-card p-5">
            <div className="space-y-1">
                <h3 className="font-semibold">{price.product}</h3>
                <p className="text-title font-semibold">
                    {priceLabel(t, price.interval, charge, i18n.language)}
                </p>
                {sponsor ? (
                    <p className="text-sm text-reos-tone-success-fg">
                        {t('billing.index.sponsorPays', { sponsor })} ·{' '}
                        <s>{money(price.amount, i18n.language)}</s>
                    </p>
                ) : null}
                {price.description ? (
                    <p className="text-body text-muted-foreground">
                        {price.description}
                    </p>
                ) : null}
            </div>
            <ul className="space-y-1.5">
                {price.includes.map((item) => (
                    <li key={item} className="flex gap-2 text-body">
                        <Check
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                        />
                        {item}
                    </li>
                ))}
                {aiHelp ? (
                    <li className="flex gap-2 text-body">
                        <Check
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                        />
                        {aiHelp}
                    </li>
                ) : null}
            </ul>
            <Button asChild className="mt-auto">
                <Link href={checkout(price.id)}>
                    {t('billing.index.choose')}
                </Link>
            </Button>
        </article>
    );
}
