import { Head, Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { statusTone } from '@/components/builder/types';
import { EmptyState } from '@/components/empty-state';
import type { AiLessonRow } from '@/components/review/types';
import { PageHeader } from '@/components/page-header';
import { cn } from '@/lib/utils';
import { show as reviewShow } from '@/routes/builder/review';

const th = 'px-3 py-2 text-left';
const td = 'px-3 py-3 align-middle';

/**
 * AI lessons (instructor web nav, A8.1): every AI lesson on the courses the
 * person builds, those waiting for review first, each opening its review
 * (screen 44).
 */
export default function AiLessons({ lessons }: { lessons: AiLessonRow[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('review.list.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('review.list.title')}
                    description={t('review.list.description')}
                />
                {lessons.length === 0 ? (
                    <EmptyState
                        icon={Sparkles}
                        title={t('review.list.emptyTitle')}
                        description={t('review.list.empty')}
                    />
                ) : (
                    <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
                        <table className="w-full min-w-[44rem] text-base">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    {(
                                        [
                                            'lesson',
                                            'course',
                                            'status',
                                            'version',
                                            'needs',
                                        ] as const
                                    ).map((c) => (
                                        <th key={c} scope="col" className={th}>
                                            {t(`review.list.${c}`)}
                                        </th>
                                    ))}
                                    <th scope="col" className={th}>
                                        <span className="sr-only">
                                            {t('review.list.review')}
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {lessons.map((lesson) => (
                                    <tr key={lesson.id}>
                                        <th
                                            scope="row"
                                            className={cn(
                                                td,
                                                'text-left font-semibold',
                                            )}
                                        >
                                            {lesson.title}
                                        </th>
                                        <td
                                            className={cn(
                                                td,
                                                'text-muted-foreground',
                                            )}
                                        >
                                            {lesson.course}
                                        </td>
                                        <td className={td}>
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                                    statusTone(lesson.status),
                                                )}
                                            >
                                                {t(
                                                    `builder.lessons.statuses.${lesson.status}`,
                                                )}
                                            </span>
                                        </td>
                                        <td className={td}>
                                            {lesson.version ?? '—'}
                                        </td>
                                        <td className={td}>
                                            {lesson.blockers === 0
                                                ? t('review.list.ready')
                                                : lesson.blockers}
                                        </td>
                                        <td className={td}>
                                            <Link
                                                href={reviewShow(lesson.id)}
                                                aria-label={`${t('review.list.review')}: ${lesson.title}`}
                                                className="inline-flex min-h-touch items-center rounded-reos-md border border-border px-4 font-medium hover:bg-muted"
                                            >
                                                {t('review.list.review')}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}
            </div>
        </>
    );
}
