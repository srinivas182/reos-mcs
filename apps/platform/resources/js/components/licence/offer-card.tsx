import { Award, GraduationCap, MapPin, Sparkles, Star } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { OfferRow } from '@/components/licence/types';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/billing';

/** One brokerage's offer (screen 17): split, fees, training, mentoring and rating; Accept or Decline. */
export function OfferCard({
    offer,
    onAccept,
    onDecline,
}: {
    offer: OfferRow;
    onAccept?: () => void;
    onDecline?: () => void;
}) {
    const { t, i18n } = useTranslation();

    return (
        <article className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h3 className="text-lg font-semibold">{offer.brokerage}</h3>
                    <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <MapPin aria-hidden className="size-4" />
                            {t('licence.offers.office', {
                                city: offer.office_city,
                            })}
                        </span>
                        {offer.fit ? (
                            <span className="rounded-reos-pill bg-reos-tone-success-bg px-2.5 py-0.5 font-semibold text-reos-tone-success-fg">
                                {t('licence.offers.fit')}
                            </span>
                        ) : null}
                        <span className="inline-flex items-center gap-1">
                            <Star aria-hidden className="size-4" />
                            {offer.rating === null
                                ? t('licence.offers.noRating')
                                : t('licence.offers.rating', {
                                      rating: offer.rating,
                                      count: offer.ratings,
                                  })}
                        </span>
                    </p>
                </div>
                <p className="text-title font-semibold tabular-nums">
                    {t('licence.offers.split', {
                        percent: offer.split_percent,
                    })}
                </p>
            </div>
            <ul className="space-y-1 text-base">
                <li>
                    {offer.monthly_fee_cents === 0
                        ? t('licence.offers.noFee')
                        : t('licence.offers.fee', {
                              fee: money(
                                  offer.monthly_fee_cents,
                                  i18n.language,
                              ),
                          })}
                    {offer.fees ? ` · ${offer.fees}` : ''}
                </li>
                {offer.training ? (
                    <li className="flex items-center gap-2">
                        <GraduationCap aria-hidden className="size-4" />
                        {t('licence.offers.training')}: {offer.training}
                    </li>
                ) : null}
                {offer.mentoring ? (
                    <li className="flex items-center gap-2">
                        <Award aria-hidden className="size-4" />
                        {t('licence.offers.mentoring')}
                    </li>
                ) : null}
                {offer.ai_help ? (
                    <li className="flex items-center gap-2">
                        <Sparkles aria-hidden className="size-4" />
                        {t('licence.offers.aiHelp')}
                    </li>
                ) : null}
            </ul>
            {offer.message ? (
                <p className="text-base text-muted-foreground">
                    “{offer.message}”
                </p>
            ) : null}
            {offer.status === 'accepted' ? (
                <p className="text-base font-medium text-reos-tone-success-fg">
                    {t('licence.offers.acceptedNote', {
                        brokerage: offer.brokerage,
                    })}
                </p>
            ) : onAccept && onDecline ? (
                <div className="flex flex-wrap gap-3">
                    <Button onClick={onAccept}>
                        {t('licence.offers.accept')}
                    </Button>
                    <Button variant="outline" onClick={onDecline}>
                        {t('licence.offers.decline')}
                    </Button>
                </div>
            ) : null}
        </article>
    );
}
