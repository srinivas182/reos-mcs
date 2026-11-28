import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { LeadRoutingPanel, SplitSheetsPanel } from '@/components/teams/team';
import type { RoutingBoard, SplitsBoard } from '@/components/teams/types';
import { agreement, lead, member, rules } from '@/routes/team';
import splits from '@/routes/team/splits';

/** Team and routing (A17.3, team leader screens 28–30): lead routing, the rotation and split sheets. */
export default function TeamRouting({
    routing,
    splits: sheets,
}: {
    routing: RoutingBoard;
    splits: SplitsBoard;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('nav.teamsAndRouting')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('nav.teamsAndRouting')}
                    description={t('teams.routing.purpose')}
                />
                <LeadRoutingPanel
                    board={routing}
                    routes={{
                        rules: rules().url,
                        member: (id) => member(id).url,
                        lead: lead().url,
                    }}
                />
                <SplitSheetsPanel
                    board={sheets}
                    routes={{
                        agreement: agreement().url,
                        send: splits.store().url,
                    }}
                />
            </div>
        </>
    );
}
