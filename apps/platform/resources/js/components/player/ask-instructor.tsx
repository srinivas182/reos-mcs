import {
    Languages,
    MessageCircleQuestion,
    Play,
    Sparkles,
    Wand2,
} from 'lucide-react';
import type { RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SaveFlashcard } from '@/components/practice/save-flashcard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { clock, postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import { ask as askRoute } from '@/routes/learn';

export type AskSource = {
    lesson_id: number;
    lesson: string;
    seconds: number | null;
    page: number | null;
    heading: string | null;
    text: string;
};

export type AskAnswer = {
    answer: string;
    sources: AskSource[];
    quiz: {
        prompt: string;
        options: string[];
        correct: number;
        explanation: string;
    } | null;
    engine: 'ai' | 'simulator' | 'none';
    found: boolean;
};

type Mode = 'ask' | 'simpler' | 'spanish' | 'quiz';

type Props = {
    lessonId: number;
    /** Ask why (A9.2e): a question asked as soon as the lesson opens. */
    initial?: string | null;
    videoRef?: RefObject<HTMLVideoElement | null>;
    /** Sends the question; tests and stories pass their own. */
    send?: (body: {
        question: string;
        mode: Mode;
        previous: string | null;
        at: number | null;
    }) => Promise<AskAnswer>;
};

/**
 * Interrupt and ask (Sprint 8 wrap-up; candidate screen 9, agent screen
 * 94): asking pauses the video at the second; the answer comes from this
 * course only, with where it's from, then Explain simpler, En español or
 * Quiz me, and Resume at the same second. Seat time doesn't count while
 * paused, as always.
 */
export function AskInstructor({
    lessonId,
    videoRef,
    send,
    initial = null,
}: Props) {
    const { t } = useTranslation();
    const [question, setQuestion] = useState(initial ?? '');
    const [asked, setAsked] = useState('');
    const [answer, setAnswer] = useState<AskAnswer | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pausedAt, setPausedAt] = useState<number | null>(null);
    const [choice, setChoice] = useState<number | null>(null);
    const [checked, setChecked] = useState(false);
    const post =
        send ?? ((body) => postJson<AskAnswer>(askRoute(lessonId).url, body));

    const run = async (mode: Mode, text: string) => {
        const video = videoRef?.current ?? null;
        if (video && !video.paused) {
            video.pause();
        }
        const at = video ? Math.floor(video.currentTime) : pausedAt;
        setPausedAt(at);
        setBusy(true);
        setError(null);
        setChoice(null);
        setChecked(false);

        try {
            setAnswer(
                await post({
                    question: text,
                    mode,
                    previous: mode === 'ask' ? null : (answer?.answer ?? null),
                    at,
                }),
            );
            setAsked(text);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    // Ask why: asked once, on opening.
    const asking = useRef(false);
    useEffect(() => {
        if (!initial || asking.current) return;
        asking.current = true;
        void run('ask', initial);
    }, [initial]); // eslint-disable-line react-hooks/exhaustive-deps

    const resume = (seconds?: number | null) => {
        const video = videoRef?.current;
        if (video) {
            if (seconds !== undefined && seconds !== null) {
                video.currentTime = seconds;
            }
            void video.play();
        }
        setAnswer(null);
        setQuestion('');
    };

    return (
        <section
            aria-labelledby="ask-title"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
        >
            <h2 id="ask-title" className="sr-only">
                {t('learn.ask.title')}
            </h2>
            <form
                className="flex gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (question.trim().length >= 2) {
                        void run('ask', question.trim());
                    }
                }}
            >
                <label htmlFor={`ask-${lessonId}`} className="sr-only">
                    {t('learn.ask.label')}
                </label>
                <Input
                    id={`ask-${lessonId}`}
                    value={question}
                    maxLength={500}
                    placeholder={t('learn.ask.bar')}
                    onChange={(e) => setQuestion(e.target.value)}
                />
                <Button
                    type="submit"
                    disabled={busy || question.trim().length < 2}
                >
                    {busy ? <Spinner /> : <MessageCircleQuestion aria-hidden />}
                    {busy ? t('learn.ask.asking') : t('learn.ask.submit')}
                </Button>
            </form>
            {error ? (
                <p role="alert" className="text-body text-destructive">
                    {error}
                </p>
            ) : null}
            {answer ? (
                <div aria-live="polite" className="space-y-3">
                    {pausedAt !== null ? (
                        <p className="text-sm text-muted-foreground">
                            {t('learn.ask.paused', { time: clock(pausedAt) })}
                        </p>
                    ) : null}
                    <p className="text-body whitespace-pre-line">
                        {answer.answer}
                    </p>
                    {answer.found ? (
                        <SaveFlashcard
                            source="ask"
                            front={asked}
                            back={answer.answer}
                        />
                    ) : null}
                    {answer.engine === 'simulator' ? (
                        <p className="text-sm text-muted-foreground">
                            {t('learn.ask.demo')}
                        </p>
                    ) : null}
                    {answer.sources.length > 0 ? (
                        <ul className="space-y-1">
                            {answer.sources.map((source, i) => (
                                <li
                                    key={`${source.lesson_id}-${source.seconds ?? source.page ?? i}`}
                                    className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                                >
                                    {t('learn.ask.from', {
                                        lesson: source.lesson,
                                    })}
                                    {source.heading
                                        ? ` · ${source.heading}`
                                        : ''}
                                    {source.lesson_id === lessonId &&
                                    source.seconds !== null ? (
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                resume(source.seconds)
                                            }
                                        >
                                            <Play aria-hidden />
                                            {t('learn.ask.play', {
                                                time: clock(source.seconds),
                                            })}
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    {answer.quiz ? (
                        <fieldset className="space-y-2">
                            <legend className="font-medium">
                                {answer.quiz.prompt}
                            </legend>
                            {answer.quiz.options.map((option, i) => (
                                <label
                                    key={option}
                                    className={cn(
                                        'flex min-h-12 items-center gap-3 rounded-reos-md border border-border px-3',
                                        checked &&
                                            i === answer.quiz?.correct &&
                                            'border-reos-tone-success-fg',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name={`quiz-${lessonId}`}
                                        checked={choice === i}
                                        disabled={checked}
                                        onChange={() => setChoice(i)}
                                        className="size-5"
                                    />
                                    {option}
                                </label>
                            ))}
                            {checked ? (
                                <p className="text-body" aria-live="polite">
                                    <span className="font-semibold">
                                        {choice === answer.quiz.correct
                                            ? t('learn.ask.right')
                                            : t('learn.ask.wrong')}
                                    </span>{' '}
                                    {answer.quiz.explanation}
                                </p>
                            ) : (
                                <Button
                                    variant="outline"
                                    disabled={choice === null}
                                    onClick={() => setChecked(true)}
                                >
                                    {t('learn.ask.check')}
                                </Button>
                            )}
                        </fieldset>
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        {answer.found ? (
                            <>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => void run('simpler', asked)}
                                >
                                    <Wand2 aria-hidden />
                                    {t('learn.ask.simpler')}
                                </Button>
                                <Button
                                    variant="outline"
                                    lang="es"
                                    disabled={busy}
                                    onClick={() => void run('spanish', asked)}
                                >
                                    <Languages aria-hidden />
                                    {t('learn.ask.spanish')}
                                </Button>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => void run('quiz', asked)}
                                >
                                    <Sparkles aria-hidden />
                                    {t('learn.ask.quiz')}
                                </Button>
                            </>
                        ) : null}
                        <Button onClick={() => resume()}>
                            <Play aria-hidden />
                            {t('learn.ask.resume')}
                        </Button>
                    </div>
                </div>
            ) : null}
        </section>
    );
}
