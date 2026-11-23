import { Link } from '@inertiajs/react';
import { Copy, Medal, Play, Swords } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    attempt as openAttempt,
    results as openResults,
} from '@/routes/practice';
import type { ChallengeBoard } from './types';

/**
 * One challenge (screen 52, A9.3b): who sent it, the share link to copy,
 * the candidate's own attempt (carry on or see results), and the board by
 * points with first names only. `onAccept` shows Accept for one not yet
 * taken.
 */
export function ChallengeCard({
    challenge,
    onAccept,
}: {
    challenge: ChallengeBoard;
    onAccept?: () => void;
}) {
    const { t, i18n } = useTranslation();
    const [copied, setCopied] = useState(false);
    const until = new Date(challenge.expires_at).toLocaleDateString(
        i18n.language,
        { month: 'long', day: 'numeric' },
    );

    return (
        <section
            aria-label={
                challenge.mine
                    ? t('practice.challenges.yours')
                    : t('practice.challenges.from', { name: challenge.from })
            }
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <Swords aria-hidden className="size-5 text-primary" />
                        {challenge.mine
                            ? t('practice.challenges.yours')
                            : t('practice.challenges.from', {
                                  name: challenge.from,
                              })}
                    </h2>
                    <p className="text-body text-muted-foreground">
                        {challenge.expired
                            ? t('practice.challenges.ended')
                            : t('practice.challenges.until', {
                                  count: challenge.questions,
                                  date: until,
                              })}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {challenge.attempt_id === null ? (
                        onAccept && !challenge.expired ? (
                            <Button onClick={onAccept}>
                                <Play aria-hidden />
                                {t('practice.challenges.accept')}
                            </Button>
                        ) : null
                    ) : challenge.submitted ? (
                        <Button variant="outline" asChild>
                            <Link href={openResults(challenge.attempt_id)}>
                                {t('practice.seeResults')}
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href={openAttempt(challenge.attempt_id)}>
                                <Play aria-hidden />
                                {t('practice.challenges.answer')}
                            </Link>
                        </Button>
                    )}
                    {challenge.mine && !challenge.expired ? (
                        <Button
                            variant="outline"
                            onClick={() =>
                                void navigator.clipboard
                                    ?.writeText(challenge.link)
                                    .then(() => setCopied(true))
                            }
                        >
                            <Copy aria-hidden />
                            {t('practice.challenges.copy')}
                        </Button>
                    ) : null}
                </div>
            </div>
            {challenge.mine && !challenge.expired ? (
                <p className="text-sm break-all text-muted-foreground">
                    {challenge.link}
                </p>
            ) : null}
            {copied ? (
                <p role="status" className="text-body">
                    {t('practice.challenges.copied')}
                </p>
            ) : null}
            {challenge.entries.length > 0 ? (
                <ol
                    aria-label={t('practice.challenges.board')}
                    className="divide-y divide-border"
                >
                    {challenge.entries.map((entry, i) => (
                        <li
                            key={`${entry.first_name}-${i}`}
                            className="flex items-center justify-between gap-3 py-3"
                        >
                            <span className="flex items-center gap-2 font-medium">
                                {i === 0 && entry.finished ? (
                                    <Medal
                                        aria-hidden
                                        className="size-5 text-primary"
                                    />
                                ) : null}
                                {entry.mine
                                    ? t('practice.challenges.you', {
                                          name: entry.first_name,
                                      })
                                    : entry.first_name}
                            </span>
                            <span className="text-body text-muted-foreground">
                                {entry.finished
                                    ? t('practice.challenges.points', {
                                          count: entry.points,
                                          correct: entry.correct,
                                          total: entry.total,
                                      })
                                    : t('practice.challenges.playing')}
                            </span>
                        </li>
                    ))}
                </ol>
            ) : null}
        </section>
    );
}
