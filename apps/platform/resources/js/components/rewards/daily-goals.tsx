import { Check, Flame, Share2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Today } from './types';

/** Today's goals with points, the streak and Share (agent screen 111). */
export function DailyGoalsCard({
    today,
    onShare,
}: {
    today: Today;
    onShare?: (text: string) => void;
}) {
    const { t, i18n } = useTranslation();
    const day = (d: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            month: 'long',
            day: 'numeric',
            timeZone: 'UTC',
        }).format(new Date(`${d}T12:00:00Z`));

    return (
        <section
            aria-labelledby="goals-title"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 id="goals-title" className="text-lg font-semibold">
                        {t('rewards.today')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('rewards.pointsToday', {
                            count: today.points_today,
                        })}
                        {today.bonus_today > 0
                            ? ` · ${t('rewards.bonusToday', { count: today.bonus_today })}`
                            : ''}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline" className="gap-1 text-base">
                        <Flame aria-hidden className="size-4" />
                        {today.streak > 0
                            ? t('rewards.streak', { count: today.streak })
                            : t('rewards.noStreak')}
                    </Badge>
                    {today.streak > 0 && onShare ? (
                        <Button
                            variant="outline"
                            onClick={() =>
                                onShare(
                                    t('rewards.shareText', {
                                        count: today.streak,
                                    }),
                                )
                            }
                        >
                            <Share2 aria-hidden />
                            {t('rewards.share')}
                        </Button>
                    ) : null}
                </div>
            </div>
            {today.best_streak > 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('rewards.bestStreak', { count: today.best_streak })}
                </p>
            ) : null}
            <ul className="grid gap-3 md:grid-cols-2">
                {today.goals.map((g) => (
                    <li
                        key={g.key}
                        className={cn(
                            'flex items-start justify-between gap-3 rounded-reos-md border border-border p-3',
                            g.met && 'border-reos-tone-success-fg',
                        )}
                    >
                        <div className="space-y-1">
                            <p className="text-base font-semibold">
                                {t(`rewards.goals.${g.key}`)}
                            </p>
                            <p className="text-base">
                                {t('rewards.goalProgress', {
                                    done: g.done,
                                    target: g.target,
                                })}
                            </p>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                            {g.met ? (
                                <Badge className="gap-1 bg-reos-tone-success-bg text-reos-tone-success-fg">
                                    <Check aria-hidden className="size-4" />
                                    {t('rewards.met')}
                                </Badge>
                            ) : null}
                            <span className="text-sm text-muted-foreground">
                                {t('rewards.goalPoints', { count: g.points })}
                            </span>
                            {g.bonus > 0 ? (
                                <span className="text-sm text-reos-tone-info-fg">
                                    {t('rewards.goalBonus', { count: g.bonus })}
                                </span>
                            ) : null}
                        </div>
                    </li>
                ))}
            </ul>
            <p className="text-base">
                {today.all_done
                    ? t('rewards.allDone')
                    : t('rewards.allDoneHint', {
                          count: today.all_done_points,
                      })}
            </p>
            {today.campaigns.length > 0 ? (
                <div className="space-y-1">
                    <h3 className="text-base font-semibold">
                        {t('rewards.campaigns')}
                    </h3>
                    <ul className="space-y-1">
                        {today.campaigns.map((c) => (
                            <li key={c.id} className="text-base">
                                {t('rewards.campaignLine', {
                                    title: c.title,
                                    multiplier: c.multiplier,
                                    goal:
                                        c.goal === 'all'
                                            ? t('rewards.allGoals')
                                            : t(
                                                  `rewards.goals.${c.goal as 'post'}`,
                                              ),
                                    date: day(c.ends_on),
                                })}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}
