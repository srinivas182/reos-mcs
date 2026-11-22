import { router } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { resolve } from '@/routes/compliance/reports';

export type MessageReportRow = {
    id: string;
    reason: 'spam' | 'harassment' | 'inappropriate' | 'other';
    details: string | null;
    reporter: string | null;
    reported: string | null;
    tenant: string | null;
    messages: {
        id: string;
        sender: string | null;
        body: string | null;
        at: string;
    }[];
    status: 'open' | 'resolved';
    created_at: string | null;
};

/** Reported messages (B6.1b) on Compliance and audit (screen 71): who, why, the copy of the messages, and Mark handled. */
export function MessageReports({ reports }: { reports: MessageReportRow[] }) {
    const { t, i18n } = useTranslation();

    if (reports.length === 0) {
        return (
            <EmptyState
                icon={Flag}
                title={t('messages.report.admin.empty')}
                description={t('messages.report.admin.emptyBody')}
            />
        );
    }

    return (
        <ul className="divide-y divide-border">
            {reports.map((report) => (
                <li key={report.id} className="space-y-3 py-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <p className="font-semibold">
                                {t('messages.report.admin.line', {
                                    reporter:
                                        report.reporter ??
                                        t('messages.formerMember'),
                                    reported:
                                        report.reported ??
                                        t('messages.report.mail.someone'),
                                    reason: t(
                                        `messages.report.reasons.${report.reason}`,
                                    ),
                                })}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {[
                                    report.tenant,
                                    report.created_at
                                        ? new Date(
                                              report.created_at,
                                          ).toLocaleString(i18n.language, {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          })
                                        : null,
                                    t(
                                        `messages.report.admin.statuses.${report.status}`,
                                    ),
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            {report.details ? (
                                <p className="text-body">{report.details}</p>
                            ) : null}
                        </div>
                        {report.status === 'open' ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        resolve(report.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('messages.report.admin.resolve')}
                            </Button>
                        ) : null}
                    </div>
                    <details className="rounded-reos-md bg-muted p-3">
                        <summary className="min-h-12 cursor-pointer content-center font-medium">
                            {t('messages.report.admin.messages', {
                                count: report.messages.length,
                            })}
                        </summary>
                        <ul className="mt-2 space-y-2 text-body">
                            {report.messages.map((m) => (
                                <li key={m.id}>
                                    <span className="font-medium">
                                        {m.sender ?? t('messages.formerMember')}
                                        :{' '}
                                    </span>
                                    {m.body ?? t('messages.deleted')}
                                </li>
                            ))}
                        </ul>
                    </details>
                </li>
            ))}
        </ul>
    );
}
