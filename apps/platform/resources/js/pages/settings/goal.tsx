import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { FlagsPicker } from '@/components/agents/flags-picker';
import { GoalPicker } from '@/components/agents/goal-picker';
import type { Flag, Goal, ModeState } from '@/components/agents/types';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { putJson } from '@/lib/content';
import { flags as flagsRoute, goal as goalRoute } from '@/routes/agent/mode';

/**
 * My goal (agent screen 146, A11.1a), in Settings: change the goal by hand,
 * see its history and locks, and set work-style flags. Every feature stays
 * reachable in every mode.
 */
export default function GoalSettings({ mode }: { mode: ModeState }) {
    const { t, i18n } = useTranslation();
    const [flags, setFlags] = useState<Flag[]>(mode.flags);
    const [error, setError] = useState<string | null>(null);
    const date = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
            new Date(iso),
        );
    const save = async (url: string, body: unknown) => {
        try {
            await putJson(url, body);
            setError(null);
            toast.success(t('agents.modes.saved'));
            router.reload({ only: ['mode'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const name = (goal: Goal) => t(`agents.modes.goals.${goal}.name`);

    return (
        <>
            <Head title={t('agents.modes.title')} />
            <div className="space-y-4">
                <Heading
                    variant="small"
                    title={t('agents.modes.title')}
                    description={t('agents.modes.description')}
                />
                <GoalPicker
                    mode={mode}
                    value={mode.goal}
                    onChange={(goal) =>
                        void save(goalRoute().url, { goal, reason: 'manual' })
                    }
                />
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
            </div>
            <div className="space-y-4">
                <Heading
                    variant="small"
                    title={t('agents.modes.flagsTitle')}
                    description={t('agents.modes.flagsBody')}
                />
                <FlagsPicker value={flags} onChange={setFlags} />
                <Button onClick={() => void save(flagsRoute().url, { flags })}>
                    {t('agents.modes.save')}
                </Button>
            </div>
            {mode.history.length > 0 ? (
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('agents.modes.history')}
                    />
                    <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
                        {mode.history.map((h) => (
                            <li
                                key={h.at}
                                className="flex flex-wrap justify-between gap-2 p-4 text-base"
                            >
                                <span className="font-semibold">
                                    {h.from
                                        ? t('agents.modes.historyEntry', {
                                              from: name(h.from),
                                              to: name(h.to),
                                          })
                                        : t('agents.modes.historyFirst', {
                                              to: name(h.to),
                                          })}
                                </span>
                                <span className="text-muted-foreground">
                                    {t(`agents.modes.reasons.${h.reason}`)} ·{' '}
                                    {date(h.at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </>
    );
}
