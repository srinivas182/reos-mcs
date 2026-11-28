import { CheckCircle2, Circle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { BrokerageSetupData } from './types';

/**
 * The brokerage's setup checklist (A15.1a, broker journey screens 1–6): the
 * TREC check, agents, seats, the policy manual, the advertising policy and
 * mentors, each with its state and where to do it.
 */
export function SetupChecklist({ setup }: { setup: BrokerageSetupData }) {
    const { t } = useTranslation();
    const complete = setup.done === setup.total;

    return (
        <section
            aria-labelledby="brokerage-setup"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <header className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="brokerage-setup" className="text-lg font-semibold">
                    {t('brokerage.setup.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {complete
                        ? t('brokerage.setup.complete')
                        : t('brokerage.setup.progress', {
                              done: setup.done,
                              total: setup.total,
                          })}
                    {setup.trec_number
                        ? ` · ${t('brokerage.setup.trec', { number: setup.trec_number })}`
                        : ''}
                </p>
            </header>
            <ol className="divide-y divide-border rounded-reos-md border border-border">
                {setup.steps.map((step) => (
                    <li
                        key={step.key}
                        className="flex flex-wrap items-center justify-between gap-3 p-3"
                    >
                        <span className="flex items-center gap-3 text-base">
                            {step.done ? (
                                <CheckCircle2
                                    aria-hidden
                                    className="size-5 text-reos-tone-success-fg"
                                />
                            ) : (
                                <Circle
                                    aria-hidden
                                    className="size-5 text-muted-foreground"
                                />
                            )}
                            {t(`brokerage.setup.steps.${step.key}`)}
                            <span className="sr-only">
                                {step.done ? t('brokerage.setup.done') : ''}
                            </span>
                        </span>
                        {step.done ? null : (
                            <Button variant="outline" asChild>
                                <a
                                    href={step.href}
                                    aria-label={`${t('brokerage.setup.do')}: ${t(`brokerage.setup.steps.${step.key}`)}`}
                                >
                                    {t('brokerage.setup.do')}
                                </a>
                            </Button>
                        )}
                    </li>
                ))}
            </ol>
        </section>
    );
}
