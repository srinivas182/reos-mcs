import { Link, router } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { QuestionDialog } from '@/components/review/question-dialog';
import type { QuestionRow } from '@/components/review/types';
import { Button } from '@/components/ui/button';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { index } from '@/routes/builder/questions';
import { decide, update } from '@/routes/builder/starter';

export type StarterTopic =
    | 'agency'
    | 'contracts'
    | 'math'
    | 'ownership'
    | 'finance'
    | 'state_rules';

export type StarterBankData = {
    topic: StarterTopic | null;
    counts: {
        to_review: number;
        approved: number;
        retired: number;
        topics: Record<StarterTopic, number>;
    };
    questions: QuestionRow[];
};

type Decision = 'approve' | 'retire' | 'restore';

const send = (ids: number[], decision: Decision) =>
    new Promise<void>((resolve) =>
        router.post(
            decide().url,
            { ids, decision },
            { preserveScroll: true, onFinish: () => resolve() },
        ),
    );

/**
 * The Starter bank tab of the question bank (screen 45, A9.1, Sprint 9
 * decision 2): REOS's 300 Texas questions, by topic, for a school admin to
 * approve, edit or retire. Each decision can be undone from its toast.
 */
export function StarterBank({
    data,
    topics,
    difficulties,
    portions,
}: {
    data: StarterBankData;
    topics: string[];
    difficulties: string[];
    portions: string[];
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<QuestionRow | null>(null);
    const { counts, questions, topic } = data;
    const ids = questions.map((q) => q.id);

    const approve = (q: QuestionRow) =>
        void send([q.id], 'approve').then(() =>
            undoToast({
                message: t('questions.starter.approved'),
                onUndo: () => send([q.id], 'restore'),
            }),
        );
    const retire = (q: QuestionRow) =>
        void send([q.id], 'retire').then(() =>
            undoToast({
                message: t('questions.starter.retired'),
                onUndo: () => send([q.id], 'restore'),
            }),
        );
    const approveAll = () =>
        void send(ids, 'approve').then(() =>
            undoToast({
                message: t('questions.starter.approvedAll', {
                    count: ids.length,
                }),
                onUndo: () => send(ids, 'restore'),
            }),
        );
    const filters: (StarterTopic | null)[] = [
        null,
        'agency',
        'contracts',
        'ownership',
        'finance',
        'math',
        'state_rules',
    ];

    return (
        <section className="space-y-4" aria-labelledby="starter-title">
            <div className="space-y-2">
                <h2 id="starter-title" className="text-xl font-semibold">
                    {t('questions.starter.title')}
                </h2>
                <p className="max-w-3xl text-base text-muted-foreground">
                    {t('questions.starter.description')}
                </p>
                <p className="text-base font-medium">
                    {t('questions.starter.progress', {
                        toReview: counts.to_review,
                        approved: counts.approved,
                        retired: counts.retired,
                    })}
                </p>
            </div>

            <nav
                aria-label={t('questions.starter.filter')}
                className="flex flex-wrap gap-2"
            >
                {filters.map((key) => (
                    <Link
                        key={key ?? 'all'}
                        href={index({
                            query: key
                                ? { tab: 'starter', topic: key }
                                : { tab: 'starter' },
                        })}
                        aria-current={topic === key ? 'page' : undefined}
                        preserveScroll
                        className={cn(
                            'inline-flex min-h-touch items-center rounded-reos-pill border px-4 font-medium',
                            topic === key
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-card hover:bg-muted',
                        )}
                    >
                        {key
                            ? t('questions.starter.topic', {
                                  topic: t(`onboarding.topics.${key}`),
                                  count: counts.topics[key],
                              })
                            : t('questions.starter.all', {
                                  count: counts.to_review,
                              })}
                    </Link>
                ))}
            </nav>

            {questions.length === 0 ? (
                <EmptyState
                    icon={ListChecks}
                    title={t('questions.starter.emptyTitle')}
                    description={t('questions.starter.empty')}
                />
            ) : (
                <>
                    <Button onClick={approveAll}>
                        {t('questions.starter.approveAll', {
                            count: questions.length,
                        })}
                    </Button>
                    <ol className="space-y-4">
                        {questions.map((q) => (
                            <StarterQuestion
                                key={q.id}
                                question={q}
                                onApprove={() => approve(q)}
                                onEdit={() => setEditing(q)}
                                onRetire={() => retire(q)}
                            />
                        ))}
                    </ol>
                </>
            )}
            <QuestionDialog
                question={editing}
                topics={topics}
                difficulties={difficulties}
                portions={portions}
                url={(id) => update(id).url}
                onClose={() => setEditing(null)}
            />
        </section>
    );
}

function StarterQuestion({
    question,
    onApprove,
    onEdit,
    onRetire,
}: {
    question: QuestionRow;
    onApprove: () => void;
    onEdit: () => void;
    onRetire: () => void;
}) {
    const { t } = useTranslation();
    const en = question.en;
    const es = question.es;
    const label = en?.prompt ?? String(question.id);

    return (
        <li className="space-y-3 rounded-reos-lg border border-border bg-card p-4">
            <p className="text-sm text-muted-foreground">
                {question.topic
                    ? t(`onboarding.topics.${question.topic as StarterTopic}`)
                    : null}
                {' · '}
                {t(`questions.portions.${question.portion ?? 'none'}`)}
            </p>
            <p className="text-base font-semibold">{en?.prompt}</p>
            <ol className="list-[upper-alpha] space-y-1 pl-6 text-base">
                {en?.options.map((option, i) => (
                    <li
                        key={option}
                        className={cn(
                            i === question.correct && 'font-semibold',
                        )}
                    >
                        {option}
                    </li>
                ))}
            </ol>
            <p className="text-base">
                {t('questions.starter.answer', {
                    answer: en?.options[question.correct] ?? '',
                })}
            </p>
            {en?.explanation ? (
                <p className="text-base text-muted-foreground">
                    {t('questions.starter.why', { why: en.explanation })}
                </p>
            ) : null}
            <p className="text-sm text-muted-foreground">{question.source}</p>
            {es ? (
                <details className="rounded-reos-md bg-muted px-3 py-2">
                    <summary className="min-h-touch cursor-pointer content-center font-medium">
                        {t('questions.starter.spanish')}
                    </summary>
                    <div lang="es" className="space-y-2 pt-2 text-base">
                        <p className="font-semibold">{es.prompt}</p>
                        <ol className="list-[upper-alpha] space-y-1 pl-6">
                            {es.options.map((option, i) => (
                                <li
                                    key={option}
                                    className={cn(
                                        i === question.correct &&
                                            'font-semibold',
                                    )}
                                >
                                    {option}
                                </li>
                            ))}
                        </ol>
                        {es.explanation ? (
                            <p className="text-muted-foreground">
                                {es.explanation}
                            </p>
                        ) : null}
                    </div>
                </details>
            ) : null}
            <div className="flex flex-wrap gap-2">
                <Button
                    aria-label={`${t('questions.approve')}: ${label}`}
                    onClick={onApprove}
                >
                    {t('questions.approve')}
                </Button>
                <Button
                    variant="outline"
                    aria-label={`${t('questions.edit')}: ${label}`}
                    onClick={onEdit}
                >
                    {t('questions.edit')}
                </Button>
                <Button
                    variant="ghost"
                    aria-label={`${t('questions.starter.retire')}: ${label}`}
                    onClick={onRetire}
                >
                    {t('questions.starter.retire')}
                </Button>
            </div>
        </li>
    );
}
