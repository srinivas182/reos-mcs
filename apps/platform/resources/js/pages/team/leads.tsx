import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { TeamLeads } from '@/components/teams/team';
import type { SplitSheetRow, TeamLead } from '@/components/teams/types';
import { claim } from '@/routes/team';
import splitRoutes from '@/routes/team/splits';

/** Team leads (A17.3, agent): leads to claim in time, and split sheets to sign. */
export default function TeamLeadsPage({
    offered,
    splits,
}: {
    offered: TeamLead[];
    splits: SplitSheetRow[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('teams.leads.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('teams.leads.title')}
                    description={t('teams.leads.purpose')}
                />
                <TeamLeads
                    offered={offered}
                    splits={splits}
                    routes={{
                        claim: (id) => claim(id).url,
                        sign: (id) => splitRoutes.sign(id).url,
                        question: (id) => splitRoutes.question(id).url,
                    }}
                />
            </div>
        </>
    );
}
