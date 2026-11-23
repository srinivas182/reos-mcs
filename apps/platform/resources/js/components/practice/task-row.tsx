import { Link } from '@inertiajs/react';
import {
    BookOpen,
    CheckCircle2,
    ClipboardCheck,
    RotateCcw,
    Target,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { lesson as openLesson } from '@/routes/learn';
import { index as practiceIndex } from '@/routes/practice';
import type { StudyTask } from './types';

const icons = {
    diagnostic: ClipboardCheck,
    lesson: BookOpen,
    practice: Target,
    review: RotateCcw,
} as const;

/** Colour by type (web screen 6): lessons, quizzes, review, the diagnostic. */
export const taskTone: Record<StudyTask['kind'], string> = {
    diagnostic: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    lesson: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    practice: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    review: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
};

/** What a task is, in words: "Agency disclosure · 20 min". */
export function useTaskTitle() {
    const { t } = useTranslation();

    return (task: StudyTask) =>
        task.kind === 'lesson' && task.lesson
            ? task.lesson.title
            : task.kind === 'diagnostic'
              ? t('practice.tasks.diagnostic')
              : t(`practice.tasks.${task.kind}`, {
                    topic: task.topic ? t(`practice.topics.${task.topic}`) : '',
                });
}

/**
 * A study task with why it's there (A8.3): opens the lesson, or the
 * practice hub where its quiz starts.
 */
export function TaskRow({ task }: { task: StudyTask }) {
    const { t } = useTranslation();
    const title = useTaskTitle()(task);
    const Icon = task.done ? CheckCircle2 : icons[task.kind];
    const href =
        task.kind === 'lesson' && task.lesson
            ? openLesson(task.lesson.id)
            : practiceIndex();

    return (
        <Link
            href={href}
            className="flex min-h-12 items-start gap-3 rounded-reos-md p-3 hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <span
                className={cn(
                    'mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-reos-md',
                    taskTone[task.kind],
                )}
            >
                <Icon aria-hidden className="size-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block font-medium',
                        task.done && 'text-muted-foreground line-through',
                    )}
                >
                    {title}
                </span>
                <span className="block text-sm text-muted-foreground">
                    {[
                        t('practice.tasks.minutes', { count: task.minutes }),
                        task.lesson?.course,
                        t(`practice.why.${task.why}`),
                        task.done ? t('practice.tasks.done') : null,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
            </span>
        </Link>
    );
}
