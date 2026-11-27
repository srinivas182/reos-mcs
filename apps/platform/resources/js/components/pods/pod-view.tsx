import { Link } from '@inertiajs/react';
import { MessageSquare, Trophy, Users, Video } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { ChallengeRow, PodView } from './types';

const when = (iso: string, lang: string) =>
    new Intl.DateTimeFormat(lang, {
        weekday: 'long',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));

/** The agent's pod (screen 131): members' weekly goals and shared numbers, the call, messages. */
export function PodPanel({
    pod,
    busy = false,
    callHref,
    messagesHref,
    onJoin,
    onLeave,
    onGoal,
}: {
    pod: PodView;
    busy?: boolean;
    callHref?: string | null;
    messagesHref?: string | null;
    onJoin: () => void;
    onLeave: () => void;
    onGoal: (text: string) => void;
}) {
    const { t, i18n } = useTranslation();
    const mine = pod.pod?.members.find((m) => m.me);
    const [goal, setGoal] = useState(mine?.goal ?? '');

    if (pod.status === 'none' || pod.pod === null) {
        return (
            <EmptyState
                icon={Users}
                title={
                    pod.status === 'waiting'
                        ? t('pods.waiting')
                        : t('pods.none')
                }
                description={
                    pod.status === 'waiting'
                        ? t('pods.waitingHint')
                        : t('pods.noneHint')
                }
                action={
                    pod.status === 'waiting' ? (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={onLeave}
                        >
                            {t('pods.cancel')}
                        </Button>
                    ) : (
                        <Button disabled={busy} onClick={onJoin}>
                            {t('pods.join')}
                        </Button>
                    )
                }
            />
        );
    }

    const p = pod.pod;

    return (
        <section
            aria-labelledby="pod-title"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 id="pod-title" className="text-lg font-semibold">
                        {p.name}
                    </h2>
                    {p.call.next ? (
                        <p className="text-base text-muted-foreground">
                            {t('pods.callNext', {
                                time: when(p.call.next, i18n.language),
                            })}
                        </p>
                    ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                    {callHref ? (
                        <Button asChild>
                            <Link href={callHref}>
                                <Video aria-hidden />
                                {t('pods.joinCall')}
                            </Link>
                        </Button>
                    ) : null}
                    {messagesHref ? (
                        <Button variant="outline" asChild>
                            <Link href={messagesHref}>
                                <MessageSquare aria-hidden />
                                {t('pods.message')}
                            </Link>
                        </Button>
                    ) : null}
                </div>
            </div>
            <p className="text-sm text-muted-foreground">{t('pods.private')}</p>
            <ul className="grid gap-3 md:grid-cols-2">
                {p.members.map((m) => (
                    <li
                        key={m.id}
                        className={cn(
                            'space-y-2 rounded-reos-md border border-border p-3',
                            m.me && 'border-reos-action',
                        )}
                    >
                        <p className="text-base font-semibold">
                            {m.name}
                            {m.me ? ` (${t('pods.you')})` : ''}
                        </p>
                        <p className="text-base">
                            {m.goal ?? (
                                <span className="text-muted-foreground">
                                    {t('pods.noGoal')}
                                </span>
                            )}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {m.metrics.map((x) => (
                                <Badge key={x.key} variant="outline">
                                    {t(
                                        `mentoring.workspace.metrics.${x.key as 'conversations'}`,
                                    )}
                                    :{' '}
                                    {t('mentoring.workspace.metricValue', {
                                        done: x.done,
                                        target: x.target,
                                    })}
                                </Badge>
                            ))}
                        </div>
                    </li>
                ))}
            </ul>
            <form
                className="flex flex-wrap items-end gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    onGoal(goal.trim());
                }}
            >
                <div className="min-w-64 flex-1 space-y-2">
                    <Label htmlFor="pod-goal">{t('pods.yourGoal')}</Label>
                    <Input
                        id="pod-goal"
                        value={goal}
                        maxLength={200}
                        placeholder={t('pods.goalPlaceholder')}
                        onChange={(e) => setGoal(e.target.value)}
                    />
                </div>
                <Button type="submit" disabled={busy || goal.trim().length < 2}>
                    {t('pods.saveGoal')}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    disabled={busy}
                    onClick={onLeave}
                >
                    {t('pods.leave')}
                </Button>
            </form>
        </section>
    );
}

/** Brokerage challenges (opt-in): total and most improved boards. */
export function ChallengeList({
    challenges,
    busy = false,
    onJoin,
    onLeave,
}: {
    challenges: ChallengeRow[];
    busy?: boolean;
    onJoin?: (c: ChallengeRow) => void;
    onLeave?: (c: ChallengeRow) => void;
}) {
    const { t, i18n } = useTranslation();
    const day = (d: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            month: 'long',
            day: 'numeric',
            timeZone: 'UTC',
        }).format(new Date(`${d}T12:00:00Z`));

    if (challenges.length === 0) {
        return (
            <EmptyState
                icon={Trophy}
                title={t('pods.challenges.none')}
                description={t('pods.challenges.noneHint')}
            />
        );
    }

    return (
        <ul className="space-y-4">
            {challenges.map((c) => (
                <li
                    key={c.id}
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <p className="text-lg font-semibold">{c.title}</p>
                            <p className="text-sm text-muted-foreground">
                                {t(`pods.challenges.metrics.${c.metric}`)} ·{' '}
                                {c.open
                                    ? t('pods.challenges.ends', {
                                          date: day(c.ends_on),
                                      })
                                    : t('pods.challenges.ended')}{' '}
                                ·{' '}
                                {t('pods.challenges.entrants', {
                                    count: c.entrants,
                                })}
                            </p>
                        </div>
                        {onJoin && onLeave && c.open ? (
                            c.joined ? (
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => onLeave(c)}
                                >
                                    {t('pods.challenges.leave')}
                                </Button>
                            ) : (
                                <Button
                                    disabled={busy}
                                    onClick={() => onJoin(c)}
                                >
                                    {t('pods.challenges.join')}
                                </Button>
                            )
                        ) : null}
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        {(['total', 'improved'] as const).map((board) => (
                            <div key={board} className="space-y-2">
                                <h3 className="text-base font-semibold">
                                    {t(`pods.challenges.${board}`)}
                                </h3>
                                <ol className="space-y-1">
                                    {c[board].map((r, i) => (
                                        <li
                                            key={r.id}
                                            className={cn(
                                                'flex items-center justify-between gap-2 rounded-reos-md px-2 py-1 text-base',
                                                r.me &&
                                                    'bg-reos-tone-info-bg text-reos-tone-info-fg',
                                            )}
                                        >
                                            <span>
                                                {i + 1}. {r.name}
                                                {r.personal_best ? (
                                                    <Badge className="ml-2">
                                                        {t(
                                                            'pods.challenges.personalBest',
                                                        )}
                                                    </Badge>
                                                ) : null}
                                            </span>
                                            <span className="font-medium">
                                                {board === 'total'
                                                    ? r.value
                                                    : `${r.improved > 0 ? '+' : ''}${r.improved}`}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        ))}
                    </div>
                </li>
            ))}
        </ul>
    );
}
