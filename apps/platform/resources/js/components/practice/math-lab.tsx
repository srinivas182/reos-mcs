import { CircleCheck, Lightbulb, Sigma } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/content';
import { check, problems } from '@/routes/practice/math';
import { Calculator } from './calculator';
import type { MathCheck, MathKind, MathProblem } from './types';

type Done = { answer: string; right: boolean };

/**
 * Math lab (screen 48, A9.2c): choose a kind, then work the problem one
 * step at a time. The server checks each value; a wrong one brings the
 * step's hint and Show the answer. Each solve offers a variant of the same
 * kind with new numbers. The calculator is always to hand.
 */
export function MathLab({
    kinds,
    initial = null,
}: {
    kinds: { kind: MathKind; solved: number }[];
    initial?: MathProblem | null;
}) {
    const { t } = useTranslation();
    const [problem, setProblem] = useState<MathProblem | null>(initial);
    const [done, setDone] = useState<Done[]>([]);
    const [value, setValue] = useState('');
    const [hint, setHint] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [solved, setSolved] = useState(false);
    const [counts, setCounts] = useState(kinds);

    const open = async (kind: MathKind) => {
        try {
            const next = await postJson<{ data: MathProblem }>(problems().url, {
                kind,
            });
            setProblem(next.data);
            setDone([]);
            setValue('');
            setHint(null);
            setError(null);
            setSolved(false);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    const send = async (reveal: boolean) => {
        if (!problem) return;
        const step = done.length + 1;
        try {
            const result = await postJson<{ data: MathCheck }>(check().url, {
                kind: problem.kind,
                seed: problem.seed,
                step,
                ...(reveal ? { reveal: true } : { value }),
            });
            setError(null);
            if (result.data.answer !== null) {
                setDone((all) => [
                    ...all,
                    {
                        answer: result.data.answer ?? '',
                        right: result.data.correct,
                    },
                ]);
                setValue('');
                setHint(null);
            } else {
                setHint(result.data.hint);
            }
            if (result.data.solved) {
                setSolved(true);
                setCounts((all) =>
                    all.map((k) =>
                        k.kind === problem.kind
                            ? { ...k, solved: k.solved + 1 }
                            : k,
                    ),
                );
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    if (!problem) {
        return (
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {counts.map((k) => (
                    <li
                        key={k.kind}
                        className="flex flex-col justify-between gap-3 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <div className="space-y-1">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <Sigma
                                    aria-hidden
                                    className="size-5 text-primary"
                                />
                                {t(`practice.math.kinds.${k.kind}`)}
                            </h2>
                            <p className="text-body text-muted-foreground">
                                {t(`practice.math.kindHints.${k.kind}`)}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('practice.math.solved', { count: k.solved })}
                            </p>
                        </div>
                        <Button
                            aria-label={`${t('practice.math.start')}: ${t(`practice.math.kinds.${k.kind}`)}`}
                            onClick={() => void open(k.kind)}
                        >
                            {t('practice.math.start')}
                        </Button>
                    </li>
                ))}
                {error ? (
                    <li role="alert" className="text-body text-destructive">
                        {error}
                    </li>
                ) : null}
            </ul>
        );
    }

    const current = problem.steps[done.length];

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section
                aria-labelledby="math-problem"
                className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            >
                <p className="text-sm text-muted-foreground">
                    {t(`practice.math.kinds.${problem.kind}`)}
                </p>
                <h2 id="math-problem" className="text-xl font-semibold">
                    {problem.prompt}
                </h2>
                <ol className="space-y-3">
                    {problem.steps.map((step, i) =>
                        i < done.length ? (
                            <li
                                key={step.n}
                                className="flex items-start gap-3 rounded-reos-md bg-muted p-3"
                            >
                                <CircleCheck
                                    aria-hidden
                                    className="mt-0.5 size-5 text-reos-tone-success-fg"
                                />
                                <span className="text-body">
                                    <span className="block font-medium">
                                        {step.label}
                                    </span>
                                    {done[i]?.right
                                        ? t('practice.math.right', {
                                              answer: done[i]?.answer,
                                          })
                                        : t('practice.math.shown', {
                                              answer: done[i]?.answer,
                                          })}
                                </span>
                            </li>
                        ) : null,
                    )}
                </ol>
                {current && !solved ? (
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            void send(false);
                        }}
                    >
                        <p className="text-sm text-muted-foreground">
                            {t('practice.math.step', {
                                n: current.n,
                                of: problem.steps.length,
                            })}
                        </p>
                        <Label htmlFor="math-value" className="text-base">
                            {current.label}
                        </Label>
                        <div className="flex flex-wrap gap-2">
                            <Input
                                id="math-value"
                                inputMode="decimal"
                                autoComplete="off"
                                value={value}
                                onChange={(e) => setValue(e.target.value)}
                                className="max-w-xs"
                                aria-describedby={
                                    hint ? 'math-hint' : undefined
                                }
                            />
                            <Button
                                type="submit"
                                disabled={value.trim() === ''}
                            >
                                {t('practice.math.check')}
                            </Button>
                        </div>
                        {hint ? (
                            <div
                                id="math-hint"
                                role="status"
                                className="space-y-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-body"
                            >
                                <p className="flex items-center gap-2 font-semibold">
                                    <Lightbulb aria-hidden className="size-5" />
                                    {t('practice.math.wrong')}
                                </p>
                                <p>{hint}</p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => void send(true)}
                                >
                                    {t('practice.math.show')}
                                </Button>
                            </div>
                        ) : null}
                    </form>
                ) : null}
                {solved ? (
                    <div
                        role="status"
                        className="space-y-3 rounded-reos-md bg-reos-tone-success-bg p-4"
                    >
                        <p className="text-lg font-semibold">
                            {t('practice.math.solvedTitle')}
                        </p>
                        <p className="text-body">
                            {t('practice.math.solvedBody')}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={() => void open(problem.kind)}>
                                {t('practice.math.variant')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setProblem(null)}
                            >
                                {t('practice.math.choose')}
                            </Button>
                        </div>
                    </div>
                ) : null}
                {error ? (
                    <p role="alert" className="text-body text-destructive">
                        {error}
                    </p>
                ) : null}
            </section>
            <aside className="space-y-3">
                <Calculator />
                <Button variant="outline" onClick={() => setProblem(null)}>
                    {t('practice.math.choose')}
                </Button>
            </aside>
        </div>
    );
}
