import { Head, router } from '@inertiajs/react';
import { BadgeCheck, CalendarCheck, GraduationCap, Plug } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FlagsPicker } from '@/components/agents/flags-picker';
import { GoalPicker } from '@/components/agents/goal-picker';
import type { Flag, Goal, ModeState } from '@/components/agents/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { putJson } from '@/lib/content';
import { goal as goalRoute, flags as flagsRoute } from '@/routes/agent/mode';
import { dashboard } from '@/routes';

/**
 * The agent's welcome, goal and flags on the web (A11.1a; agent screens 4,
 * 5 and 6): the licence confirmed with three promises, one question that
 * sets the home screen, and optional work-style flags.
 */
export default function AgentSetup({ mode }: { mode: ModeState }) {
    const { t } = useTranslation();
    const [step, setStep] = useState<'welcome' | 'goal' | 'flags'>(
        mode.welcome.verified && mode.needs_setup ? 'welcome' : 'goal',
    );
    const [goal, setGoal] = useState<Goal>(mode.goal ?? mode.suggested_default);
    const [flags, setFlags] = useState<Flag[]>(mode.flags);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const run = async (work: () => Promise<unknown>, next: () => void) => {
        setBusy(true);
        try {
            await work();
            setError(null);
            next();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={t('agents.modes.setupPrompt.title')} />
            <div className="max-w-2xl space-y-6 px-4 py-6 md:px-8">
                {step === 'welcome' ? (
                    <>
                        <PageHeader
                            title={t('agents.modes.welcome.title', {
                                name: mode.welcome.name,
                            })}
                            description={t('agents.modes.setupPrompt.body')}
                        />
                        <div className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                            <p className="flex items-center gap-2 text-base font-semibold">
                                <BadgeCheck aria-hidden className="size-5" />
                                {t('agents.modes.welcome.verified')}
                            </p>
                            {mode.welcome.licence && mode.welcome.state ? (
                                <p className="text-base">
                                    {t('agents.modes.welcome.licence', {
                                        state: mode.welcome.state,
                                        number: mode.welcome.licence,
                                    })}
                                </p>
                            ) : null}
                            {mode.welcome.brokerage ? (
                                <p className="text-base text-muted-foreground">
                                    {t('agents.modes.welcome.brokerage', {
                                        brokerage: mode.welcome.brokerage,
                                    })}
                                </p>
                            ) : null}
                        </div>
                        <ol className="space-y-3">
                            {(
                                [
                                    ['tools', Plug],
                                    ['plan', CalendarCheck],
                                    ['ce', GraduationCap],
                                ] as const
                            ).map(([key, Icon], i) => (
                                <li
                                    key={key}
                                    className="flex items-center gap-3 text-base"
                                >
                                    <span className="flex size-8 items-center justify-center rounded-full bg-muted font-semibold">
                                        {i + 1}
                                    </span>
                                    <Icon aria-hidden className="size-5" />
                                    {t(`agents.modes.welcome.steps.${key}`)}
                                </li>
                            ))}
                        </ol>
                        <Button onClick={() => setStep('goal')}>
                            {t('agents.modes.welcome.start')}
                        </Button>
                    </>
                ) : null}
                {step === 'goal' ? (
                    <>
                        <PageHeader
                            title={t('agents.modes.setupPrompt.title')}
                            description={t('agents.modes.description')}
                        />
                        <GoalPicker
                            mode={mode}
                            value={goal}
                            onChange={setGoal}
                        />
                        <Button
                            disabled={busy}
                            onClick={() =>
                                void run(
                                    () =>
                                        putJson(goalRoute().url, {
                                            goal,
                                            reason: mode.needs_setup
                                                ? 'setup'
                                                : 'manual',
                                        }),
                                    () => setStep('flags'),
                                )
                            }
                        >
                            {t('agents.modes.continue')}
                        </Button>
                    </>
                ) : null}
                {step === 'flags' ? (
                    <>
                        <PageHeader
                            title={t('agents.modes.flagsTitle')}
                            description={t('agents.modes.flagsBody')}
                        />
                        <FlagsPicker value={flags} onChange={setFlags} />
                        <div className="flex flex-wrap gap-3">
                            <Button
                                disabled={busy}
                                onClick={() =>
                                    void run(
                                        () =>
                                            putJson(flagsRoute().url, {
                                                flags,
                                            }),
                                        () => router.visit(dashboard().url),
                                    )
                                }
                            >
                                {t('agents.modes.saveFlags')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => router.visit(dashboard().url)}
                            >
                                {t('agents.modes.skip')}
                            </Button>
                        </div>
                    </>
                ) : null}
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
            </div>
        </>
    );
}
