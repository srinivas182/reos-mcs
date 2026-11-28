import { Head, Link, router } from '@inertiajs/react';
import { Handshake } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ProviderProfileCard } from '@/components/providers/provider-card';
import { NearYou } from '@/components/providers/near-you';
import type { NearYouData, ProviderCard } from '@/components/providers/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/providers';

/** Verified providers (B15.3, agent web screen "service providers"): live profiles with the licence badge. */
export default function Providers({
    providers,
    filters,
    categories,
    near = null,
}: {
    providers: ProviderCard[];
    filters: { category: string | null; place: string | null };
    categories: { key: string; name: string }[];
    /** Providers near you, with ratings (B16.3). */
    near?: NearYouData | null;
}) {
    const { t } = useTranslation();
    const [category, setCategory] = useState(filters.category ?? '');
    const [place, setPlace] = useState(filters.place ?? '');

    return (
        <>
            <Head title={t('providers.directory.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('providers.directory.title')}
                    description={t('providers.directory.purpose')}
                />
                {near && !filters.place ? (
                    <NearYou
                        near={near}
                        category={filters.category}
                        profileHref={(id) => show(id).url}
                        categoryHref={(key) =>
                            index({ query: { category: key ?? undefined } }).url
                        }
                    />
                ) : null}
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            index({
                                query: {
                                    category: category || undefined,
                                    place: place || undefined,
                                },
                            }).url,
                        );
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="provider-category">
                            {t('providers.directory.category')}
                        </Label>
                        <select
                            id="provider-category"
                            className="h-12 rounded-reos-md border border-input bg-background px-3 text-base"
                            value={category}
                            onChange={(e) => setCategory(e.target.value)}
                        >
                            <option value="">
                                {t('providers.directory.allCategories')}
                            </option>
                            {categories.map((c) => (
                                <option key={c.key} value={c.key}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="provider-place">
                            {t('providers.directory.place')}
                        </Label>
                        <Input
                            id="provider-place"
                            value={place}
                            maxLength={80}
                            onChange={(e) => setPlace(e.target.value)}
                        />
                    </div>
                    <Button type="submit">
                        {t('providers.directory.search')}
                    </Button>
                </form>
                {providers.length === 0 ? (
                    <EmptyState
                        icon={Handshake}
                        title={t('providers.directory.emptyTitle')}
                        description={t('providers.directory.empty')}
                    />
                ) : (
                    <ul className="grid gap-4 lg:grid-cols-2">
                        {providers.map((p) => (
                            <li key={p.id} className="space-y-2">
                                <ProviderProfileCard provider={p} compact />
                                <Button variant="outline" asChild>
                                    <Link href={show(p.id).url}>
                                        {t('providers.directory.open', {
                                            name: p.name,
                                        })}
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
