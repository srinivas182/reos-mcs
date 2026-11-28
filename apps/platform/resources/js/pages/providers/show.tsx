import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { ProviderProfileCard } from '@/components/providers/provider-card';
import { ProviderNetworkPanel } from '@/components/providers/near-you';
import type {
    ProviderCard,
    ProviderNetworkInfo,
} from '@/components/providers/types';
import { index as providerRequests } from '@/routes/provider-network';
import { connect, review } from '@/routes/providers';

/** A verified provider's profile, as agents see it (B15.3). */
export default function ProviderShow({
    provider,
    network = null,
}: {
    provider: ProviderCard;
    /** Reviews, my connection and Request to connect (B16.3). */
    network?: ProviderNetworkInfo | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={provider.name} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={provider.name}
                    description={
                        provider.category ?? t('providers.directory.purpose')
                    }
                />
                <div className="grid max-w-5xl gap-6 lg:grid-cols-[2fr_1fr]">
                    <ProviderProfileCard provider={provider} />
                    {network ? (
                        <ProviderNetworkPanel
                            name={provider.name}
                            network={network}
                            routes={{
                                connect: connect(provider.id).url,
                                review: review(provider.id).url,
                                requests: providerRequests().url,
                            }}
                        />
                    ) : null}
                </div>
            </div>
        </>
    );
}
