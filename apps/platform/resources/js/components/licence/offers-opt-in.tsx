import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Journey } from '@/components/licence/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { offers as offersPage } from '@/routes/licence';

/**
 * Broker offers from the tracker (A10.3b, screen 17, decision 5): opt in
 * with the city to work in; brokerages see a first name, city and progress
 * only, and contact details only on Accept.
 */
export function OffersOptIn({
    state,
    offers,
    onSave,
}: {
    state: string;
    offers: Journey['offers'];
    onSave: (on: boolean, city: string | null) => void;
}) {
    const { t } = useTranslation();
    const [on, setOn] = useState(offers.opted_in);
    const [city, setCity] = useState(offers.city ?? '');

    return (
        <section
            aria-labelledby="broker-offers"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2
                id="broker-offers"
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <Building2 aria-hidden className="size-5" />
                {t('licence.offers.title')}
            </h2>
            <div className="flex min-h-12 items-center justify-between gap-3">
                <Label htmlFor="offers-on" className="text-base">
                    {t('licence.offers.optIn')}
                </Label>
                <Switch id="offers-on" checked={on} onCheckedChange={setOn} />
            </div>
            <p className="text-sm text-muted-foreground">
                {t('licence.offers.optInHint', { state })}
            </p>
            {on ? (
                <div className="grid max-w-sm gap-2">
                    <Label htmlFor="offers-city">
                        {t('licence.offers.city')}
                    </Label>
                    <Input
                        id="offers-city"
                        value={city}
                        maxLength={120}
                        onChange={(e) => setCity(e.target.value)}
                    />
                </div>
            ) : null}
            <div className="flex flex-wrap gap-3">
                <Button
                    variant="outline"
                    disabled={
                        on === offers.opted_in &&
                        (city.trim() || null) === offers.city
                    }
                    onClick={() => onSave(on, city.trim() || null)}
                >
                    {t('licence.offers.save')}
                </Button>
                {offers.opted_in ? (
                    <Button asChild>
                        <Link href={offersPage().url}>
                            {offers.open > 0
                                ? t('licence.offers.open', {
                                      count: offers.open,
                                  })
                                : t('licence.offers.see')}
                        </Link>
                    </Button>
                ) : null}
            </div>
        </section>
    );
}
