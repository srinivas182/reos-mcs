import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { index as messagesIndex } from '@/routes/messages';
import { ProviderRequestsInbox } from '@/components/providers/network';
import type { AgentNetwork } from '@/components/providers/types';
import {
    accept,
    block,
    decline,
    preferences,
    share,
} from '@/routes/provider-network';

const routes = {
    accept: (id: string) => accept(id).url,
    decline: (id: string) => decline(id).url,
    share: (id: string) => share(id).url,
    block: (id: string) => block(id).url,
    preferences: preferences().url,
    chat: (conversation: string) =>
        messagesIndex({ query: { c: conversation } }).url,
};

/** Provider requests (B16.2, agent journey): requests to answer, connections and my choices. */
export default function ProviderRequests({
    network,
}: {
    network: AgentNetwork;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('providers.network.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('providers.network.title')}
                    description={t('providers.network.purpose')}
                />
                <ProviderRequestsInbox network={network} routes={routes} />
            </div>
        </>
    );
}
