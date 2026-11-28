import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ApprovalsQueue } from '@/components/brokerage/approvals-queue';
import type {
    ApprovalKind,
    ApprovalsQueueData,
} from '@/components/brokerage/types';
import { PageHeader } from '@/components/page-header';
import { act, settings } from '@/routes/approvals';

const routes = {
    act: (kind: ApprovalKind, id: string) => act({ kind, item: id }).url,
    settings: settings().url,
};

/**
 * Brokerage › Approvals (A15.3, broker web screen "approvals" with broker
 * journey screens 7–12): one queue for posts, public pages and escalated
 * questions, oldest first, with the item under review beside it.
 */
export default function Approvals({ queue }: { queue: ApprovalsQueueData }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('brokerage.approvals.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brokerage.approvals.title')}
                    description={
                        queue.scope === 'team'
                            ? t('brokerage.approvals.teamPurpose', {
                                  team: queue.team ?? '',
                              })
                            : t('brokerage.approvals.purpose')
                    }
                />
                <ApprovalsQueue queue={queue} routes={routes} />
            </div>
        </>
    );
}
