import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { restore, revoke } from '@/routes/school/instructors';
import type { InstructorRow } from './types';
import { shortDay } from './types';

const th = 'px-3 py-2 text-left';
const td = 'px-3 py-3 align-middle';

/**
 * Instructors (screen 52, A7.1b): approvals, courses, avatar consent, rating,
 * price and status; Edit (courses they teach) and Revoke with Undo.
 */
export function InstructorsTable({
    instructors,
    onEdit,
}: {
    instructors: InstructorRow[];
    onEdit: (instructor: InstructorRow) => void;
}) {
    const { t, i18n } = useTranslation();

    const avatar = (row: InstructorRow): { text: string; tone: string } => {
        switch (row.avatar.status) {
            case 'given':
                return {
                    text: t('instructors.avatar.given', {
                        date: shortDay(row.avatar.given_at, i18n.language),
                    }),
                    tone: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
                };
            case 'withdrawn':
            case 'ended':
                return {
                    text: t(`instructors.avatar.${row.avatar.status}`, {
                        date: shortDay(
                            row.avatar.lessons_due_by,
                            i18n.language,
                        ),
                    }),
                    tone: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
                };
            default:
                return {
                    text: t('instructors.avatar.none'),
                    tone: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
                };
        }
    };

    const revokeOne = (row: InstructorRow) =>
        router.delete(revoke(row.id).url, {
            preserveScroll: true,
            onSuccess: () =>
                undoToast({
                    message: t('instructors.revoked', { name: row.name }),
                    onUndo: () =>
                        router.post(
                            restore(row.id).url,
                            {},
                            { preserveScroll: true },
                        ),
                }),
        });

    return (
        <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
            <table className="w-full min-w-[60rem] text-sm">
                <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    <tr>
                        {(
                            [
                                'instructor',
                                'approvals',
                                'courses',
                                'avatar',
                                'rating',
                                'price',
                                'status',
                                'actions',
                            ] as const
                        ).map((column) => (
                            <th key={column} scope="col" className={th}>
                                {t(`instructors.columns.${column}`)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {instructors.map((row) => {
                        const consent = avatar(row);

                        return (
                            <tr key={row.id}>
                                <th scope="row" className={cn(td, 'text-left')}>
                                    <span className="block font-semibold">
                                        {row.name}
                                    </span>
                                    {row.email ? (
                                        <span className="block font-normal break-all text-muted-foreground">
                                            {row.email}
                                        </span>
                                    ) : null}
                                </th>
                                <td className={td}>
                                    {t('instructors.approvals', {
                                        count: row.approvals,
                                    })}
                                </td>
                                <td className={td}>
                                    {row.courses.length > 0 ? (
                                        row.courses
                                            .map((c) => c.title)
                                            .join(', ')
                                    ) : (
                                        <span className="text-muted-foreground">
                                            {t('instructors.noCourses')}
                                        </span>
                                    )}
                                </td>
                                <td className={td}>
                                    {row.status === 'active' ? (
                                        <span
                                            className={cn(
                                                'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                                consent.tone,
                                            )}
                                        >
                                            {consent.text}
                                        </span>
                                    ) : (
                                        '—'
                                    )}
                                </td>
                                <td className={cn(td, 'text-muted-foreground')}>
                                    {row.rating ?? t('instructors.noRating')}
                                </td>
                                <td className={cn(td, 'text-muted-foreground')}>
                                    {row.price ?? t('instructors.noPrice')}
                                </td>
                                <td className={td}>
                                    {t(`instructors.statuses.${row.status}`)}
                                </td>
                                <td className={td}>
                                    {row.status === 'active' ? (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                aria-label={`${t('instructors.edit')}: ${row.name}`}
                                                onClick={() => onEdit(row)}
                                            >
                                                {t('instructors.edit')}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                aria-label={`${t('instructors.revoke')}: ${row.name}`}
                                                onClick={() => revokeOne(row)}
                                            >
                                                {t('instructors.revoke')}
                                            </Button>
                                        </div>
                                    ) : null}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </section>
    );
}
