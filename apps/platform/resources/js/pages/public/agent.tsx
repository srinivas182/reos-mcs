import { Head } from '@inertiajs/react';
import { BadgeCheck, Building2, Home, Star } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { ListingCard } from '@/components/listings/listing-card';
import type { PublicAgentPage } from '@/components/listings/types';

const TREC_CPN = 'https://www.trec.texas.gov/forms/consumer-protection-notice';
const TREC_IABS =
    'https://www.trec.texas.gov/forms/information-about-brokerage-services';

/**
 * An agent's public page (B10.2b): name, brokerage, license numbers, bio and
 * MLS listings with each MLS's rules applied (attribution on every listing,
 * no sold prices unless allowed). TREC's notices are linked as its rules
 * require. Sample data is marked and kept out of search engines.
 */
export default function AgentPublicPage({ page }: { page: PublicAgentPage }) {
    const { t } = useTranslation();
    const title = t('listings.public.title', { name: page.name });

    return (
        <>
            <Head title={title}>
                <meta
                    name="description"
                    content={t('listings.public.metaDescription', {
                        name: page.name,
                        brokerage: page.brokerage ? ` · ${page.brokerage}` : '',
                    })}
                />
                <link rel="canonical" href={page.url} />
                {page.sample ? <meta name="robots" content="noindex" /> : null}
            </Head>
            <div className="mx-auto max-w-6xl space-y-8 px-4 py-10">
                <header className="space-y-3">
                    <h1 className="text-3xl font-semibold">{page.name}</h1>
                    {page.brokerage ? (
                        <p className="flex items-center gap-2 text-lg">
                            <Building2 aria-hidden className="size-5" />
                            {page.brokerage}
                        </p>
                    ) : null}
                    {page.licences.length > 0 ? (
                        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-base text-muted-foreground">
                            {page.licences.map((l) => (
                                <li
                                    key={`${l.state}-${l.number}`}
                                    className="flex items-center gap-1"
                                >
                                    <BadgeCheck
                                        aria-hidden
                                        className="size-4"
                                    />
                                    {t('listings.public.license', {
                                        state: l.state,
                                        number: l.number,
                                    })}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    {page.peer_rating ? (
                        <p className="flex items-center gap-2 text-base">
                            <Star aria-hidden className="size-4" />
                            {t('feedback.publicLine', {
                                average: page.peer_rating.average.toFixed(1),
                                count: page.peer_rating.count,
                            })}
                        </p>
                    ) : null}
                    {page.bio ? (
                        <p className="max-w-3xl text-body">{page.bio}</p>
                    ) : null}
                </header>
                {page.sample ? (
                    <p className="text-base text-muted-foreground">
                        <span className="mr-3 rounded-reos-pill bg-reos-tone-info-bg px-3 py-1 font-semibold text-reos-tone-info-fg">
                            {t('listings.sample')}
                        </span>
                        {t('listings.public.sampleNote')}
                    </p>
                ) : null}
                <section aria-labelledby="agent-listings" className="space-y-4">
                    <h2 id="agent-listings" className="text-xl font-semibold">
                        {t('listings.public.listingsTitle')}
                    </h2>
                    {page.listings.length === 0 ? (
                        <EmptyState
                            icon={Home}
                            title={t('listings.public.noListings')}
                            description={t('listings.empty.description')}
                        />
                    ) : (
                        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {page.listings.map((listing) => (
                                <li key={listing.uuid}>
                                    <ListingCard
                                        listing={{
                                            ...listing,
                                            role: 'list',
                                            can_post: false,
                                            open_house: null,
                                        }}
                                        hideRole
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                <footer className="flex flex-wrap gap-x-6 gap-y-2 border-t border-border pt-6 text-base">
                    <a
                        className="underline"
                        href={TREC_IABS}
                        target="_blank"
                        rel="noreferrer"
                    >
                        {t('listings.public.iabs')}
                    </a>
                    <a
                        className="underline"
                        href={TREC_CPN}
                        target="_blank"
                        rel="noreferrer"
                    >
                        {t('listings.public.cpn')}
                    </a>
                </footer>
            </div>
        </>
    );
}
