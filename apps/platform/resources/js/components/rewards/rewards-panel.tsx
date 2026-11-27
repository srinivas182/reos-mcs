import { Link } from '@inertiajs/react';
import { Coins, History } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import type { HistoryRow, RewardOption, RewardsSummary } from './types';

const money = (cents: number, lang: string) =>
    new Intl.NumberFormat(lang, { style: 'currency', currency: 'USD' }).format(
        cents / 100,
    );

/** Turns a ledger line into words ("Daily goal: Conversations"). */
export function useReasonLabel() {
    const { t } = useTranslation();

    return (row: HistoryRow): string => {
        const memo = row.memo ?? '';
        const [kind, , third, fourth] = memo.split(':');

        if (row.reason === 'points_returned')
            return t('rewards.reasons.returned');
        if (kind === 'goal')
            return third === 'all_done'
                ? t('rewards.reasons.all_done')
                : t('rewards.reasons.goal', {
                      goal: t(`rewards.goals.${third as 'post'}`),
                  });
        if (kind === 'campaign')
            return t('rewards.reasons.bonus', {
                goal: t(`rewards.goals.${fourth as 'post'}`),
            });
        if (kind === 'session') return t('rewards.reasons.session');
        if (kind === 'order') return t('rewards.reasons.order');

        return t('rewards.reasons.other');
    };
}

/** The balance, what points pay for and recent points (agent screen 112). */
export function RewardsPanel({
    rewards,
    busy = false,
    mentorsHref,
    onRedeem,
}: {
    rewards: RewardsSummary;
    busy?: boolean;
    mentorsHref?: string;
    onRedeem: (option: RewardOption) => void;
}) {
    const { t, i18n } = useTranslation();
    const label = useReasonLabel();
    const when = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            month: 'short',
            day: 'numeric',
        }).format(new Date(iso));
    const optionName = (o: RewardOption) =>
        o.kind === 'credit_pack'
            ? t('rewards.creditPack', { credits: o.credits ?? 0 })
            : o.interval === 'year'
              ? t('rewards.membershipYear', { name: o.name })
              : t('rewards.membership', { name: o.name });

    return (
        <div className="space-y-6">
            <section
                aria-labelledby="balance-title"
                className="space-y-2 rounded-reos-lg border border-border bg-card p-5"
            >
                <h2 id="balance-title" className="text-lg font-semibold">
                    {t('rewards.balance')}
                </h2>
                <p className="text-2xl font-semibold">
                    {t('rewards.balanceValue', {
                        points: rewards.balance.toLocaleString(i18n.language),
                        value: money(rewards.value_cents, i18n.language),
                    })}
                </p>
                <p className="text-sm text-muted-foreground">
                    {t('rewards.noCash')}
                </p>
            </section>

            <section aria-labelledby="use-title" className="space-y-3">
                <h2 id="use-title" className="text-lg font-semibold">
                    {t('rewards.use')}
                </h2>
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-reos-md border border-border bg-card p-4">
                    <div>
                        <p className="text-base font-semibold">
                            {t('rewards.mentorSessions')}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('rewards.mentorSessionsHint')}
                        </p>
                    </div>
                    {mentorsHref ? (
                        <Button variant="outline" asChild>
                            <Link href={mentorsHref}>
                                {t('rewards.findMentor')}
                            </Link>
                        </Button>
                    ) : null}
                </div>
                {rewards.options.length === 0 ? (
                    <EmptyState
                        icon={Coins}
                        title={t('rewards.noOptions')}
                        description={t('rewards.noOptionsHint')}
                    />
                ) : (
                    <ul className="space-y-2">
                        {rewards.options.map((o) => (
                            <li
                                key={o.price}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-reos-md border border-border bg-card p-4"
                            >
                                <div>
                                    <p className="text-base font-semibold">
                                        {optionName(o)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {t('rewards.optionPoints', {
                                            count: o.points,
                                        })}
                                    </p>
                                </div>
                                <Button
                                    disabled={busy || !o.affordable}
                                    onClick={() => onRedeem(o)}
                                >
                                    {o.affordable
                                        ? t('rewards.redeem', {
                                              count: o.points,
                                          })
                                        : t('rewards.notEnough')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="history-title" className="space-y-3">
                <h2 id="history-title" className="text-lg font-semibold">
                    {t('rewards.history')}
                </h2>
                {rewards.history.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title={t('rewards.noHistory')}
                        description={t('rewards.noHistoryHint')}
                    />
                ) : (
                    <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
                        {rewards.history.map((h, i) => (
                            <li
                                key={`${h.at}-${i}`}
                                className="flex items-center justify-between gap-3 px-4 py-3 text-base"
                            >
                                <span>
                                    {label(h)}{' '}
                                    <span className="text-sm text-muted-foreground">
                                        · {when(h.at)}
                                    </span>
                                </span>
                                <span
                                    className={
                                        h.points > 0
                                            ? 'font-semibold text-reos-tone-success-fg'
                                            : 'font-semibold'
                                    }
                                >
                                    {h.points > 0 ? '+' : ''}
                                    {h.points}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
