import { Check, Timer, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import { finish, store } from '@/routes/practice/speed';
import type { SpeedResult, SpeedRoundData } from './types';

/**
 * The speed round (screen 50, A9.2d): 60 seconds of true or false, "is
 * this the answer?", with the best and current score and two big buttons.
 * The server scores the taps and saves the misses as flashcards.
 */
export function SpeedRound({ best }: { best: number | null }) {
    const { t } = useTranslation();
    const [round, setRound] = useState<SpeedRoundData | null>(null);
    const [at, setAt] = useState(0);
    const [left, setLeft] = useState(0);
    const [score, setScore] = useState(0);
    const [last, setLast] = useState<boolean | null>(null);
    const [result, setResult] = useState<SpeedResult | null>(null);
    const [error, setError] = useState(false);
    const taps = useRef<{ id: number; true: boolean }[]>([]);
    const sent = useRef(false);

    const begin = async () => {
        try {
            const next = await postJson<{ data: SpeedRoundData }>(store().url);
            taps.current = [];
            sent.current = false;
            setRound(next.data);
            setAt(0);
            setScore(0);
            setLast(null);
            setResult(null);
            setLeft(next.data.seconds);
            setError(false);
        } catch {
            setError(true);
        }
    };

    const end = async (current: SpeedRoundData) => {
        if (sent.current) return;
        sent.current = true;
        try {
            const done = await postJson<{ data: SpeedResult }>(
                finish(current.id).url,
                { taps: taps.current },
            );
            setResult(done.data);
        } catch {
            setError(true);
        }
    };

    useEffect(() => {
        if (!round || result || left <= 0) return;
        const timer = window.setTimeout(() => setLeft((s) => s - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [round, result, left]);

    useEffect(() => {
        if (round && left === 0 && !result) void end(round);
    }, [left]); // eslint-disable-line react-hooks/exhaustive-deps

    const tap = (answer: boolean) => {
        const statement = round?.statements[at];
        if (!round || !statement || left <= 0) return;
        taps.current.push({ id: statement.id, true: answer });
        const right = answer === statement.truth;
        setLast(right);
        if (right) setScore((s) => s + 1);
        if (at + 1 >= round.statements.length) void end(round);
        else setAt(at + 1);
    };

    const statement = round?.statements[at];

    return (
        <div className="space-y-4">
            <p className="text-body text-muted-foreground">
                {best !== null || result
                    ? t('practice.speed.best', {
                          score: Math.max(best ?? 0, result?.best ?? 0),
                      })
                    : t('practice.speed.noBest')}
            </p>

            {!round || result ? (
                <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    {result ? (
                        <div role="status" className="space-y-1">
                            <h2 className="text-lg font-semibold">
                                {t('practice.speed.doneTitle')}
                            </h2>
                            <p className="text-body">
                                {t('practice.speed.doneBody', {
                                    score: result.score,
                                    answered: result.answered,
                                })}
                            </p>
                            {result.record && result.score > 0 ? (
                                <p className="font-semibold">
                                    {t('practice.speed.record')}
                                </p>
                            ) : null}
                            {result.misses > 0 ? (
                                <p className="text-body text-muted-foreground">
                                    {t('practice.speed.misses', {
                                        count: result.misses,
                                    })}
                                </p>
                            ) : null}
                        </div>
                    ) : null}
                    <Button onClick={() => void begin()}>
                        {result
                            ? t('practice.speed.again')
                            : t('practice.speed.start')}
                    </Button>
                </section>
            ) : statement ? (
                <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                    <div className="flex items-center justify-between text-body">
                        <span
                            role="timer"
                            aria-live="off"
                            aria-label={t('practice.speed.timeLeft', {
                                count: left,
                            })}
                            className="inline-flex items-center gap-2 font-semibold tabular-nums"
                        >
                            <Timer aria-hidden className="size-5" />
                            {left}
                        </span>
                        <span className="font-semibold">
                            {t('practice.speed.score', { score })}
                        </span>
                    </div>
                    <p className="text-lg font-semibold">{statement.prompt}</p>
                    <p className="rounded-reos-md bg-muted p-3 text-lg">
                        {t('practice.speed.proposed', {
                            answer: statement.answer,
                        })}
                    </p>
                    <div className="grid grid-cols-2 gap-3">
                        <Button
                            className="h-16 text-lg"
                            onClick={() => tap(true)}
                        >
                            <Check aria-hidden />
                            {t('practice.speed.true')}
                        </Button>
                        <Button
                            className="h-16 text-lg"
                            variant="outline"
                            onClick={() => tap(false)}
                        >
                            <X aria-hidden />
                            {t('practice.speed.false')}
                        </Button>
                    </div>
                    {last !== null ? (
                        <p
                            role="status"
                            className={cn(
                                'text-body font-semibold',
                                last
                                    ? 'text-reos-tone-success-fg'
                                    : 'text-reos-tone-danger-fg',
                            )}
                        >
                            {last
                                ? t('practice.speed.right')
                                : t('practice.speed.wrong')}
                        </p>
                    ) : null}
                </section>
            ) : null}
            {error ? (
                <p role="alert" className="text-body text-destructive">
                    {t('practice.errors.save')}
                </p>
            ) : null}
        </div>
    );
}
