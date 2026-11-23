import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FileText } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import {
    approve,
    destroy,
    move,
    restore,
    store,
    update,
} from '@/routes/builder/lessons';
import type { LessonRow } from './types';
import { statusTone } from './types';

/** A module's lessons with their status (screen 43): add, edit, order, approve, remove with Undo. */
export function LessonList({
    moduleId,
    lessons,
}: {
    moduleId: number;
    lessons: LessonRow[];
}) {
    const { t } = useTranslation();
    const [title, setTitle] = useState('');
    const [editing, setEditing] = useState<LessonRow | null>(null);

    const remove = (lesson: LessonRow) =>
        router.delete(destroy(lesson.id).url, {
            preserveScroll: true,
            onSuccess: () =>
                undoToast({
                    message: t('builder.lessons.removed'),
                    onUndo: () =>
                        router.post(
                            restore(lesson.id).url,
                            {},
                            { preserveScroll: true },
                        ),
                }),
        });

    return (
        <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            <h2 className="text-lg font-semibold">
                {t('builder.lessons.title')}
            </h2>
            {lessons.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title={t('builder.lessons.emptyTitle')}
                    description={t('builder.lessons.empty')}
                />
            ) : (
                <ol className="divide-y divide-border">
                    {lessons.map((lesson, index) => (
                        <li
                            key={lesson.id}
                            className="flex flex-wrap items-center gap-2 py-3"
                        >
                            <span className="min-w-0 flex-1 font-semibold">
                                {lesson.title}
                            </span>
                            <span
                                className={cn(
                                    'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                    statusTone(lesson.status),
                                )}
                            >
                                {t(`builder.lessons.statuses.${lesson.status}`)}
                            </span>
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={index === 0}
                                aria-label={`${t('builder.lessons.up')}: ${lesson.title}`}
                                onClick={() =>
                                    router.post(
                                        move(lesson.id).url,
                                        { direction: 'up' },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ArrowUp aria-hidden />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={index === lessons.length - 1}
                                aria-label={`${t('builder.lessons.down')}: ${lesson.title}`}
                                onClick={() =>
                                    router.post(
                                        move(lesson.id).url,
                                        { direction: 'down' },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ArrowDown aria-hidden />
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                aria-label={`${t('builder.lessons.edit')}: ${lesson.title}`}
                                onClick={() => setEditing(lesson)}
                            >
                                {t('builder.lessons.edit')}
                            </Button>
                            {lesson.status === 'draft' ? (
                                <Button
                                    size="sm"
                                    disabled={!lesson.has_body}
                                    aria-label={`${t('builder.lessons.approve')}: ${lesson.title}`}
                                    onClick={() =>
                                        router.post(
                                            approve(lesson.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('builder.lessons.approve')}
                                </Button>
                            ) : null}
                            <Button
                                size="sm"
                                variant="ghost"
                                aria-label={`${t('builder.lessons.remove')}: ${lesson.title}`}
                                onClick={() => remove(lesson)}
                            >
                                {t('builder.lessons.remove')}
                            </Button>
                        </li>
                    ))}
                </ol>
            )}
            <form
                className="flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.post(
                        store(moduleId).url,
                        { title },
                        { preserveScroll: true, onSuccess: () => setTitle('') },
                    );
                }}
            >
                <div className="grid min-w-64 flex-1 gap-2">
                    <Label htmlFor="new-lesson">
                        {t('builder.lessons.lessonTitle')}
                    </Label>
                    <Input
                        id="new-lesson"
                        value={title}
                        maxLength={200}
                        onChange={(e) => setTitle(e.target.value)}
                    />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={title.trim().length < 2}
                >
                    {t('builder.lessons.add')}
                </Button>
            </form>
            <LessonDialog lesson={editing} onClose={() => setEditing(null)} />
        </section>
    );
}

function LessonDialog({
    lesson,
    onClose,
}: {
    lesson: LessonRow | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog
            open={lesson !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{lesson?.title}</DialogTitle>
                    <DialogDescription>
                        {t('builder.lessons.bodyHint')}
                    </DialogDescription>
                </DialogHeader>
                {lesson ? (
                    <LessonForm
                        key={lesson.id}
                        lesson={lesson}
                        onDone={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function LessonForm({
    lesson,
    onDone,
}: {
    lesson: LessonRow;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState({
        title: lesson.title,
        body: lesson.body ?? '',
        minutes: lesson.minutes === null ? '' : String(lesson.minutes),
    });

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                const payload: Record<string, string | number | null> = {
                    title: draft.title,
                    minutes:
                        draft.minutes === '' ? null : Number(draft.minutes),
                };

                if (draft.body !== (lesson.body ?? '')) {
                    payload.body = draft.body;
                }

                router.patch(update(lesson.id).url, payload, {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="lesson-title">
                    {t('builder.lessons.lessonTitle')}
                </Label>
                <Input
                    id="lesson-title"
                    value={draft.title}
                    maxLength={200}
                    onChange={(e) =>
                        setDraft({ ...draft, title: e.target.value })
                    }
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="lesson-body">{t('builder.lessons.body')}</Label>
                <Textarea
                    id="lesson-body"
                    rows={10}
                    value={draft.body}
                    onChange={(e) =>
                        setDraft({ ...draft, body: e.target.value })
                    }
                />
            </div>
            <div className="grid max-w-40 gap-2">
                <Label htmlFor="lesson-minutes">
                    {t('builder.lessons.minutes')}
                </Label>
                <Input
                    id="lesson-minutes"
                    type="number"
                    min={1}
                    max={240}
                    value={draft.minutes}
                    onChange={(e) =>
                        setDraft({ ...draft, minutes: e.target.value })
                    }
                />
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('builder.cancel')}
                </Button>
                <Button type="submit">{t('builder.lessons.save')}</Button>
            </DialogFooter>
        </form>
    );
}
