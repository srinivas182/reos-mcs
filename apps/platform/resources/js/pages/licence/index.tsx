import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Hourglass } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { ReadyToBook } from '@/components/licence/ready-to-book';
import { RetakePlan } from '@/components/licence/retake-plan';
import { StepsList } from '@/components/licence/steps-list';
import { dayText, type Journey } from '@/components/licence/types';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { putJson } from '@/lib/content';
import { exam, result, step } from '@/routes/licence';
import { store as storePlan } from '@/routes/practice/plans';

/**
 * Licence journey (candidate screen 16, with Ready to book, 15, and the
 * retake plan, 19; A10.3a): every step to an active licence from the
 * state's rules, the one-year window from the application, the exam date,
 * and what to do after the exam.
 */
export default function LicenceJourneyPage({ journey }: { journey: Journey }) {
    const { t, i18n } = useTranslation();
    const [error, setError] = useState<string | null>(null);
    const { window: win } = journey;
    const used =
        win.days_left === null || win.ends_on === null || win.filed_on === null
            ? 0
            : Math.min(
                  100,
                  Math.max(
                      0,
                      100 -
                          Math.round(
                              (win.days_left /
                                  ((new Date(win.ends_on).getTime() -
                                      new Date(win.filed_on).getTime()) /
                                      86_400_000)) *
                                  100,
                          ),
                  ),
              );

    const run = async (url: string, body: unknown) => {
        try {
            await putJson(url, body);
            setError(null);
            router.reload({ only: ['journey'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('licence.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('licence.title')}
                    description={t('licence.purpose', {
                        state: journey.state.name,
                    })}
                />
                {error ? <InputError message={error} /> : null}

                <section
                    aria-labelledby="licence-window"
                    className="space-y-2 rounded-reos-lg border border-border bg-card p-5"
                >
                    <h2
                        id="licence-window"
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <Hourglass aria-hidden className="size-5" />
                        {t('licence.window.title')}
                    </h2>
                    {win.filed_on && win.ends_on && win.days_left !== null ? (
                        <>
                            <p className="text-base">
                                {t('licence.window.left', {
                                    count: Math.max(0, win.days_left),
                                    date: dayText(win.ends_on, i18n.language),
                                })}
                            </p>
                            <div
                                role="progressbar"
                                aria-label={t('licence.window.used')}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={used}
                                className="h-2 overflow-hidden rounded-reos-pill bg-muted"
                            >
                                <div
                                    className="h-full bg-reos-action"
                                    style={{ width: `${used}%` }}
                                />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t('licence.window.filed', {
                                    date: dayText(win.filed_on, i18n.language),
                                    months: win.months,
                                })}
                            </p>
                        </>
                    ) : (
                        <p className="text-base text-muted-foreground">
                            {t('licence.window.notFiled', {
                                months: win.months,
                            })}
                        </p>
                    )}
                </section>

                {journey.results.can_enter ? (
                    <Alert>
                        <ClipboardCheck aria-hidden />
                        <AlertTitle>{t('licence.result.prompt')}</AlertTitle>
                        <AlertDescription className="text-base">
                            <Button asChild className="mt-2">
                                <Link href={result().url}>
                                    {t('licence.result.title')}
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : null}

                {journey.retake ? (
                    <RetakePlan
                        retake={journey.retake}
                        onStart={(topic) =>
                            router.post(storePlan().url, { topic })
                        }
                    />
                ) : null}

                {journey.results.passed_both ? (
                    <Alert>
                        <AlertTitle>{t('licence.passed.title')}</AlertTitle>
                        <AlertDescription className="text-base">
                            {t('licence.passed.body')}
                        </AlertDescription>
                    </Alert>
                ) : (
                    <ReadyToBook
                        journey={journey}
                        onBook={(examOn, time, centre) =>
                            void run(exam().url, {
                                exam_on: examOn,
                                time,
                                centre,
                            })
                        }
                    />
                )}

                <section aria-labelledby="licence-steps" className="space-y-3">
                    <h2 id="licence-steps" className="text-lg font-semibold">
                        {t('licence.steps', { state: journey.state.name })}
                    </h2>
                    <StepsList
                        steps={journey.steps}
                        onCheck={(code, done, on) =>
                            void run(step(code).url, { done, on: on ?? null })
                        }
                    />
                </section>
            </div>
        </>
    );
}
