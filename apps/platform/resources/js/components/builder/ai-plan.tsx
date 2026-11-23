import { Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Plan } from './types';

/** "What AI will make" for a module (screen 43): A7.3 makes it, for review before anything publishes. */
export function AiPlan({ plan }: { plan: Plan }) {
    const { t, i18n } = useTranslation();

    return (
        <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
            <div className="flex items-center gap-2">
                <Sparkles aria-hidden className="size-5 text-primary" />
                <h2 className="text-lg font-semibold">
                    {t('builder.plan.title')}
                </h2>
            </div>
            <p className="text-base text-muted-foreground">
                {t('builder.plan.intro')}
            </p>
            {plan.minutes === 0 ? (
                <p className="text-base">{t('builder.plan.nothing')}</p>
            ) : (
                <ul className="grid gap-2 text-base sm:grid-cols-2">
                    <li>
                        {t('builder.plan.minutes', { count: plan.minutes })}
                    </li>
                    <li>
                        {t('builder.plan.lessons', { count: plan.lessons })}
                    </li>
                    <li>
                        {t('builder.plan.checkins', { count: plan.checkins })}
                    </li>
                    <li>
                        {t('builder.plan.questions', { count: plan.questions })}
                    </li>
                    <li>
                        {t('builder.plan.flashcards', {
                            count: plan.flashcards,
                        })}
                    </li>
                    <li className="font-semibold">
                        {t('builder.plan.credits', {
                            credits: plan.credits.toLocaleString(i18n.language),
                        })}
                    </li>
                </ul>
            )}
            {plan.sources_waiting > 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('builder.plan.waiting', { count: plan.sources_waiting })}
                </p>
            ) : null}
        </section>
    );
}
