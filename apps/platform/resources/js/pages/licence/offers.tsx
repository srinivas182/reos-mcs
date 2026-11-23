import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { OfferCard } from '@/components/licence/offer-card';
import type { CandidateOffers } from '@/components/licence/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { show } from '@/routes/licence';
import { accept, decline } from '@/routes/licence/offers';

/**
 * Broker offers (candidate screen 17, A10.3b): ranked by fit, rating and
 * terms; Accept shares contact details with that brokerage; Decline hides it.
 */
export default function OffersPage({ offers }: { offers: CandidateOffers }) {
    const { t } = useTranslation();
    const [error, setError] = useState<string | null>(null);

    const answer = async (url: string) => {
        try {
            await postJson(url);
            setError(null);
            router.reload({ only: ['offers'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('licence.offers.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    back={
                        <Link
                            href={show().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('licence.title')}
                        </Link>
                    }
                    title={t('licence.offers.title')}
                    description={t('licence.offers.purposeShort')}
                />
                {error ? <InputError message={error} /> : null}
                {!offers.opted_in ? (
                    <EmptyState
                        icon={Building2}
                        title={t('licence.offers.off')}
                        description={t('licence.offers.offBody')}
                        action={
                            <Button asChild>
                                <Link href={show().url}>
                                    {t('licence.title')}
                                </Link>
                            </Button>
                        }
                    />
                ) : offers.offers.length === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title={t('licence.offers.empty')}
                        description={t('licence.offers.emptyBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {offers.offers.map((o) => (
                            <li key={o.id}>
                                <OfferCard
                                    offer={o}
                                    onAccept={() =>
                                        void answer(accept(o.id).url)
                                    }
                                    onDecline={() =>
                                        void answer(decline(o.id).url)
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
