import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import type { WeeklyReport } from '@/components/practice/types';
import { WeeklyReportView } from '@/components/practice/weekly-report';

/** A weekly progress report shared by link with a study buddy (A9.2e): first name only. */
export default function SharedReport({ report }: { report: WeeklyReport }) {
    const { t } = useTranslation();
    const title = t('practice.reports.sharedTitle', {
        name: report.first_name,
    });

    return (
        <>
            <Head title={title} />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-10">
                <h1 className="text-3xl font-semibold">{title}</h1>
                <p className="text-body text-muted-foreground">
                    {t('practice.reports.sharedBody')}
                </p>
                <WeeklyReportView report={report} />
            </div>
        </>
    );
}
