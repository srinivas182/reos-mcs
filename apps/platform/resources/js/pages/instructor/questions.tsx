import { Head, Link, router } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AnswerQuestion } from '@/components/ask/answer-question';
import type { StudentQuestion } from '@/components/ask/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import { answer, index } from '@/routes/instructor/questions';

/**
 * Student questions (instructor screens i09 and i10, A10.1b): what the
 * course content couldn't answer, waiting or answered, with the answer
 * beside the list.
 */
export default function StudentQuestionsPage({
    data,
    counts,
    status,
    selected,
}: {
    data: StudentQuestion[];
    counts: { waiting: number; answered: number; from_content: number };
    status: 'waiting' | 'answered';
    selected: StudentQuestion | null;
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [done, setDone] = useState<string | null>(null);
    const open = selected ?? null;

    const send = async (values: {
        answer: string;
        add_to_knowledge: boolean;
        flashcard: boolean;
    }) => {
        if (!open) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            await postJson(answer(open.uuid).url, values);
            setDone(t('ask.instructor.sentToStudent', { name: open.student }));
            router.visit(index({ query: { status: 'waiting' } }).url, {
                preserveScroll: true,
            });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={t('ask.instructor.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('ask.instructor.title')}
                    description={t('ask.instructor.purpose')}
                />
                <p className="text-sm text-muted-foreground">
                    {t('ask.instructor.fromContent', {
                        count: counts.from_content,
                    })}
                </p>
                {done ? (
                    <p
                        className="text-sm text-reos-tone-success-fg"
                        aria-live="polite"
                    >
                        {done}
                    </p>
                ) : null}
                <nav
                    aria-label={t('ask.instructor.title')}
                    className="flex gap-2"
                >
                    {(['waiting', 'answered'] as const).map((tab) => (
                        <Link
                            key={tab}
                            href={index({ query: { status: tab } }).url}
                            aria-current={status === tab ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-12 items-center rounded-reos-pill border border-border px-4 font-medium',
                                status === tab &&
                                    'border-primary bg-primary text-primary-foreground',
                            )}
                        >
                            {tab === 'waiting'
                                ? `${t('ask.instructor.waiting')} (${counts.waiting})`
                                : `${t('ask.instructor.answeredTab')} (${counts.answered})`}
                        </Link>
                    ))}
                </nav>
                <div className="grid gap-6 lg:grid-cols-[22rem_1fr]">
                    {data.length === 0 ? (
                        <EmptyState
                            icon={Inbox}
                            title={t('ask.instructor.emptyTitle')}
                            description={t('ask.instructor.empty')}
                        />
                    ) : (
                        <ul className="space-y-2">
                            {data.map((q) => (
                                <li key={q.uuid}>
                                    <Link
                                        href={
                                            index({
                                                query: {
                                                    status,
                                                    question: q.uuid,
                                                },
                                            }).url
                                        }
                                        preserveScroll
                                        aria-current={
                                            open?.uuid === q.uuid
                                                ? 'page'
                                                : undefined
                                        }
                                        className="block min-h-12 rounded-reos-lg border border-border p-3 hover:bg-muted aria-[current=page]:border-primary"
                                    >
                                        <span className="block font-medium">
                                            {q.question}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {t('ask.instructor.askedBy', {
                                                student: q.student,
                                                course: q.course,
                                            })}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    {open ? (
                        <AnswerQuestion
                            key={open.uuid}
                            question={open}
                            busy={busy}
                            error={error}
                            onSend={(values) => void send(values)}
                        />
                    ) : data.length > 0 ? (
                        <p className="text-muted-foreground">
                            {t('ask.instructor.select')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}
