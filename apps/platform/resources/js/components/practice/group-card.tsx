import { Copy, LogOut, Swords, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { StudyGroup } from './types';

/**
 * A study group (screen 52, A9.3c): its size, this week's leaderboard of
 * questions answered and accuracy (first names only), the invite link,
 * Challenge the group, and Leave. `onJoin` shows Join for a group the
 * candidate isn't in yet.
 */
export function GroupCard({
    group,
    onJoin,
    onChallenge,
    onLeave,
}: {
    group: StudyGroup;
    onJoin?: () => void;
    onChallenge?: () => void;
    onLeave?: () => void;
}) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    return (
        <section
            aria-label={group.name}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h3 className="flex items-center gap-2 text-lg font-semibold">
                        <Users aria-hidden className="size-5 text-primary" />
                        {group.name}
                    </h3>
                    <p className="text-body text-muted-foreground">
                        {group.school
                            ? `${t('practice.groups.school', { school: group.school })} · `
                            : ''}
                        {t('practice.groups.members', {
                            count: group.members,
                            max: group.max,
                        })}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {!group.mine && onJoin ? (
                        <Button onClick={onJoin}>
                            {t('practice.groups.join')}
                        </Button>
                    ) : null}
                    {group.mine && onChallenge ? (
                        <Button onClick={onChallenge}>
                            <Swords aria-hidden />
                            {t('practice.groups.challenge')}
                        </Button>
                    ) : null}
                    {group.mine ? (
                        <Button
                            variant="outline"
                            onClick={() =>
                                void navigator.clipboard
                                    ?.writeText(group.link)
                                    .then(() => setCopied(true))
                            }
                        >
                            <Copy aria-hidden />
                            {t('practice.groups.copy')}
                        </Button>
                    ) : null}
                    {group.mine && onLeave ? (
                        <Button variant="ghost" onClick={onLeave}>
                            <LogOut aria-hidden />
                            {t('practice.groups.leave')}
                        </Button>
                    ) : null}
                </div>
            </div>
            {copied ? (
                <p role="status" className="text-body">
                    {t('practice.groups.copied')}
                </p>
            ) : null}
            {group.leaderboard.length > 0 ? (
                <ol
                    aria-label={t('practice.groups.leaderboard')}
                    className="divide-y divide-border"
                >
                    {group.leaderboard.map((row, i) => (
                        <li
                            key={`${row.first_name}-${i}`}
                            className="flex items-center justify-between gap-3 py-3"
                        >
                            <span className="font-medium">
                                {i + 1}.{' '}
                                {row.mine
                                    ? t('practice.groups.you', {
                                          name: row.first_name,
                                      })
                                    : row.first_name}
                            </span>
                            <span className="text-body text-muted-foreground">
                                {row.accuracy === null
                                    ? t('practice.groups.noAnswers')
                                    : `${t('practice.groups.questions', { count: row.questions })} · ${t('practice.groups.accuracy', { percent: row.accuracy })}`}
                            </span>
                        </li>
                    ))}
                </ol>
            ) : null}
        </section>
    );
}
