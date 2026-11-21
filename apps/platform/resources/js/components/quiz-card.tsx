import { Check, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

export type QuizTopic =
    | 'agency'
    | 'contracts'
    | 'math'
    | 'ownership'
    | 'finance'
    | 'state_rules';

export type QuizQuestion = {
    id: number;
    topic: QuizTopic;
    question: string;
    options: string[];
    correct_option: number;
    explanation: string;
    source: string;
    sample: boolean;
};

const LETTERS = ['A', 'B', 'C', 'D'];

/**
 * One free-quiz question (candidate journey screen 6, on the web): the topic,
 * four options, then the explanation and its source the moment one is
 * picked. Once answered, the choice can't be changed.
 */
export function QuizCard({
    question,
    choice,
    onChoose,
}: {
    question: QuizQuestion;
    choice: number | null;
    onChoose: (choice: number) => void;
}) {
    const { t } = useTranslation();
    const answered = choice !== null;
    const right = choice === question.correct_option;

    return (
        <div className="space-y-5">
            <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                {t(`onboarding.topics.${question.topic}`)}
                {question.sample ? (
                    <span className="ml-2 rounded-reos-pill bg-muted px-2 py-0.5 text-xs font-medium tracking-normal text-muted-foreground normal-case">
                        {t('onboarding.quiz.sample')}
                    </span>
                ) : null}
            </p>
            <h2
                id={`question-${question.id}`}
                className="text-2xl leading-snug font-bold text-foreground"
            >
                {question.question}
            </h2>
            <div
                role="radiogroup"
                aria-labelledby={`question-${question.id}`}
                className="space-y-3"
            >
                {question.options.map((option, index) => {
                    const isChoice = choice === index;
                    const isRight = index === question.correct_option;
                    const state = !answered
                        ? 'idle'
                        : isRight && (isChoice || !right)
                          ? isChoice
                              ? 'right'
                              : 'reveal'
                          : isChoice
                            ? 'wrong'
                            : 'idle';

                    return (
                        <button
                            key={index}
                            type="button"
                            role="radio"
                            aria-checked={isChoice}
                            aria-disabled={answered}
                            onClick={() => (answered ? null : onChoose(index))}
                            className={cn(
                                'flex min-h-14 w-full items-center gap-3 rounded-reos-md border bg-card px-4 py-3 text-left text-base text-foreground transition-colors',
                                state === 'idle' &&
                                    !answered &&
                                    'border-border hover:border-primary',
                                state === 'idle' && answered && 'border-border',
                                state === 'right' &&
                                    'border-reos-tone-success-fg bg-reos-tone-success-bg',
                                state === 'reveal' &&
                                    'border-reos-tone-success-fg',
                                state === 'wrong' &&
                                    'border-reos-tone-danger-fg bg-reos-tone-danger-bg',
                            )}
                        >
                            <span
                                aria-hidden
                                className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold text-muted-foreground"
                            >
                                {LETTERS[index]}
                            </span>
                            <span className="flex-1">{option}</span>
                            {state === 'right' || state === 'reveal' ? (
                                <Check
                                    aria-hidden
                                    className="size-5 text-reos-tone-success-fg"
                                />
                            ) : null}
                            {state === 'wrong' ? (
                                <X
                                    aria-hidden
                                    className="size-5 text-reos-tone-danger-fg"
                                />
                            ) : null}
                        </button>
                    );
                })}
            </div>
            {answered ? (
                <div
                    role="status"
                    className={cn(
                        'space-y-1 rounded-reos-md border p-4',
                        right
                            ? 'border-reos-tone-success-fg bg-reos-tone-success-bg text-reos-tone-success-fg'
                            : 'border-reos-tone-danger-fg bg-reos-tone-danger-bg text-reos-tone-danger-fg',
                    )}
                >
                    <p className="font-semibold">
                        {right
                            ? t('onboarding.quiz.correct')
                            : t('onboarding.quiz.incorrect')}
                    </p>
                    <p>{question.explanation}</p>
                    <p className="text-sm">
                        {t('onboarding.quiz.source', {
                            source: question.source,
                        })}
                    </p>
                </div>
            ) : null}
        </div>
    );
}
