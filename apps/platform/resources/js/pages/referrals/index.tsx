import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import {
    InviteCard,
    ReferralsView,
} from '@/components/referrals/referrals-view';
import type { InviteSummary, Referral } from '@/components/referrals/types';
import { index as contacts } from '@/routes/contacts';
import { accept, close, decline, store } from '@/routes/referrals';

const routes = {
    create: store().url,
    accept: (id: string) => accept(id).url,
    decline: (id: string) => decline(id).url,
    close: (id: string) => close(id).url,
    contact: (uuid: string) => contacts({ query: { contact: uuid } }).url,
};

/** Referrals and invites (B14.3b): refer clients between brokers, and invite and earn. */
export default function ReferralsPage({
    referrals,
    invite,
}: {
    referrals: { sent: Referral[]; received: Referral[] };
    invite: InviteSummary;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('leads.referrals.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('leads.referrals.title')}
                    description={t('leads.referrals.purpose')}
                />
                <ReferralsView referrals={referrals} routes={routes} />
                <InviteCard invite={invite} />
            </div>
        </>
    );
}
