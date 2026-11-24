import { Download, FileCheck2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type {
    LineStatus,
    ReportStatus,
    ReportSummary,
} from './reporting-types';

const TONE: Record<ReportStatus | LineStatus, string> = {
    pending_upload: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    submitted: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    reported: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    included: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    rejected: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
};

const day = (iso: string, locale: string) =>
    new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(
        new Date(`${iso.slice(0, 10)}T12:00:00`),
    );

/**
 * The school's CE reports (A11.3, screen 51): each with its range, how it
 * went, its status and TREC's reference; the file to download; the
 * reference to enter; and any completion TREC rejected.
 */
export function ReportsList({
    reports,
    fileUrl,
    onReference,
    onReject,
}: {
    reports: ReportSummary[];
    fileUrl: (report: ReportSummary) => string;
    onReference: (report: ReportSummary, reference: string) => void;
    onReject: (
        report: ReportSummary,
        completion: number,
        reason: string,
    ) => void;
}) {
    const { t, i18n } = useTranslation();
    const [references, setReferences] = useState<Record<string, string>>({});
    const [reasons, setReasons] = useState<Record<string, string>>({});

    if (reports.length === 0) {
        return (
            <EmptyState
                icon={FileCheck2}
                title={t('ce.reports.emptyReports.title')}
                description={t('ce.reports.emptyReports.body')}
            />
        );
    }

    return (
        <ul className="space-y-4">
            {reports.map((report) => (
                <li
                    key={report.id}
                    className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-base font-semibold">
                                {day(report.from_on, i18n.language)} –{' '}
                                {day(report.to_on, i18n.language)}
                            </p>
                            <p className="text-base text-muted-foreground">
                                {[
                                    t(`ce.reports.methods.${report.method}`),
                                    `${t('ce.reports.columns.count')}: ${report.count}`,
                                    report.created_by
                                        ? `${t('ce.reports.columns.sentBy')}: ${report.created_by}`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                        <span
                            className={cn(
                                'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                TONE[report.status],
                            )}
                        >
                            {t(`ce.reports.status.${report.status}`)}
                        </span>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <Button asChild variant="outline">
                            <a href={fileUrl(report)} download>
                                <Download aria-hidden />
                                {t('ce.reports.submit.download')}
                            </a>
                        </Button>
                        {report.status === 'reported' ? (
                            <p className="text-base">
                                {t('ce.reports.reference.label')}:{' '}
                                <span className="font-semibold">
                                    {report.reference}
                                </span>
                            </p>
                        ) : (
                            <form
                                className="flex flex-wrap items-end gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    onReference(
                                        report,
                                        references[report.id] ?? '',
                                    );
                                }}
                            >
                                <div className="space-y-2">
                                    <Label
                                        htmlFor={`ref-${report.id}`}
                                        className="text-base"
                                    >
                                        {t('ce.reports.reference.label')}
                                    </Label>
                                    <Input
                                        id={`ref-${report.id}`}
                                        placeholder={t(
                                            'ce.reports.reference.placeholder',
                                        )}
                                        value={references[report.id] ?? ''}
                                        onChange={(e) =>
                                            setReferences((r) => ({
                                                ...r,
                                                [report.id]: e.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                <Button type="submit">
                                    {t('ce.reports.reference.save')}
                                </Button>
                            </form>
                        )}
                    </div>
                    <ul className="divide-y divide-border rounded-reos-md border border-border">
                        {report.lines.map((line) => {
                            const key = `${report.id}-${line.completion}`;
                            return (
                                <li
                                    key={key}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-base font-semibold">
                                            {line.student}
                                        </p>
                                        <p className="text-base text-muted-foreground">
                                            {line.course}
                                            {line.reason
                                                ? ` · ${t('ce.reports.rejectedBefore', { reason: line.reason })}`
                                                : null}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                            TONE[line.status],
                                        )}
                                    >
                                        {t(`ce.reports.status.${line.status}`)}
                                    </span>
                                    {line.status !== 'rejected' ? (
                                        <form
                                            className="flex flex-wrap items-end gap-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                onReject(
                                                    report,
                                                    line.completion,
                                                    reasons[key] ?? '',
                                                );
                                            }}
                                        >
                                            <Label
                                                htmlFor={`reason-${key}`}
                                                className="sr-only"
                                            >
                                                {t('ce.reports.reject.reason')}:{' '}
                                                {line.student}
                                            </Label>
                                            <Input
                                                id={`reason-${key}`}
                                                className="w-56"
                                                placeholder={t(
                                                    'ce.reports.reject.reason',
                                                )}
                                                value={reasons[key] ?? ''}
                                                onChange={(e) =>
                                                    setReasons((r) => ({
                                                        ...r,
                                                        [key]: e.target.value,
                                                    }))
                                                }
                                            />
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={
                                                    (
                                                        reasons[key] ?? ''
                                                    ).trim() === ''
                                                }
                                            >
                                                {t('ce.reports.reject.save')}
                                                <span className="sr-only">
                                                    : {line.student}
                                                </span>
                                            </Button>
                                        </form>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                </li>
            ))}
        </ul>
    );
}
