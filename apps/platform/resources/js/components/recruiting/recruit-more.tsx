import { MessageSquare, Sparkles, Users } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { OpenAgent, SponsoredBoard } from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const REASONS = ['closings', 'city', 'new'] as const;
const END = ['passed', 'other_sponsor', 'withdrawn'] as const;

/**
 * Sponsored AI help (A17.2, broker screen 26): the pool, each sponsored
 * candidate's use and status, and the cap.
 */
export function SponsoredHelp({
    board,
    onCap,
}: {
    board: SponsoredBoard;
    onCap: (id: string, cap: number) => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [caps, setCaps] = useState<Record<string, string>>({});
    const status = (s: SponsoredBoard['candidates'][number]): string => {
        if (s.status === 'ended') {
            switch (END.find((r) => r === s.end_reason)) {
                case 'passed':
                    return t('recruiting.sponsored.reasons.passed');
                case 'other_sponsor':
                    return t('recruiting.sponsored.reasons.other_sponsor');
                case 'withdrawn':
                    return t('recruiting.sponsored.reasons.withdrawn');
                default:
                    return t('recruiting.sponsored.statuses.ended');
            }
        }

        return s.status === 'used_up'
            ? t('recruiting.sponsored.statuses.used_up')
            : t('recruiting.sponsored.statuses.active');
    };

    return (
        <section className={card} aria-labelledby={`${ids}-sponsored`}>
            <div className="space-y-1">
                <h2
                    id={`${ids}-sponsored`}
                    className="text-title font-semibold"
                >
                    {t('recruiting.sponsored.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('recruiting.sponsored.purpose')}
                </p>
                <p className="font-medium">
                    {t('recruiting.sponsored.pool', { credits: board.pool })}
                </p>
            </div>
            {board.candidates.length === 0 ? (
                <EmptyState
                    icon={Sparkles}
                    title={t('recruiting.sponsored.empty')}
                    description={t('recruiting.sponsored.emptyHint')}
                    className="border-0"
                />
            ) : (
                <ul className="divide-y divide-border">
                    {board.candidates.map((s) => (
                        <li
                            key={s.id}
                            className="flex flex-wrap items-end justify-between gap-3 py-4"
                        >
                            <div>
                                <p className="font-medium">{s.candidate}</p>
                                <p className="text-base text-muted-foreground">
                                    {t('recruiting.sponsored.usage', {
                                        used: s.used,
                                        cap: s.cap,
                                    })}{' '}
                                    · {status(s)}
                                </p>
                            </div>
                            {s.status !== 'ended' ? (
                                <form
                                    className="flex items-end gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        onCap(
                                            s.id,
                                            Number(caps[s.id] ?? s.cap),
                                        );
                                    }}
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor={`${ids}-${s.id}`}>
                                            {t('recruiting.sponsored.cap', {
                                                name: s.candidate,
                                            })}
                                        </Label>
                                        <Input
                                            id={`${ids}-${s.id}`}
                                            type="number"
                                            min={0}
                                            max={5000}
                                            className="w-32"
                                            value={caps[s.id] ?? String(s.cap)}
                                            onChange={(e) =>
                                                setCaps({
                                                    ...caps,
                                                    [s.id]: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <Button type="submit" variant="outline">
                                        {t('recruiting.sponsored.save')}
                                    </Button>
                                </form>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/**
 * Licensed agents open to a move (A17.2, broker screen 27): first name,
 * closings and why they match, with Invite to talk.
 */
export function OpenAgents({
    agents,
    onInvite,
}: {
    agents: OpenAgent[];
    onInvite: (agent: OpenAgent) => void;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const reason = (r: OpenAgent['reasons'][number]): string => {
        switch (REASONS.find((x) => x === r)) {
            case 'closings':
                return t('recruiting.agents.reasons.closings');
            case 'city':
                return t('recruiting.agents.reasons.city');
            default:
                return t('recruiting.agents.reasons.new');
        }
    };

    return (
        <section className={card} aria-labelledby={`${ids}-agents`}>
            <div className="space-y-1">
                <h2 id={`${ids}-agents`} className="text-title font-semibold">
                    {t('recruiting.agents.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('recruiting.agents.purpose')}
                </p>
            </div>
            {agents.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title={t('recruiting.agents.empty')}
                    description={t('recruiting.agents.emptyHint')}
                    className="border-0"
                />
            ) : (
                <ul className="divide-y divide-border">
                    {agents.map((a) => (
                        <li
                            key={a.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-4"
                        >
                            <div className="space-y-1">
                                <p className="font-medium">{a.name}</p>
                                <p className="text-base text-muted-foreground">
                                    {t('recruiting.agents.closings', {
                                        count: a.closings,
                                    })}
                                    {a.reasons.length > 0
                                        ? ` · ${a.reasons.map(reason).join(' · ')}`
                                        : ''}
                                </p>
                                {a.invited_at ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('recruiting.agents.invited', {
                                            date: new Date(
                                                a.invited_at,
                                            ).toLocaleDateString(
                                                i18n.language,
                                                {
                                                    dateStyle: 'medium',
                                                },
                                            ),
                                        })}
                                    </p>
                                ) : null}
                            </div>
                            <Button
                                aria-label={t('recruiting.agents.inviteFor', {
                                    name: a.name,
                                })}
                                onClick={() => onInvite(a)}
                            >
                                <MessageSquare aria-hidden />
                                {t('recruiting.agents.invite')}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
