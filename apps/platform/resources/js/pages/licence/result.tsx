import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import type { Journey, Portion } from '@/components/licence/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { postJson } from '@/lib/content';
import { results, show } from '@/routes/licence';

const TOPICS = [
    'agency',
    'contracts',
    'ownership',
    'finance',
    'math',
    'state_rules',
] as const;

type Answer = 'passed' | 'failed' | 'skipped';

/**
 * Enter your result (candidate screen 15 › result entry, A10.3a): passed or
 * didn't for each portion not yet passed, and, if one was missed, the weak
 * areas from the score report, used only for the retake plan.
 */
export default function ResultPage({ journey }: { journey: Journey }) {
    const { t } = useTranslation();
    const open = (['national', 'state'] as const).filter(
        (p) => journey.results[p].passed !== true,
    );
    const [answers, setAnswers] = useState<Record<Portion, Answer>>({
        national: open.includes('national') ? 'passed' : 'skipped',
        state: open.includes('state') ? 'passed' : 'skipped',
    });
    const [weak, setWeak] = useState<string[]>([]);
    const [error, setError] = useState<string | null>(null);
    const missed = Object.values(answers).includes('failed');
    const value = (a: Answer) => (a === 'skipped' ? null : a === 'passed');

    const submit = async () => {
        try {
            await postJson(results().url, {
                national: value(answers.national),
                state: value(answers.state),
                weak_topics: missed ? weak : [],
            });
            router.visit(show().url);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('licence.result.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    back={
                        <Link
                            href={show().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('licence.title')}
                        </Link>
                    }
                    title={t('licence.result.title')}
                    description={t('licence.result.purpose')}
                />
                {!journey.results.can_enter ? (
                    <p className="text-base text-muted-foreground">
                        {t('licence.result.notYet')}
                    </p>
                ) : (
                    <form
                        className="space-y-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            void submit();
                        }}
                    >
                        {open.map((portion) => (
                            <fieldset
                                key={portion}
                                className="space-y-2 rounded-reos-lg border border-border bg-card p-5"
                            >
                                <legend className="px-1 text-lg font-semibold">
                                    {t(`licence.portions.${portion}`)}
                                </legend>
                                {(['passed', 'failed', 'skipped'] as const).map(
                                    (a) => (
                                        <label
                                            key={a}
                                            className="flex min-h-12 items-center gap-3 text-base"
                                        >
                                            <input
                                                type="radio"
                                                name={`portion-${portion}`}
                                                className="size-5 accent-reos-action"
                                                checked={answers[portion] === a}
                                                onChange={() =>
                                                    setAnswers({
                                                        ...answers,
                                                        [portion]: a,
                                                    })
                                                }
                                            />
                                            {t(`licence.result.answers.${a}`)}
                                        </label>
                                    ),
                                )}
                            </fieldset>
                        ))}
                        {journey.results.national.passed === true ? (
                            <p className="text-base text-muted-foreground">
                                {t('licence.result.kept', {
                                    portion: t('licence.portions.national'),
                                })}
                            </p>
                        ) : null}
                        {journey.results.state.passed === true ? (
                            <p className="text-base text-muted-foreground">
                                {t('licence.result.kept', {
                                    portion: t('licence.portions.state'),
                                })}
                            </p>
                        ) : null}

                        {missed ? (
                            <fieldset className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                                <legend className="px-1 text-lg font-semibold">
                                    {t('licence.result.weakTitle')}
                                </legend>
                                <p className="text-base text-muted-foreground">
                                    {t('licence.result.weakHint')}
                                </p>
                                {TOPICS.map((topic) => (
                                    <label
                                        key={topic}
                                        htmlFor={`weak-${topic}`}
                                        className="flex min-h-12 items-center gap-3 text-base"
                                    >
                                        <Checkbox
                                            id={`weak-${topic}`}
                                            checked={weak.includes(topic)}
                                            onCheckedChange={(on) =>
                                                setWeak(
                                                    on === true
                                                        ? [...weak, topic]
                                                        : weak.filter(
                                                              (w) =>
                                                                  w !== topic,
                                                          ),
                                                )
                                            }
                                        />
                                        {t(`practice.topics.${topic}`)}
                                    </label>
                                ))}
                            </fieldset>
                        ) : null}

                        <InputError message={error ?? undefined} />
                        <Button
                            type="submit"
                            size="lg"
                            disabled={open.every(
                                (p) => answers[p] === 'skipped',
                            )}
                        >
                            {t('licence.result.save')}
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}
