import { Link } from '@inertiajs/react';
import { CalendarClock, Languages, Star } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { money } from '@/lib/billing';
import { instructor as instructorRoute } from '@/routes/bookings';
import type { InstructorOffer } from './types';

/** "Mon, Nov 24, 1:00 PM" in the viewer's time zone. */
export function useSlotText() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Date(iso).toLocaleString(i18n.language, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
}

/** One instructor to book (candidate screen 18): rating, rate, languages and next free hour. */
export function InstructorCard({ offer }: { offer: InstructorOffer }) {
    const { t, i18n } = useTranslation();
    const slot = useSlotText();

    return (
        <Link
            href={instructorRoute(offer.user_id).url}
            className="block space-y-2 rounded-reos-lg border border-border bg-card p-4 hover:border-primary"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-semibold">{offer.name}</p>
                    {offer.school ? (
                        <p className="text-sm text-muted-foreground">
                            {offer.school}
                        </p>
                    ) : null}
                </div>
                <p className="text-sm font-semibold">
                    {offer.price_30 === 0 && offer.price_60 === 0
                        ? t('bookings.included')
                        : t('bookings.from', {
                              price: money(
                                  Math.min(offer.price_30, offer.price_60) ||
                                      Math.max(offer.price_30, offer.price_60),
                                  i18n.language,
                              ),
                          })}
                </p>
            </div>
            <p className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                <span className="flex items-center gap-1">
                    <Star aria-hidden className="size-4" />
                    {offer.rating === null
                        ? t('bookings.new')
                        : t('bookings.rating', {
                              rating: offer.rating,
                              count: offer.ratings,
                          })}
                </span>
                <span className="flex items-center gap-1">
                    <Languages aria-hidden className="size-4" />
                    {offer.languages
                        .map((l) => t(`bookings.languages.${l as 'en' | 'es'}`))
                        .join(', ')}
                </span>
                {offer.next_slot ? (
                    <span className="flex items-center gap-1">
                        <CalendarClock aria-hidden className="size-4" />
                        {t('bookings.next', { time: slot(offer.next_slot) })}
                    </span>
                ) : null}
            </p>
            {offer.topics.length > 0 ? (
                <p className="text-sm">{offer.topics.join(' · ')}</p>
            ) : null}
        </Link>
    );
}
