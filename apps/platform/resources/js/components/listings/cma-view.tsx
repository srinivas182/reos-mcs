import { useTranslation } from 'react-i18next';
import { money } from '@/lib/billing';
import type { Cma } from './types';

/**
 * A CMA (B10.2b): the suggested range from what sold, the median price per
 * square foot, and the comparables nearest first, each adjusted to the
 * subject's size, with each listing's MLS attribution. Prints as a page.
 */
export function CmaView({ cma }: { cma: Cma }) {
    const { t, i18n } = useTranslation();
    const dollars = (value: number | null) =>
        value === null ? '—' : money(value * 100, i18n.language);
    const number = (value: number | null) =>
        value === null
            ? '—'
            : new Intl.NumberFormat(i18n.language).format(value);

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-3">
                <section className="space-y-1 rounded-reos-lg border border-border bg-card p-5 md:col-span-2">
                    <h2 className="text-base font-semibold text-muted-foreground">
                        {t('listings.cma.suggested')}
                    </h2>
                    <p className="text-title font-semibold tabular-nums">
                        {cma.low === null || cma.high === null
                            ? '—'
                            : `${dollars(cma.low)} – ${dollars(cma.high)}`}
                    </p>
                    <p className="text-base text-muted-foreground">
                        {cma.sold >= 3
                            ? t('listings.cma.suggestedBody', {
                                  sold: cma.sold,
                              })
                            : t('listings.cma.suggestedAll', {
                                  count: cma.comparables.length,
                              })}
                    </p>
                </section>
                <section className="space-y-1 rounded-reos-lg border border-border bg-card p-5">
                    <h2 className="text-base font-semibold text-muted-foreground">
                        {t('listings.cma.median')}
                    </h2>
                    <p className="text-title font-semibold tabular-nums">
                        {dollars(cma.median_per_sqft)}
                    </p>
                    <p className="text-base text-muted-foreground">
                        {t('listings.cma.soldActive', {
                            sold: cma.sold,
                            active: cma.active,
                        })}
                    </p>
                </section>
            </div>
            <section aria-labelledby="comparables" className="space-y-3">
                <h2 id="comparables" className="text-lg font-semibold">
                    {t('listings.cma.comparables')}
                </h2>
                {/* Focusable so the table scrolls by keyboard on small screens. */}
                <div
                    tabIndex={0}
                    className="overflow-x-auto rounded-reos-lg border border-border bg-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <table className="w-full min-w-[56rem] text-left text-base">
                        <thead className="border-b border-border text-sm text-muted-foreground">
                            <tr>
                                {(
                                    [
                                        'address',
                                        'status',
                                        'price',
                                        'area',
                                        'perSqft',
                                        'adjusted',
                                        'distance',
                                        'days',
                                    ] as const
                                ).map((column) => (
                                    <th
                                        key={column}
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t(`listings.cma.columns.${column}`)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            <tr className="bg-muted/50">
                                <th
                                    scope="row"
                                    className="px-4 py-3 font-semibold"
                                >
                                    {cma.subject.address}
                                    <span className="block text-sm font-normal text-muted-foreground">
                                        {t('listings.cma.subject')}
                                    </span>
                                </th>
                                <td className="px-4 py-3">
                                    {t(
                                        `listings.status.${cma.subject.status}`,
                                        {
                                            defaultValue: cma.subject.status,
                                        },
                                    )}
                                </td>
                                <td className="px-4 py-3 tabular-nums">
                                    {dollars(cma.subject.price)}
                                </td>
                                <td className="px-4 py-3 tabular-nums">
                                    {number(cma.subject.area)}
                                </td>
                                <td className="px-4 py-3 tabular-nums">
                                    {dollars(cma.subject.price_per_sqft)}
                                </td>
                                <td className="px-4 py-3">—</td>
                                <td className="px-4 py-3">—</td>
                                <td className="px-4 py-3 tabular-nums">
                                    {number(cma.subject.days_on_market)}
                                </td>
                            </tr>
                            {cma.comparables.map(
                                ({ listing, distance, adjusted }) => (
                                    <tr key={listing.uuid}>
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-normal"
                                        >
                                            <span className="font-semibold">
                                                {listing.address}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {listing.attribution}
                                            </span>
                                        </th>
                                        <td className="px-4 py-3">
                                            {t(
                                                `listings.status.${listing.status}`,
                                                {
                                                    defaultValue:
                                                        listing.status,
                                                },
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {dollars(
                                                listing.status === 'closed'
                                                    ? listing.close_price
                                                    : listing.price,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {number(listing.area)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {dollars(listing.price_per_sqft)}
                                        </td>
                                        <td className="px-4 py-3 font-semibold tabular-nums">
                                            {dollars(adjusted)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {t('listings.cma.distance', {
                                                miles: distance.toFixed(1),
                                            })}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {number(listing.days_on_market)}
                                        </td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
                <p className="text-sm text-muted-foreground">
                    {t('listings.cma.attributions')}
                </p>
            </section>
        </div>
    );
}
