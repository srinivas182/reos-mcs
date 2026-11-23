import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Printer, SearchX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { CmaView } from '@/components/listings/cma-view';
import type { Cma } from '@/components/listings/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as listingsIndex } from '@/routes/deals/listings';
import { cma as cmaRoute } from '@/routes/deals/listings';

/**
 * A CMA for one of the agent's listings (B10.2b, from My listings beside
 * Deals): the suggested range, the median price per square foot, and the
 * comparables. Radius and date range can be changed; Print saves the page.
 */
export default function CmaPage({
    cma,
    radii,
    ranges,
}: {
    cma: Cma;
    radii: number[];
    ranges: number[];
}) {
    const { t } = useTranslation();
    const change = (query: { radius?: number; months?: number }) =>
        router.visit(
            cmaRoute(cma.subject.uuid, {
                query: { radius: cma.radius, months: cma.months, ...query },
            }).url,
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('listings.cma.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('listings.cma.title')}
                    description={t('listings.cma.description', {
                        address: cma.subject.address,
                    })}
                    back={
                        <Button
                            asChild
                            variant="ghost"
                            className="print:hidden"
                        >
                            <Link href={listingsIndex().url}>
                                <ArrowLeft aria-hidden />
                                {t('listings.cma.back')}
                            </Link>
                        </Button>
                    }
                    actions={
                        <Button
                            variant="outline"
                            className="print:hidden"
                            onClick={() => window.print()}
                        >
                            <Printer aria-hidden />
                            {t('listings.cma.print')}
                        </Button>
                    }
                />
                {cma.sample ? (
                    <p className="text-base text-muted-foreground">
                        <span className="mr-3 rounded-reos-pill bg-reos-tone-info-bg px-3 py-1 font-semibold text-reos-tone-info-fg">
                            {t('listings.sample')}
                        </span>
                        {t('listings.sampleNote')}
                    </p>
                ) : null}
                <div className="flex flex-wrap gap-4 print:hidden">
                    <div className="space-y-1">
                        <label
                            id="cma-radius"
                            className="block text-sm font-semibold"
                        >
                            {t('listings.cma.radius')}
                        </label>
                        <Select
                            value={String(cma.radius)}
                            onValueChange={(v) => change({ radius: Number(v) })}
                        >
                            <SelectTrigger
                                aria-labelledby="cma-radius"
                                className="w-40"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {radii.map((r) => (
                                    <SelectItem key={r} value={String(r)}>
                                        {t('listings.cma.radiusOption', {
                                            miles: r,
                                        })}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <label
                            id="cma-months"
                            className="block text-sm font-semibold"
                        >
                            {t('listings.cma.months')}
                        </label>
                        <Select
                            value={String(cma.months)}
                            onValueChange={(v) => change({ months: Number(v) })}
                        >
                            <SelectTrigger
                                aria-labelledby="cma-months"
                                className="w-44"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ranges.map((m) => (
                                    <SelectItem key={m} value={String(m)}>
                                        {t('listings.cma.monthsOption', {
                                            count: m,
                                        })}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                {cma.comparables.length === 0 ? (
                    <EmptyState
                        icon={SearchX}
                        title={t('listings.cma.empty.title')}
                        description={t('listings.cma.empty.description')}
                    />
                ) : (
                    <CmaView cma={cma} />
                )}
            </div>
        </>
    );
}
