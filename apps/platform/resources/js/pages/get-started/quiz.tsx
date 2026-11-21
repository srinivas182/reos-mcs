import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { QuizQuestion } from '@/components/quiz-card';
import { QuizCard } from '@/components/quiz-card';
import { Button } from '@/components/ui/button';
import { answer } from '@/routes/get-started/quiz';

type Props = { state: string; questions: QuizQuestion[] };

/**
 * The free quiz (candidate journey screen 6, on the web): one question at a
 * time, no account needed. The answers go to the server once, at the end.
 */
export default function FreeQuiz({ questions }: Props) {
    const { t } = useTranslation();
    const [index, setIndex] = useState(0);
    const [choices, setChoices] = useState<Array<number | null>>(() =>
        questions.map(() => null),
    );
    const [sending, setSending] = useState(false);
    const question = questions[index];
    const choice = choices[index] ?? null;
    const last = index === questions.length - 1;

    const next = () => {
        if (!last) {
            setIndex(index + 1);

            return;
        }

        setSending(true);
        router.post(
            answer.url(),
            {
                answers: questions.map((q, i) => ({
                    question_id: q.id,
                    choice: choices[i] ?? 0,
                })),
            },
            { onFinish: () => setSending(false) },
        );
    };

    if (!question) {
        return null;
    }

    return (
        <>
            <Head title={t('site.getStarted.quizTitle')} />
            <div className="mx-auto max-w-2xl space-y-8 px-4 py-10 md:py-16">
                <div className="space-y-3">
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{t('onboarding.quiz.header')}</span>
                        <span className="font-semibold text-foreground">
                            {t('onboarding.quiz.count', {
                                number: index + 1,
                                total: questions.length,
                            })}
                        </span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label={t('onboarding.quiz.progress')}
                        aria-valuemin={0}
                        aria-valuemax={questions.length}
                        aria-valuenow={index + 1}
                        className="h-2 overflow-hidden rounded-reos-pill bg-muted"
                    >
                        <div
                            className="h-full rounded-reos-pill bg-primary transition-[width]"
                            style={{
                                width: `${((index + 1) / questions.length) * 100}%`,
                            }}
                        />
                    </div>
                </div>

                <QuizCard
                    key={question.id}
                    question={question}
                    choice={choice}
                    onChoose={(value) =>
                        setChoices((current) =>
                            current.map((c, i) => (i === index ? value : c)),
                        )
                    }
                />

                <Button
                    className="w-full"
                    disabled={choice === null || sending}
                    onClick={next}
                >
                    {last
                        ? t('onboarding.quiz.finish')
                        : t('onboarding.quiz.next')}
                </Button>
            </div>
        </>
    );
}
