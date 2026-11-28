import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { ProviderProfileCard } from '@/components/providers/provider-card';
import type { ProviderCard } from '@/components/providers/types';

/** A verified provider's profile, as agents see it (B15.3). */
export default function ProviderShow({ provider }: { provider: ProviderCard }) {
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
                <div className="max-w-3xl">
                    <ProviderProfileCard provider={provider} />
                </div>
            </div>
        </>
    );
}
