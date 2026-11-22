import { BookOpen, CheckCircle2, CircleHelp, XCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { PracticeQuestion, Share } from '@/components/sessions/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { lesson as lessonRoute } from '@/routes/learn';

/** One practice question, answered in the room: the answer and why show after a choice. */
function Question({ question, n }: { question: PracticeQuestion; n: number }) {
    const { t } = useTranslation();
    const [chosen, setChosen] = useState<number | null>(null);
    const right = chosen === question.correct;

    return (
        <fieldset className="space-y-3">
            <legend className="text-base font-semibold">
                {n}. {question.question}
            </legend>
            <div className="grid gap-2">
                {question.options.map((option, i) => (
                    <Button
                        key={option}
                        type="button"
                        variant="outline"
                        disabled={chosen !== null}
                        aria-pressed={chosen === i}
                        className={cn(
                            'h-auto min-h-12 justify-start text-left whitespace-normal',
                            chosen !== null &&
                                i === question.correct &&
                                'border-reos-tone-success-fg bg-reos-tone-success-bg text-reos-tone-success-fg',
                            chosen === i &&
                                !right &&
                                'border-reos-tone-danger-fg bg-reos-tone-danger-bg text-reos-tone-danger-fg',
                        )}
                        onClick={() => setChosen(i)}
                    >
                        {option}
                    </Button>
                ))}
            </div>
            {chosen !== null ? (
                <p className="flex items-start gap-2 text-base" role="status">
                    {right ? (
                        <CheckCircle2
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0"
                        />
                    ) : (
                        <XCircle
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0"
                        />
                    )}
                    <span>
                        <strong>
                            {right
                                ? t('sessions.share.right')
                                : t('sessions.share.wrong')}
                        </strong>{' '}
                        {question.explanation}{' '}
                        <span className="text-muted-foreground">
                            {t('sessions.share.source', {
                                source: question.source,
                            })}
                        </span>
                    </span>
                </p>
            ) : null}
        </fieldset>
    );
}

/**
 * What the host shared in the session (B6.3b quick actions): lessons to
 * open, and practice questions answered right here. Kept with the session.
 */
export function SessionShares({ shares }: { shares: Share[] }) {
    const { t } = useTranslation();

    if (shares.length === 0) {
        return null;
    }

    return (
        <section className="space-y-4" aria-label={t('sessions.share.shared')}>
            <h2 className="text-lg font-semibold">
                {t('sessions.share.shared')}
            </h2>
            {shares.map((share) =>
                share.kind === 'lesson' && share.lesson ? (
                    <article
                        key={share.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-reos-lg border border-border bg-card p-4"
                    >
                        <div className="flex items-start gap-3">
                            <BookOpen aria-hidden className="mt-1 size-5" />
                            <div>
                                <h3 className="text-base font-semibold">
                                    {share.lesson.title}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {share.lesson.course}
                                </p>
                            </div>
                        </div>
                        <Button asChild variant="outline">
                            <a
                                href={lessonRoute(share.lesson.id).url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                {t('sessions.share.openLesson')}
                            </a>
                        </Button>
                    </article>
                ) : (
                    <article
                        key={share.id}
                        className="space-y-5 rounded-reos-lg border border-border bg-card p-4"
                    >
                        <h3 className="flex items-center gap-2 text-base font-semibold">
                            <CircleHelp aria-hidden className="size-5" />
                            {t('sessions.share.practice', {
                                topic: t(
                                    `onboarding.topics.${share.topic as 'agency'}`,
                                ),
                            })}
                        </h3>
                        {share.questions.map((question, i) => (
                            <Question
                                key={question.id}
                                question={question}
                                n={i + 1}
                            />
                        ))}
                    </article>
                ),
            )}
        </section>
    );
}
