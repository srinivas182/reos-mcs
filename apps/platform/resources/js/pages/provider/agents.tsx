import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { AgentsInArea } from '@/components/providers/agents-in-area';
import { ConnectionCard } from '@/components/providers/network';
import type {
    AgentsInAreaView,
    ProviderConnection,
    SentRequest,
} from '@/components/providers/types';
import { index as messagesIndex } from '@/routes/messages';
import { block, share } from '@/routes/provider-network';
import { index, request, withdraw } from '@/routes/provider-agents';

const networkRoutes = {
    share: (id: string) => share(id).url,
    block: (id: string) => block(id).url,
    chat: (conversation: string) =>
        messagesIndex({ query: { c: conversation } }).url,
};

const routes = {
    request: request().url,
    withdraw: (id: string) => withdraw(id).url,
};

/** Agents in my areas (B16.1, provider journey): agents by level, filters, and requests to connect. */
export default function ProviderAgents({
    view,
    sent,
    connections = [],
}: {
    view: AgentsInAreaView;
    sent: SentRequest[];
    /** Agents who accepted (B16.2), with contact sharing and the chat. */
    connections?: ProviderConnection[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('providers.agents.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('providers.agents.title')}
                    description={t('providers.agents.purpose')}
                />
                <AgentsInArea
                    view={view}
                    sent={sent}
                    routes={routes}
                    onFilter={({ level, area }) =>
                        router.get(
                            index({
                                query: {
                                    ...(level ? { level } : {}),
                                    ...(area ? { area } : {}),
                                },
                            }).url,
                        )
                    }
                />
                {connections.length > 0 ? (
                    <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="text-title font-semibold">
                            {t('providers.network.connectionsTitle')}
                        </h2>
                        <ul className="divide-y divide-border">
                            {connections.map((c) => (
                                <ConnectionCard
                                    key={c.id}
                                    connection={c}
                                    routes={networkRoutes}
                                />
                            ))}
                        </ul>
                    </section>
                ) : null}
            </div>
        </>
    );
}
