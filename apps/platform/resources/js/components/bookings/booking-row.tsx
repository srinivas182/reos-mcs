import { Link } from '@inertiajs/react';
import { ListTodo, Star, Video } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/billing';
import { show as sessionRoute } from '@/routes/sessions';
import { useSlotText } from './instructor-card';
import type { Booking } from './types';

/**
 * A booked session (screen 18, i14): who, when and what; join; pay for a
 * held one; cancel (saying whether it's refunded); rate once it ends.
 */
export function BookingRow({
    booking,
    onCancel,
    onRate,
    onTasks,
}: {
    booking: Booking;
    onCancel: () => void;
    onRate: (stars: number) => void;
    /** The instructor sends follow-up tasks (screen i16). */
    onTasks?: () => void;
}) {
    const { t, i18n } = useTranslation();
    const slot = useSlotText();
    const [stars, setStars] = useState(0);

    return (
        <li className="space-y-2 rounded-reos-lg border border-border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="font-semibold">
                        {booking.as === 'student'
                            ? t('bookings.with', { name: booking.instructor })
                            : t('bookings.with', { name: booking.student })}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {`${slot(booking.starts_at)} · ${t('bookings.minutes', { count: booking.minutes })} · ${booking.topic}`}
                    </p>
                </div>
                <span className="rounded-reos-pill bg-muted px-2.5 py-0.5 text-sm font-semibold">
                    {t(`bookings.statuses.${booking.status}`)}
                </span>
            </div>
            <div className="flex flex-wrap gap-2">
                {booking.status === 'confirmed' && booking.session ? (
                    <Button asChild>
                        <Link href={sessionRoute(booking.session).url}>
                            <Video aria-hidden />
                            {t('bookings.join')}
                        </Link>
                    </Button>
                ) : null}
                {booking.status === 'held' && booking.checkout_url ? (
                    <Button asChild>
                        <a href={booking.checkout_url}>
                            {t('bookings.pay', {
                                price: money(booking.price, i18n.language),
                            })}
                        </a>
                    </Button>
                ) : null}
                {booking.as === 'instructor' &&
                booking.status === 'completed' &&
                !booking.tasks_sent &&
                onTasks ? (
                    <Button variant="outline" onClick={onTasks}>
                        <ListTodo aria-hidden />
                        {t('bookings.tasks.send')}
                    </Button>
                ) : null}
                {booking.can_cancel ? (
                    <Button variant="outline" onClick={onCancel}>
                        {booking.price > 0 && !booking.refund_on_cancel
                            ? t('bookings.cancelNoRefund')
                            : t('bookings.cancel')}
                    </Button>
                ) : null}
            </div>
            {booking.can_rate ? (
                <fieldset className="flex flex-wrap items-center gap-1">
                    <legend className="sr-only">{t('bookings.rate')}</legend>
                    <span className="mr-2 text-sm">{t('bookings.rate')}</span>
                    {[1, 2, 3, 4, 5].map((n) => (
                        <Button
                            key={n}
                            variant="ghost"
                            aria-label={t('bookings.stars', { count: n })}
                            aria-pressed={stars === n}
                            onClick={() => {
                                setStars(n);
                                onRate(n);
                            }}
                        >
                            <Star
                                aria-hidden
                                className={
                                    n <= stars
                                        ? 'size-5 fill-current text-primary'
                                        : 'size-5'
                                }
                            />
                        </Button>
                    ))}
                </fieldset>
            ) : null}
        </li>
    );
}
