import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    CeCompliance,
    Sponsorships,
} from '@/components/brokerage/ce-compliance';
import type { CeTable, SponsorshipBoard } from '@/components/brokerage/types';
import { PageHeader } from '@/components/page-header';
import { assign, remind } from '@/routes/ce-compliance';
import { release } from '@/routes/sponsorships';

const routes = { remind: remind().url, assign: assign().url };

/** Brokerage › CE compliance (A16.2): renewals, SAE, reminders, courses and sponsorship. */
export default function CeCompliancePage({
    table,
    sponsorships,
}: {
    table: CeTable;
    sponsorships: SponsorshipBoard | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('brokerage.ce.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brokerage.ce.title')}
                    description={
                        table.scope === 'team'
                            ? t('brokerage.ce.teamPurpose', {
                                  team: table.team ?? '',
                              })
                            : t('brokerage.ce.purpose')
                    }
                />
                <CeCompliance table={table} routes={routes} />
                {sponsorships ? (
                    <Sponsorships board={sponsorships} route={release().url} />
                ) : null}
            </div>
        </>
    );
}
