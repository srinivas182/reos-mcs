import { CheckCircle2, Circle, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { ContractReview } from './types';

const STEPS = ['read', 'terms', 'checks', 'compare'] as const;

/** Reviewing (agent screen 64): the steps, with the one in progress. */
export function ReviewProgress({ step }: { step: ContractReview['step'] }) {
    const { t } = useTranslation();
    const at = step === 'done' ? STEPS.length : STEPS.indexOf(step);

    return (
        <section
            aria-live="polite"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <p className="text-base">{t('contracts.web.reviewing')}</p>
            <ol className="space-y-2">
                {STEPS.map((s, i) => {
                    const Icon =
                        i < at ? CheckCircle2 : i === at ? Loader2 : Circle;
                    return (
                        <li
                            key={s}
                            className="flex items-center gap-3 text-base"
                        >
                            <Icon
                                aria-hidden
                                className={
                                    i === at
                                        ? 'size-5 animate-spin text-primary'
                                        : i < at
                                          ? 'size-5 text-reos-tone-success-fg'
                                          : 'size-5 text-muted-foreground'
                                }
                            />
                            <span
                                className={
                                    i <= at
                                        ? 'font-medium'
                                        : 'text-muted-foreground'
                                }
                            >
                                {t(`contracts.web.steps.${s}`)}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
