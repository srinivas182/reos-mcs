import { Head, Link, router } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { QuestionDialog } from '@/components/review/question-dialog';
import type { DisputeRow, QuestionRow } from '@/components/review/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { decide, index, resolve } from '@/routes/builder/questions';

type Tab = 'to_review' | 'approved' | 'rejected' | 'disputed';

const th = 'px-3 py-2 text-left';
const td = 'px-3 py-3 align-top';

/**
 * Question bank (instructor web screen 45, A8.1): to review, approved,
 * rejected and disputed; approve, edit, reject; resolve learner disputes.
 * Every question needs a source and an exam-outline mapping.
 */
export default function QuestionBankPage({
    tab,
    counts,
    questions,
    disputes,
    topics,
    portions,
    difficulties,
}: {
    tab: Tab;
    counts: Record<Tab, number>;
    questions: QuestionRow[];
    disputes: DisputeRow[];
    topics: string[];
    portions: string[];
    difficulties: string[];
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<QuestionRow | null>(null);
    const choose = (question: QuestionRow, decision: 'approve' | 'reject') =>
        router.post(
            decide(question.id).url,
            { decision },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('questions.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('questions.title')}
                    description={t('questions.description')}
                />
                <nav
                    aria-label={t('questions.title')}
                    className="flex flex-wrap gap-2"
                >
                    {(
                        [
                            'to_review',
                            'approved',
                            'rejected',
                            'disputed',
                        ] as const
                    ).map((key) => (
                        <Link
                            key={key}
                            href={index({ query: { tab: key } })}
                            aria-current={tab === key ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-touch items-center rounded-reos-pill border px-4 font-medium',
                                tab === key
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-card hover:bg-muted',
                            )}
                        >
                            {t(`questions.tabs.${key}`, { count: counts[key] })}
                        </Link>
                    ))}
                </nav>

                {tab === 'disputed' ? (
                    disputes.length === 0 ? (
                        <EmptyState
                            icon={ListChecks}
                            title={t('questions.disputes.emptyTitle')}
                            description={t('questions.disputes.empty')}
                        />
                    ) : (
                        <ul className="space-y-4">
                            {disputes.map((d) => (
                                <Dispute key={d.id} dispute={d} />
                            ))}
                        </ul>
                    )
                ) : questions.length === 0 ? (
                    <EmptyState
                        icon={ListChecks}
                        title={t('questions.emptyTitle')}
                        description={t('questions.empty')}
                    />
                ) : (
                    <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
                        <table className="w-full min-w-[56rem] text-base">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    {(
                                        [
                                            'question',
                                            'topic',
                                            'difficulty',
                                            'source',
                                            'outline',
                                            'actions',
                                        ] as const
                                    ).map((c) => (
                                        <th key={c} scope="col" className={th}>
                                            {t(`questions.columns.${c}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {questions.map((q) => (
                                    <tr key={q.id}>
                                        <th
                                            scope="row"
                                            className={cn(
                                                td,
                                                'max-w-md text-left font-normal',
                                            )}
                                        >
                                            <span className="block font-semibold">
                                                {q.en?.prompt}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {q.lesson} · {q.course}
                                            </span>
                                        </th>
                                        <td className={td}>
                                            {q.topic
                                                ? t(
                                                      `onboarding.topics.${q.topic as 'agency'}`,
                                                  )
                                                : '—'}
                                        </td>
                                        <td className={td}>
                                            {q.difficulty
                                                ? t(
                                                      `questions.difficulties.${q.difficulty}`,
                                                  )
                                                : '—'}
                                        </td>
                                        <td
                                            className={cn(
                                                td,
                                                'text-sm text-muted-foreground',
                                            )}
                                        >
                                            {q.source || '—'}
                                        </td>
                                        <td className={td}>
                                            {t(
                                                `questions.portions.${q.portion ?? 'none'}`,
                                            )}
                                        </td>
                                        <td className={td}>
                                            <div className="flex flex-wrap gap-2">
                                                {q.status !== 'approved' ? (
                                                    <Button
                                                        size="sm"
                                                        aria-label={`${t('questions.approve')}: ${q.en?.prompt ?? q.id}`}
                                                        onClick={() =>
                                                            choose(q, 'approve')
                                                        }
                                                    >
                                                        {t('questions.approve')}
                                                    </Button>
                                                ) : null}
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    aria-label={`${t('questions.edit')}: ${q.en?.prompt ?? q.id}`}
                                                    onClick={() =>
                                                        setEditing(q)
                                                    }
                                                >
                                                    {t('questions.edit')}
                                                </Button>
                                                {q.status !== 'rejected' ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label={`${t('questions.reject')}: ${q.en?.prompt ?? q.id}`}
                                                        onClick={() =>
                                                            choose(q, 'reject')
                                                        }
                                                    >
                                                        {t('questions.reject')}
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}
            </div>
            <QuestionDialog
                question={editing}
                topics={topics}
                difficulties={difficulties}
                portions={portions}
                onClose={() => setEditing(null)}
            />
        </>
    );
}

/** One learner report: the question, why, and Keep or Corrected with what to tell them. */
function Dispute({ dispute }: { dispute: DisputeRow }) {
    const { t } = useTranslation();
    const [resolution, setResolution] = useState('');
    const send = (outcome: 'keep' | 'correct') =>
        router.post(
            resolve(dispute.id).url,
            { outcome, resolution },
            { preserveScroll: true },
        );

    return (
        <li className="space-y-3 rounded-reos-lg border border-border bg-card p-4">
            <p className="font-semibold">{dispute.question.text}</p>
            <p className="text-base">
                {t('questions.disputes.answer', {
                    answer:
                        dispute.question.options[dispute.question.correct] ??
                        '',
                })}
            </p>
            <p className="text-sm text-muted-foreground">
                {dispute.question.source}
            </p>
            <p className="text-base">
                <span className="font-semibold">
                    {t(`questions.disputes.reason.${dispute.reason}`)}
                </span>
                {dispute.details ? ` · ${dispute.details}` : ''}
            </p>
            <p className="text-sm text-muted-foreground">
                {t('questions.disputes.by', { name: dispute.by })}
            </p>
            <div className="grid gap-2">
                <Label htmlFor={`resolution-${dispute.id}`}>
                    {t('questions.disputes.resolution')}
                </Label>
                <Textarea
                    id={`resolution-${dispute.id}`}
                    value={resolution}
                    maxLength={2000}
                    onChange={(e) => setResolution(e.target.value)}
                />
                <p className="text-sm text-muted-foreground">
                    {t('questions.disputes.correctHint')}
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={resolution.trim().length < 5}
                    onClick={() => send('keep')}
                >
                    {t('questions.disputes.keep')}
                </Button>
                <Button
                    size="sm"
                    disabled={resolution.trim().length < 5}
                    onClick={() => send('correct')}
                >
                    {t('questions.disputes.correct')}
                </Button>
            </div>
        </li>
    );
}
