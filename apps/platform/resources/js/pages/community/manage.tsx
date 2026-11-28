import { Head, router } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CommunityPanel } from '@/components/communities/community';
import {
    InvitePanel,
    RosterPanel,
    ResultsView,
    ScheduleForm,
} from '@/components/communities/owner';
import type {
    CommunityResults,
    CommunitySession,
    CommunityView,
    RosterRow,
} from '@/components/communities/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { communityRoutes } from '@/lib/communities';
import { getJson } from '@/lib/content';
import { results as resultsPage } from '@/routes/community';

/** My community (B17.2, provider screens 16–17): sessions, posts, resources, the invite and a results summary. */
export default function CommunityManage({
    community,
    results,
    canCharge = false,
}: {
    community: CommunityView;
    results: CommunityResults;
    canCharge?: boolean;
}) {
    const { t } = useTranslation();
    const routes = communityRoutes(community.id);
    const [roster, setRoster] = useState<{
        session: CommunitySession;
        rows: RosterRow[];
    } | null>(null);
    const reload = () => router.reload({ only: ['community', 'results'] });

    return (
        <>
            <Head title={t('communities.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={community.name}
                    description={t('communities.purpose')}
                    actions={
                        <Button asChild variant="outline">
                            <a href={resultsPage().url}>
                                <BarChart3 aria-hidden />
                                {t('communities.results.open')}
                            </a>
                        </Button>
                    }
                />
                {community.invite_url ? (
                    <InvitePanel
                        name={community.name}
                        inviteUrl={community.invite_url}
                        qrUrl={community.qr_url}
                        newCodeUrl={routes.newCode}
                        onChanged={reload}
                    />
                ) : null}
                <ScheduleForm
                    url={routes.schedule}
                    canCharge={canCharge}
                    onScheduled={reload}
                />
                {roster ? (
                    <RosterPanel
                        key={roster.session.id}
                        title={roster.session.title}
                        rows={roster.rows}
                        url={routes.attendance(roster.session.id)}
                        onSaved={reload}
                    />
                ) : null}
                <CommunityPanel
                    community={community}
                    routes={routes}
                    onChanged={reload}
                    onRoster={(session) =>
                        void getJson<{ data: RosterRow[] }>(
                            routes.roster(session.id),
                        ).then((r) => setRoster({ session, rows: r.data }))
                    }
                />
                <ResultsView results={results} />
            </div>
        </>
    );
}
