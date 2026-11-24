import { CircleCheck, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import type { ReadyRow } from './reporting-types';

const day = (iso: string, locale: string) =>
    new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(
        new Date(`${iso.slice(0, 10)}T12:00:00`),
    );

/** Completions not yet reported (or rejected by TREC), with what would stop one going (A11.3, screen 51). */
export function ReadyTable({
    rows,
    kind,
}: {
    rows: ReadyRow[];
    kind: 'ready' | 'rejected';
}) {
    const { t, i18n } = useTranslation();
    const empty = kind === 'ready' ? 'emptyReady' : 'emptyRejected';

    if (rows.length === 0) {
        return (
            <EmptyState
                icon={CircleCheck}
                title={t(`ce.reports.${empty}.title`)}
                description={t(`ce.reports.${empty}.body`)}
            />
        );
    }

    return (
        <div
            className="overflow-x-auto rounded-reos-lg border border-border bg-card"
            tabIndex={0}
            aria-label={t(`ce.reports.tabs.${kind}`, { count: rows.length })}
        >
            <table className="w-full text-left text-base">
                <thead className="border-b border-border text-muted-foreground">
                    <tr>
                        <th scope="col" className="p-3 font-medium">
                            {t('ce.reports.columns.student')}
                        </th>
                        <th scope="col" className="p-3 font-medium">
                            {t('ce.reports.columns.licence')}
                        </th>
                        <th scope="col" className="p-3 font-medium">
                            {t('ce.reports.columns.course')}
                        </th>
                        <th scope="col" className="p-3 font-medium">
                            {t('ce.reports.columns.courseNumber')}
                        </th>
                        <th scope="col" className="p-3 font-medium">
                            {t('ce.reports.columns.hours')}
                        </th>
                        <th scope="col" className="p-3 font-medium">
                            {t('ce.reports.columns.completed')}
                        </th>
                        <th scope="col" className="p-3 font-medium">
                            {t(
                                kind === 'ready'
                                    ? 'ce.reports.columns.status'
                                    : 'ce.reports.columns.reason',
                            )}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.map((row) => (
                        <tr key={row.completion}>
                            <td className="p-3 font-semibold">{row.student}</td>
                            <td className="p-3">{row.licence ?? '—'}</td>
                            <td className="p-3">{row.course}</td>
                            <td className="p-3">{row.course_number ?? '—'}</td>
                            <td className="p-3">{row.hours}</td>
                            <td className="p-3">
                                {day(row.completed_on, i18n.language)}
                            </td>
                            <td className="p-3">
                                {kind === 'rejected' ? (
                                    row.rejected
                                ) : row.problem ? (
                                    <span className="inline-flex items-center gap-2 text-destructive">
                                        <TriangleAlert
                                            aria-hidden
                                            className="size-4"
                                        />
                                        {t(
                                            `ce.reports.problems.${row.problem}`,
                                        )}
                                    </span>
                                ) : (
                                    t('ce.reports.status.ready')
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
