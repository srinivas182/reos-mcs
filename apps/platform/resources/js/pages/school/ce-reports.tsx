import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { ReadyTable } from '@/components/ce/ready-table';
import type { Reporting, ReportSummary } from '@/components/ce/reporting-types';
import { ReportsList } from '@/components/ce/reports-list';
import { SubmitReport, type SubmitValues } from '@/components/ce/submit-report';
import { PageHeader } from '@/components/page-header';
import { postJson, putJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import {
    file as fileRoute,
    line as lineRoute,
    reference as referenceRoute,
    store,
} from '@/routes/school/ce-reports';

/**
 * CE reporting to TREC (school admin web screen 51, A11.3): Ready,
 * Rejected and the reports sent. Submit downloads TREC's upload file or,
 * with the school's authority, lets REOS submit; TREC's reference moves
 * every completion in a report to Reported; a completion TREC rejects
 * returns to Ready with its reason. Every submission is logged.
 */
export default function CeReportsPage({
    reporting: initial,
}: {
    reporting: Reporting;
}) {
    const { t } = useTranslation();
    const [reporting, setReporting] = useState(initial);
    const [error, setError] = useState<string | null>(null);
    const today = new Date().toISOString().slice(0, 10);
    const run = async <T,>(go: () => Promise<T>, after: (value: T) => void) => {
        try {
            after(await go());
            setError(null);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const submit = (values: SubmitValues) =>
        run(
            () =>
                postJson<{ data: { id: string; overview: Reporting } }>(
                    store().url,
                    values,
                ),
            ({ data }) => {
                setReporting(data.overview);
                toast.success(t('ce.reports.submit.created'));
                if (values.method === 'file')
                    window.location.assign(fileRoute(data.id).url);
            },
        );
    const reference = (report: ReportSummary, value: string) =>
        run(
            () =>
                putJson<{ data: Reporting }>(referenceRoute(report.id).url, {
                    reference: value,
                }),
            ({ data }) => {
                setReporting(data);
                toast.success(t('ce.reports.reference.saved'));
            },
        );
    const reject = (
        report: ReportSummary,
        completion: number,
        reason: string,
        rejected = true,
    ): Promise<void> =>
        run(
            () =>
                putJson<{ data: Reporting }>(
                    lineRoute({ report: report.id, completion }).url,
                    { rejected, reason: rejected ? reason : null },
                ),
            ({ data }) => {
                setReporting(data);
                if (rejected)
                    undoToast({
                        message: t('ce.reports.reject.done'),
                        onUndo: () => reject(report, completion, reason, false),
                    });
            },
        );

    return (
        <>
            <Head title={t('ce.reports.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('ce.reports.title')}
                    description={t('ce.reports.description')}
                />
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                <SubmitReport
                    reos={reporting.reos}
                    today={today}
                    onSubmit={(values) => void submit(values)}
                />
                <section aria-labelledby="ce-ready" className="space-y-3">
                    <h2 id="ce-ready" className="text-lg font-semibold">
                        {t('ce.reports.tabs.ready', {
                            count: reporting.ready.length,
                        })}
                    </h2>
                    <ReadyTable rows={reporting.ready} kind="ready" />
                </section>
                <section aria-labelledby="ce-rejected" className="space-y-3">
                    <h2 id="ce-rejected" className="text-lg font-semibold">
                        {t('ce.reports.tabs.rejected', {
                            count: reporting.rejected.length,
                        })}
                    </h2>
                    <ReadyTable rows={reporting.rejected} kind="rejected" />
                </section>
                <section aria-labelledby="ce-reports" className="space-y-3">
                    <h2 id="ce-reports" className="text-lg font-semibold">
                        {t('ce.reports.tabs.reports', {
                            count: reporting.reports.length,
                        })}
                    </h2>
                    <ReportsList
                        reports={reporting.reports}
                        fileUrl={(report) => fileRoute(report.id).url}
                        onReference={(report, value) =>
                            void reference(report, value)
                        }
                        onReject={(report, completion, reason) =>
                            void reject(report, completion, reason)
                        }
                    />
                </section>
            </div>
        </>
    );
}
