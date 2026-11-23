import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { status as statusRoute } from '@/routes/school/approvals';
import type { DialogMode } from './approval-dialog';
import type { ApprovalRow } from './types';
import { approvalDate, statusTone } from './types';

const th = 'px-3 py-2 text-left';
const td = 'px-3 py-3 align-middle';

/**
 * The state approvals table (screen 50): type, state, approval number, hours,
 * expiry, delivery and status, with Renew, Add state, Edit and Withdraw.
 */
export function ApprovalsTable({
    approvals,
    onOpen,
}: {
    approvals: ApprovalRow[];
    onOpen: (mode: DialogMode) => void;
}) {
    const { t, i18n } = useTranslation();

    const withdraw = (approval: ApprovalRow) => {
        router.put(
            statusRoute(approval.id).url,
            { status: 'withdrawn' },
            {
                preserveScroll: true,
                onSuccess: () =>
                    undoToast({
                        message: t('approvals.withdrawn', {
                            course: approval.course.title,
                            state: approval.state_code,
                        }),
                        onUndo: () =>
                            router.put(
                                statusRoute(approval.id).url,
                                { status: approval.status },
                                { preserveScroll: true },
                            ),
                    }),
            },
        );
    };

    return (
        <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
            <table className="w-full min-w-[60rem] text-sm">
                <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    <tr>
                        {(
                            [
                                'course',
                                'type',
                                'state',
                                'number',
                                'hours',
                                'expiry',
                                'delivery',
                                'status',
                                'actions',
                            ] as const
                        ).map((column) => (
                            <th key={column} scope="col" className={th}>
                                {t(`approvals.columns.${column}`)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {approvals.map((approval) => (
                        <tr key={approval.id}>
                            <th
                                scope="row"
                                className={cn(td, 'text-left font-semibold')}
                            >
                                {approval.course.title}
                                {approval.labels_course ? (
                                    <span className="block font-normal text-muted-foreground">
                                        {t('approvals.labels')}
                                    </span>
                                ) : approval.delivery !==
                                  approval.course.delivery ? (
                                    <span className="block font-normal text-muted-foreground">
                                        {t('approvals.otherDelivery', {
                                            delivery: t(
                                                `approvals.deliveries.${approval.course.delivery}`,
                                            ).toLowerCase(),
                                        })}
                                    </span>
                                ) : null}
                            </th>
                            <td className={td}>
                                {t(
                                    `content.types.${approval.course.type as 'ce'}`,
                                )}
                            </td>
                            <td className={td}>{approval.state_code}</td>
                            <td className={cn(td, 'font-mono')}>
                                {approval.number ?? '—'}
                            </td>
                            <td className={td}>{approval.hours ?? '—'}</td>
                            <td className={td}>
                                {approval.expires_on ? (
                                    <>
                                        {approvalDate(
                                            approval.expires_on,
                                            i18n.language,
                                        )}
                                        {approval.shown_status === 'approved' &&
                                        approval.days_left !== null &&
                                        approval.days_left <= 60 ? (
                                            <span className="block text-muted-foreground">
                                                {t('approvals.expiresIn', {
                                                    count: approval.days_left,
                                                })}
                                            </span>
                                        ) : null}
                                    </>
                                ) : (
                                    t('approvals.noExpiry')
                                )}
                            </td>
                            <td className={td}>
                                {t(`approvals.deliveries.${approval.delivery}`)}
                            </td>
                            <td className={td}>
                                <span
                                    className={cn(
                                        'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                        statusTone(
                                            approval.shown_status,
                                            approval.days_left,
                                        ),
                                    )}
                                >
                                    {t(
                                        `approvals.statuses.${approval.shown_status}`,
                                    )}
                                </span>
                            </td>
                            <td className={td}>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        aria-label={`${t('approvals.renew')}: ${approval.course.title}, ${approval.state_code}, ${t(`approvals.deliveries.${approval.delivery}`)}`}
                                        onClick={() =>
                                            onOpen({ kind: 'renew', approval })
                                        }
                                    >
                                        {t('approvals.renew')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`${t('approvals.edit')}: ${approval.course.title}, ${approval.state_code}, ${t(`approvals.deliveries.${approval.delivery}`)}`}
                                        onClick={() =>
                                            onOpen({ kind: 'edit', approval })
                                        }
                                    >
                                        {t('approvals.edit')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`${t('approvals.addState')}: ${approval.course.title}`}
                                        onClick={() =>
                                            onOpen({
                                                kind: 'add',
                                                courseId: approval.course.id,
                                            })
                                        }
                                    >
                                        {t('approvals.addState')}
                                    </Button>
                                    {approval.status !== 'withdrawn' ? (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            aria-label={`${t('approvals.withdraw')}: ${approval.course.title}, ${approval.state_code}, ${t(`approvals.deliveries.${approval.delivery}`)}`}
                                            onClick={() => withdraw(approval)}
                                        >
                                            {t('approvals.withdraw')}
                                        </Button>
                                    ) : null}
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}
