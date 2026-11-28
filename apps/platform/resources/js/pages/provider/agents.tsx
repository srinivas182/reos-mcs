import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { AgentsInArea } from '@/components/providers/agents-in-area';
import type {
    AgentsInAreaView,
    SentRequest,
} from '@/components/providers/types';
import { index, request, withdraw } from '@/routes/provider-agents';

const routes = {
    request: request().url,
    withdraw: (id: string) => withdraw(id).url,
};

/** Agents in my areas (B16.1, provider journey): agents by level, filters, and requests to connect. */
export default function ProviderAgents({
    view,
    sent,
}: {
    view: AgentsInAreaView;
    sent: SentRequest[];
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
            </div>
        </>
    );
}
