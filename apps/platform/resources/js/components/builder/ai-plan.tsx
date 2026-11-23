import { router } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { generate } from '@/routes/builder/modules';
import type { Plan } from './types';

/**
 * "What AI will make" for a module (screen 43), and Generate module (A7.3):
 * the lessons are drafted from the sources and wait for review; nothing
 * publishes without it. The credits come from the school.
 */
export function AiPlan({
    moduleId,
    plan,
    generating,
    vendorLive,
}: {
    moduleId: number;
    plan: Plan;
    generating: boolean;
    vendorLive: boolean;
}) {
    const { t, i18n } = useTranslation();
    const [processing, setProcessing] = useState(false);
    const short = plan.credits > plan.credits_left;
    const number = (n: number) => n.toLocaleString(i18n.language);

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
                            credits: number(plan.credits),
                        })}
                    </li>
                </ul>
            )}
            {plan.sources_waiting > 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('builder.plan.waiting', { count: plan.sources_waiting })}
                </p>
            ) : null}
            <p className="text-sm text-muted-foreground">
                {t('builder.plan.left', { credits: number(plan.credits_left) })}
            </p>
            {!vendorLive ? (
                <p className="text-sm text-muted-foreground">
                    {t('builder.plan.simulated')}
                </p>
            ) : null}
            {short && plan.minutes > 0 ? (
                <p className="text-sm font-medium text-destructive">
                    {t('builder.plan.short')}
                </p>
            ) : null}
            <Button
                disabled={
                    plan.minutes === 0 || short || generating || processing
                }
                onClick={() =>
                    router.post(
                        generate(moduleId).url,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                        },
                    )
                }
            >
                <Sparkles aria-hidden />
                {generating
                    ? t('builder.plan.generating')
                    : t('builder.plan.generate')}
            </Button>
        </section>
    );
}
