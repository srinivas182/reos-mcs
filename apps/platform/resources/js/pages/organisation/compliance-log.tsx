import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    LogFiltersForm,
    LogTable,
} from '@/components/compliance/compliance-log';
import type {
    LogAction,
    LogChannel,
    LogFilters,
    LogRow,
} from '@/components/compliance/compliance-log';
import { PageHeader } from '@/components/page-header';
import { exportMethod, index } from '@/routes/organisation/compliance-log';

const query = (f: LogFilters) =>
    Object.fromEntries(
        Object.entries(f).filter(([, v]) => v !== null && v !== ''),
    ) as Record<string, string | number>;

/**
 * The compliance log (B13.2; broker web): every check, fix, approval and
 * send-back across posts, videos, the public page, emails and texts, with
 * who did it and when; filter it and export it to CSV.
 */
export default function ComplianceLogPage({
    brokerage,
    events,
    filters,
    agents,
    channels,
    actions,
}: {
    brokerage: string;
    events: LogRow[];
    filters: LogFilters;
    agents: { id: number; name: string }[];
    channels: LogChannel[];
    actions: LogAction[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('compliance.log.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('compliance.log.title')}
                    description={t('compliance.log.purpose')}
                />
                <p className="text-base text-muted-foreground">{brokerage}</p>
                <LogFiltersForm
                    filters={filters}
                    agents={agents}
                    channels={channels}
                    actions={actions}
                    exportHref={exportMethod({ query: query(filters) }).url}
                    onApply={(next) =>
                        router.get(index().url, query(next), {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                />
                <LogTable rows={events} />
            </div>
        </>
    );
}
