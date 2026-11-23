import { Link } from '@inertiajs/react';
import {
    BedDouble,
    CalendarDays,
    ChartColumn,
    Home,
    Share2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { money } from '@/lib/billing';
import type { ListingRow } from './types';

const TONES: Record<string, string> = {
    coming_soon: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    active: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    under_contract: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    pending: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    closed: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

/**
 * One MLS listing in My listings (B10.1): photo, status, price, address,
 * beds, baths and size, the agent's role, the MLS number and the next open
 * house. Sold listings show the closing price.
 */
export function ListingCard({
    listing,
    onPost,
    cmaHref,
    hideRole = false,
}: {
    listing: ListingRow;
    /** Opens the listing post (shown when the MLS allows a post). */
    onPost?: () => void;
    /** The listing's CMA (B10.2b). */
    cmaHref?: string;
    /** On the public page: no "Listing agent". */
    hideRole?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const dollars = (value: number) => money(value * 100, i18n.language);
    const when = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(iso));
    const facts = [
        listing.beds === null
            ? null
            : t('listings.beds', { count: listing.beds }),
        listing.baths === null
            ? null
            : t('listings.baths', { count: listing.baths }),
        listing.area === null
            ? null
            : t('listings.area', {
                  area: new Intl.NumberFormat(i18n.language).format(
                      listing.area,
                  ),
              }),
    ].filter((fact): fact is string => fact !== null);

    return (
        <article className="overflow-hidden rounded-reos-lg border border-border bg-card">
            <div className="relative aspect-[4/3] bg-muted">
                {listing.photo ? (
                    <img
                        src={listing.photo.url}
                        alt={
                            listing.photo.caption ??
                            t('listings.photo', { address: listing.address })
                        }
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground">
                        <Home aria-hidden className="size-8" />
                        <span>{t('listings.noPhoto')}</span>
                    </div>
                )}
                <span
                    className={`absolute top-3 left-3 rounded-reos-pill px-3 py-1 text-sm font-semibold ${TONES[listing.status] ?? TONES.closed}`}
                >
                    {t(`listings.status.${listing.status}`, {
                        defaultValue: listing.status,
                    })}
                </span>
            </div>
            <div className="space-y-2 p-5">
                <p className="text-title font-semibold tabular-nums">
                    {listing.status === 'closed' && listing.close_price !== null
                        ? t('listings.soldFor', {
                              price: dollars(listing.close_price),
                          })
                        : listing.price === null
                          ? '—'
                          : dollars(listing.price)}
                </p>
                <h3 className="text-base font-semibold">{listing.address}</h3>
                {facts.length > 0 ? (
                    <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-base text-muted-foreground">
                        <BedDouble aria-hidden className="size-4" />
                        <span>{facts.join(' · ')}</span>
                    </p>
                ) : null}
                <p className="text-sm text-muted-foreground">
                    {hideRole ? '' : t(`listings.role.${listing.role}`)}
                    {listing.listing_id
                        ? `${hideRole ? '' : ' · '}${t('listings.mlsNumber', { mls: listing.short_name, id: listing.listing_id })}`
                        : ''}
                    {listing.days_on_market !== null
                        ? ` · ${t('listings.daysOnMarket', { count: listing.days_on_market })}`
                        : ''}
                </p>
                <p className="text-sm text-muted-foreground">
                    {listing.attribution}
                </p>
                {listing.open_house ? (
                    <p className="flex items-center gap-2 text-base font-medium">
                        <CalendarDays aria-hidden className="size-4" />
                        {t(
                            listing.open_house.kind === 'broker'
                                ? 'listings.brokerOpen'
                                : 'listings.openHouse',
                            { time: when(listing.open_house.starts_at) },
                        )}
                    </p>
                ) : null}
                <div className="flex flex-wrap gap-3">
                    {onPost && listing.can_post ? (
                        <Button
                            variant="outline"
                            onClick={onPost}
                            aria-label={t('listings.post.createFor', {
                                address: listing.address,
                            })}
                        >
                            <Share2 aria-hidden />
                            {t('listings.post.create')}
                        </Button>
                    ) : null}
                    {cmaHref ? (
                        <Button asChild variant="outline">
                            <Link
                                href={cmaHref}
                                aria-label={t('listings.cma.openFor', {
                                    address: listing.address,
                                })}
                            >
                                <ChartColumn aria-hidden />
                                {t('listings.cma.open')}
                            </Link>
                        </Button>
                    ) : null}
                </div>
            </div>
        </article>
    );
}
