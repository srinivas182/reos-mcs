import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ClosingsReport, TrainingReport } from '@/components/brokerage/reports';
import type {
    ClosingsReport as ClosingsData,
    TrainingReport as TrainingData,
} from '@/components/brokerage/types';
import { PageHeader } from '@/components/page-header';
import { index as ceIndex } from '@/routes/ce-compliance';
import { index } from '@/routes/reports';

/** Brokerage › Reports (A16.3): closings matched to the transaction tool, and training. */
export default function ReportsPage({
    closings,
    training,
}: {
    closings: ClosingsData;
    training: TrainingData;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('brokerage.reports.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brokerage.reports.title')}
                    description={
                        closings.scope === 'team'
                            ? t('brokerage.reports.teamPurpose', {
                                  team: closings.team ?? '',
                              })
                            : t('brokerage.reports.purpose')
                    }
                />
                <ClosingsReport
                    report={closings}
                    periodHref={(period) => index({ query: { period } }).url}
                />
                <TrainingReport report={training} ceHref={ceIndex().url} />
            </div>
        </>
    );
}
