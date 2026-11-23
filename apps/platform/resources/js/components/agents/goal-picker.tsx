import { Check, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GOALS, type Goal, type ModeState } from './types';

/**
 * The four goals (agent screens 5 and 146): one question sets what Today
 * shows first. Own stays locked until the TREC record says it can open.
 */
export function GoalPicker({
    mode,
    value,
    onChange,
}: {
    mode: ModeState;
    value: Goal | null;
    onChange: (goal: Goal) => void;
}) {
    const { t, i18n } = useTranslation();
    const date = (d: string) =>
        new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
            new Date(`${d}T12:00:00`),
        );

    return (
        <div
            className="grid gap-3"
            role="radiogroup"
            aria-label={t('agents.modes.title')}
        >
            {GOALS.map((goal) => {
                const locked = goal === 'own' && !mode.own.unlocked;
                const on = value === goal;

                return (
                    <div key={goal}>
                        <button
                            type="button"
                            role="radio"
                            aria-checked={on}
                            disabled={locked}
                            onClick={() => onChange(goal)}
                            className={`flex w-full items-start gap-4 rounded-reos-lg border-2 bg-card p-4 text-left disabled:opacity-70 ${on ? 'border-primary' : 'border-border'}`}
                        >
                            <span className="mt-1 flex size-6 shrink-0 items-center justify-center rounded-full border border-border">
                                {on ? (
                                    <Check aria-hidden className="size-4" />
                                ) : null}
                                {locked ? (
                                    <Lock aria-hidden className="size-4" />
                                ) : null}
                            </span>
                            <span className="space-y-1">
                                <span className="block text-lg font-semibold">
                                    {t(`agents.modes.goals.${goal}.goal`)}{' '}
                                    <span className="text-base font-normal text-muted-foreground">
                                        · {t(`agents.modes.goals.${goal}.name`)}
                                    </span>
                                </span>
                                <span className="block text-base text-muted-foreground">
                                    {t(`agents.modes.goals.${goal}.body`)}
                                </span>
                                {locked ? (
                                    <span className="block text-base">
                                        {mode.own.unlocks_on
                                            ? t('agents.modes.ownLocked', {
                                                  date: date(
                                                      mode.own.unlocks_on,
                                                  ),
                                                  years: mode.own.years,
                                              })
                                            : t(
                                                  'agents.modes.ownLockedNoDate',
                                                  {
                                                      years: mode.own.years,
                                                  },
                                              )}
                                    </span>
                                ) : null}
                                {mode.needs_setup &&
                                goal === mode.suggested_default ? (
                                    <span className="inline-block rounded-reos-pill bg-reos-tone-info-bg px-3 py-0.5 text-sm font-semibold text-reos-tone-info-fg">
                                        {t('agents.modes.suggestedDefault')}
                                    </span>
                                ) : null}
                            </span>
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
