import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CourseStatusBadge } from '@/components/course-status-badge';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { CourseStatus } from '@/lib/content';
import { cn } from '@/lib/utils';
import { index, library } from '@/routes/content';
import { show } from '@/routes/content/courses';

type Item = {
    id: number;
    title: string;
    lessons: number;
    tenant: string | null;
    states: string[] | null;
    status: CourseStatus;
    missing_approvals: string[];
    flags: number;
};

const RULES = [
    'approval',
    'legal',
    'instructor',
    'sources',
    'tenants',
] as const;

/**
 * Content and approvals (web journey screen 63, A5.3b): the review queue
 * with each item's tenant, state, checks and status, and the rules every
 * piece of content meets before it's live. "Content library" opens the
 * courses; "Review queue" shows only what waits for a reviewer.
 */
export default function ContentApprovals({
    items,
    waiting,
    queueOnly,
}: {
    items: Item[];
    waiting: number;
    queueOnly: boolean;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('content.review.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('content.review.title')}
                    description={t('content.review.description')}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={library()}>
                                    {t('content.review.library')}
                                </Link>
                            </Button>
                            <Button
                                variant={queueOnly ? 'outline' : 'default'}
                                asChild
                            >
                                <Link
                                    href={
                                        queueOnly
                                            ? index()
                                            : index({ query: { queue: 1 } })
                                    }
                                >
                                    {queueOnly
                                        ? t('content.review.all')
                                        : t('content.review.queue', {
                                              count: waiting,
                                          })}
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="font-semibold">
                            {queueOnly
                                ? t('content.review.listTitle')
                                : t('content.review.allTitle')}
                        </h2>
                        {items.length === 0 ? (
                            <EmptyState
                                icon={ClipboardCheck}
                                title={t('content.review.emptyTitle')}
                                description={t('content.review.empty')}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-base">
                                    <caption className="sr-only">
                                        {t('content.review.listTitle')}
                                    </caption>
                                    <thead className="text-sm text-muted-foreground uppercase">
                                        <tr className="border-b border-border">
                                            {(
                                                [
                                                    'item',
                                                    'tenant',
                                                    'state',
                                                    'checks',
                                                    'status',
                                                ] as const
                                            ).map((column) => (
                                                <th
                                                    key={column}
                                                    scope="col"
                                                    className="px-3 py-3 font-semibold"
                                                >
                                                    {t(
                                                        `content.review.columns.${column}`,
                                                    )}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b border-border last:border-b-0"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-3 py-2 font-medium"
                                                >
                                                    <Link
                                                        href={show(item.id)}
                                                        className="inline-flex min-h-12 flex-col justify-center text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {item.title}
                                                        <span className="text-sm font-normal text-muted-foreground">
                                                            {t(
                                                                'learn.lessons',
                                                                {
                                                                    count: item.lessons,
                                                                },
                                                            )}
                                                        </span>
                                                    </Link>
                                                </th>
                                                <td className="px-3 py-2">
                                                    {item.tenant ??
                                                        t(
                                                            'content.review.reos',
                                                        )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.states === null
                                                        ? t(
                                                              'content.everyState',
                                                          )
                                                        : item.states.join(
                                                              ', ',
                                                          )}
                                                </td>
                                                <td
                                                    className={cn(
                                                        'px-3 py-2',
                                                        item.flags > 0 ||
                                                            item
                                                                .missing_approvals
                                                                .length > 0
                                                            ? 'text-reos-tone-warning-fg'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {[
                                                        item.flags > 0
                                                            ? t(
                                                                  'content.checks.flags',
                                                                  {
                                                                      count: item.flags,
                                                                  },
                                                              )
                                                            : null,
                                                        item.missing_approvals
                                                            .length > 0
                                                            ? t(
                                                                  'content.checks.approval',
                                                              )
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') ||
                                                        t(
                                                            'content.checks.clean',
                                                        )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <CourseStatusBadge
                                                        status={item.status}
                                                        noApproval={
                                                            item
                                                                .missing_approvals
                                                                .length > 0
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="font-semibold">
                            {t('content.rules.title')}
                        </h2>
                        <ul className="space-y-3">
                            {RULES.map((rule) => (
                                <li key={rule} className="flex gap-3 text-body">
                                    <ShieldCheck
                                        aria-hidden
                                        className="mt-0.5 size-5 shrink-0 text-primary"
                                    />
                                    {t(`content.rules.${rule}`)}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}
