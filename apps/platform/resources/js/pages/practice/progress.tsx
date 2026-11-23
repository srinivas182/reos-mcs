import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Link2, School } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import type { ReportShares, WeeklyReport } from '@/components/practice/types';
import { WeeklyReportView } from '@/components/practice/weekly-report';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { progress } from '@/routes/practice';
import { share as shareRoute } from '@/routes/practice/reports';

const shift = (day: string, days: number) => {
    const d = new Date(`${day}T12:00:00`);
    d.setDate(d.getDate() + days);

    return d.toISOString().slice(0, 10);
};

/** The weekly progress report on the web (the phone's screen 55, A9.2e), with sharing. */
export default function ProgressPage({
    report,
    shares: initial,
    this_week,
}: {
    report: WeeklyReport;
    shares: ReportShares;
    this_week: string;
}) {
    const { t } = useTranslation();
    const [shares, setShares] = useState(initial);
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const share = async (withWho: 'school' | 'link') => {
        try {
            const next = await postJson<{ data: ReportShares }>(
                shareRoute().url,
                { with: withWho, week: report.week_start },
            );
            setShares(next.data);
            setError(null);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('practice.reports.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.reports.title')}
                    description={t('practice.reports.description')}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={progress({
                                        query: {
                                            week: shift(report.week_start, -7),
                                        },
                                    })}
                                >
                                    <ChevronLeft aria-hidden />
                                    {t('practice.reports.previous')}
                                </Link>
                            </Button>
                            {report.week_start < this_week ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={progress({
                                            query: {
                                                week: shift(
                                                    report.week_start,
                                                    7,
                                                ),
                                            },
                                        })}
                                    >
                                        {t('practice.reports.next')}
                                        <ChevronRight aria-hidden />
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    }
                />
                <WeeklyReportView report={report} />
                <section
                    aria-labelledby="share"
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                >
                    <h2 id="share" className="text-lg font-semibold">
                        {t('practice.reports.shareTitle')}
                    </h2>
                    <p className="text-body text-muted-foreground">
                        {t('practice.reports.shareBody')}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {shares.can_share_school ? (
                            shares.school ? (
                                <p className="inline-flex items-center gap-2 text-body">
                                    <School aria-hidden className="size-5" />
                                    {t('practice.reports.sharedSchool')}
                                </p>
                            ) : (
                                <Button onClick={() => void share('school')}>
                                    <School aria-hidden />
                                    {t('practice.reports.shareSchool')}
                                </Button>
                            )
                        ) : null}
                        {shares.link ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    void navigator.clipboard
                                        ?.writeText(shares.link ?? '')
                                        .then(() => setCopied(true))
                                }
                            >
                                <Link2 aria-hidden />
                                {t('practice.reports.copy')}
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() => void share('link')}
                            >
                                <Link2 aria-hidden />
                                {t('practice.reports.shareLink')}
                            </Button>
                        )}
                    </div>
                    {shares.link ? (
                        <p className="text-sm break-all text-muted-foreground">
                            {shares.link}
                        </p>
                    ) : null}
                    {copied ? (
                        <p role="status" className="text-body">
                            {t('practice.reports.copied')}
                        </p>
                    ) : null}
                    {error ? (
                        <p role="alert" className="text-body text-destructive">
                            {error}
                        </p>
                    ) : null}
                </section>
            </div>
        </>
    );
}
