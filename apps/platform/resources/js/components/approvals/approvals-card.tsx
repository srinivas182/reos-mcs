import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { index as approvalsIndex } from '@/routes/school/approvals';
import type { ApprovalSummary } from './types';
import { approvalDate, statusTone } from './types';

/**
 * The school dashboard's approvals card (screen 49, A7.1): approvals in
 * force and what needs attention: expiring within 60 days, expired, or
 * waiting for the regulator.
 */
export function ApprovalsCard({ summary }: { summary: ApprovalSummary }) {
    const { t, i18n } = useTranslation();

    return (
        <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                    <h2 className="text-lg font-semibold">
                        {t('approvals.dashboard.title')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('approvals.dashboard.inForce', {
                            count: summary.in_force,
                        })}{' '}
                        ·{' '}
                        {t('approvals.dashboard.courses', {
                            count: summary.courses,
                        })}
                    </p>
                </div>
                <Link
                    href={approvalsIndex().url}
                    className="inline-flex min-h-12 items-center text-base font-medium text-primary underline-offset-4 hover:underline"
                >
                    {t('approvals.dashboard.open')}
                </Link>
            </div>
            <h3 className="font-semibold">
                {t('approvals.dashboard.attention')}
            </h3>
            {summary.attention.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    {t('approvals.dashboard.allGood')}
                </p>
            ) : (
                <ul className="space-y-2">
                    {summary.attention.map((item) => (
                        <li
                            key={item.id}
                            className="flex flex-wrap items-center gap-3"
                        >
                            <span
                                className={cn(
                                    'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                    statusTone(item.status, item.days_left),
                                )}
                            >
                                {t(`approvals.statuses.${item.status}`)}
                            </span>
                            <span className="text-base">
                                {t(
                                    item.status === 'pending'
                                        ? 'approvals.dashboard.pending'
                                        : item.status === 'expired'
                                          ? 'approvals.dashboard.expired'
                                          : 'approvals.dashboard.expiring',
                                    {
                                        course: item.course,
                                        state: item.state_code,
                                        date: approvalDate(
                                            item.expires_on,
                                            i18n.language,
                                        ),
                                    },
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
