import { Link, router } from '@inertiajs/react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { CourseChecks as Checks, CourseStatus } from '@/lib/content';
import { marks, where } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import { status as setStatus } from '@/routes/content/courses';
import { clear, restore } from '@/routes/content/flags';
import { show as showLesson } from '@/routes/content/lessons';

/**
 * A course's checks and review (A5.3b; screen 63): missing approval numbers,
 * legal flags in its lessons (cleared one at a time, with Undo), and the
 * reviewer's Approve and publish or Send back with a note.
 */
export function CourseChecks({
    course,
    checks,
}: {
    course: {
        id: number;
        title: string;
        status: CourseStatus;
        status_note?: string | null;
    };
    checks: Checks;
}) {
    const { t } = useTranslation();
    const [note, setNote] = useState('');
    const open = checks.flags.filter((flag) => !flag.cleared);
    const cleared = checks.flags.filter((flag) => flag.cleared);

    const move = (status: CourseStatus, message: string, withNote = false) =>
        router.put(
            setStatus(course.id).url,
            {
                status,
                note: withNote && note.trim() !== '' ? note.trim() : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(message),
                onError: (errors) => {
                    if (errors.status) toast.error(errors.status);
                },
            },
        );

    return (
        <section
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="checks-title"
        >
            <h2 id="checks-title" className="font-semibold">
                {t('content.checks.title')}
            </h2>

            {checks.approvals.length > 0 ? (
                <p className="flex gap-2 rounded-reos-md bg-reos-tone-warning-bg p-3 text-sm text-reos-tone-warning-fg">
                    <TriangleAlert aria-hidden className="size-5 shrink-0" />
                    {t('content.checks.approvalsMissing', {
                        states: checks.approvals.join(', '),
                    })}
                </p>
            ) : null}

            {open.length === 0 ? (
                <p className="flex items-center gap-2 text-body text-muted-foreground">
                    <CircleCheck
                        aria-hidden
                        className="size-5 text-reos-tone-success-fg"
                    />
                    {t('content.checks.allClear')}
                </p>
            ) : (
                <>
                    <p className="text-sm text-muted-foreground">
                        {t('content.checks.flagIntro')}
                    </p>
                    <ul className="divide-y divide-border">
                        {open.map((flag) => (
                            <li
                                key={flag.key}
                                className="flex flex-wrap items-start gap-3 py-3"
                            >
                                <div className="min-w-0 flex-1 space-y-1">
                                    <p className="text-sm font-semibold text-reos-tone-warning-fg">
                                        {t(`content.checks.rules.${flag.rule}`)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        <Link
                                            href={showLesson(flag.lesson_id)}
                                            className="text-primary underline-offset-4 hover:underline"
                                        >
                                            {flag.lesson_title}
                                        </Link>{' '}
                                        · {where(t, flag)}
                                    </p>
                                    <p className="text-body">
                                        {marks(flag.excerpt).map(
                                            (part, index) =>
                                                part.match ? (
                                                    <mark
                                                        key={index}
                                                        className="rounded-sm bg-reos-tone-warning-bg px-0.5 text-reos-tone-warning-fg"
                                                    >
                                                        {part.text}
                                                    </mark>
                                                ) : (
                                                    <span key={index}>
                                                        {part.text}
                                                    </span>
                                                ),
                                        )}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    aria-label={t('content.checks.clearLabel', {
                                        lesson: flag.lesson_title,
                                    })}
                                    onClick={() =>
                                        router.post(
                                            clear({
                                                course: course.id,
                                                flag: flag.key,
                                            }).url,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    undoToast({
                                                        message: t(
                                                            'content.checks.cleared',
                                                            {
                                                                lesson: flag.lesson_title,
                                                            },
                                                        ),
                                                        onUndo: () =>
                                                            router.delete(
                                                                restore({
                                                                    course: course.id,
                                                                    flag: flag.key,
                                                                }).url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            ),
                                                    }),
                                            },
                                        )
                                    }
                                >
                                    {t('content.checks.clear')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                </>
            )}

            {cleared.length > 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('content.checks.clearedChip')}:{' '}
                    {cleared.map((flag) => flag.lesson_title).join(', ')}
                </p>
            ) : null}

            {course.status === 'in_review' ? (
                <div className="space-y-3 border-t border-border pt-4">
                    <div className="grid gap-2">
                        <Label htmlFor="review-note">
                            {t('content.review.note')}
                        </Label>
                        <Textarea
                            id="review-note"
                            rows={2}
                            maxLength={300}
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button
                            disabled={!checks.clean}
                            onClick={() =>
                                move(
                                    'live',
                                    t('content.review.approved', {
                                        title: course.title,
                                    }),
                                )
                            }
                        >
                            {t('content.review.approve')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                move(
                                    'draft',
                                    t('content.review.sentBack', {
                                        title: course.title,
                                    }),
                                    true,
                                )
                            }
                        >
                            {t('content.review.sendBack')}
                        </Button>
                    </div>
                </div>
            ) : course.status === 'draft' ? (
                <div className="border-t border-border pt-4">
                    <Button
                        variant="outline"
                        onClick={() =>
                            move(
                                'in_review',
                                t('content.review.sent', {
                                    title: course.title,
                                }),
                            )
                        }
                    >
                        {t('content.review.sendForReview')}
                    </Button>
                </div>
            ) : null}
            {course.status === 'draft' && course.status_note ? (
                <p className="text-sm text-muted-foreground">
                    “{course.status_note}”
                </p>
            ) : null}
        </section>
    );
}
