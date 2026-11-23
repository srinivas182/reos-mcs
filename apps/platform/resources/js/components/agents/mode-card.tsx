import { Link } from '@inertiajs/react';
import { Compass, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { Goal, ModeState } from './types';

/**
 * The agent's mode on the dashboard (A11.1a): the badge that opens My goal,
 * "Pick your goal" until set, or a suggested switch (screens 126, 132) the
 * agent can take or put off for 30 days.
 */
export function ModeCard({
    mode,
    goalHref,
    setupHref,
    onSwitch,
    onSnooze,
}: {
    mode: ModeState;
    goalHref: string;
    setupHref: string;
    onSwitch: (goal: Goal) => void;
    onSnooze: () => void;
}) {
    const { t } = useTranslation();

    if (mode.needs_setup || mode.goal === null) {
        return (
            <section className="flex flex-wrap items-center justify-between gap-4 rounded-reos-lg border border-border bg-card p-5">
                <div className="space-y-1">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <Compass aria-hidden className="size-5" />
                        {t('agents.modes.setupPrompt.title')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('agents.modes.setupPrompt.body')}
                    </p>
                </div>
                <Button asChild>
                    <Link href={setupHref}>
                        {t('agents.modes.setupPrompt.action')}
                    </Link>
                </Button>
            </section>
        );
    }

    const suggestion = mode.suggestion;
    const name = (goal: Goal) => t(`agents.modes.goals.${goal}.name`);

    return (
        <section className="space-y-3">
            <Link
                href={goalHref}
                className="inline-flex min-h-12 items-center gap-2 rounded-reos-pill border border-border bg-card px-4 text-base font-semibold hover:bg-muted"
            >
                <Compass aria-hidden className="size-5" />
                {t('agents.modes.badge', { goal: name(mode.goal) })}
            </Link>
            {suggestion ? (
                <div className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <Sparkles aria-hidden className="size-5" />
                        {t(
                            `agents.modes.suggestion.${suggestion.goal === 'lead' ? 'lead' : 'grow'}.title`,
                        )}
                    </h2>
                    <p className="text-base">
                        {t(
                            `agents.modes.suggestion.${suggestion.goal === 'lead' ? 'lead' : 'grow'}.body`,
                            {
                                count: suggestion.closings,
                            },
                        )}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {t('agents.modes.suggestion.note')}
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Button onClick={() => onSwitch(suggestion.goal)}>
                            {t('agents.modes.suggestion.switch', {
                                goal: name(suggestion.goal),
                            })}
                        </Button>
                        <Button variant="outline" onClick={onSnooze}>
                            {t('agents.modes.suggestion.notYet')}
                        </Button>
                    </div>
                </div>
            ) : null}
        </section>
    );
}
