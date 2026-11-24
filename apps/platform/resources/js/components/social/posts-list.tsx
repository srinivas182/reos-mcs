import { ExternalLink, Megaphone, Pencil, Repeat2, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    METRICS,
    type PostList,
    type PostStatus,
    type SocialPost,
    type TargetStatus,
} from './types';

const TONE: Record<PostStatus | TargetStatus, string> = {
    draft: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    approval: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    scheduled: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    publishing: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    queued: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    published: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    partly: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    failed: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
};

const when = (iso: string, locale: string) =>
    new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));

/**
 * My posts and results (B11; agent screen 169): this month's numbers, then
 * Scheduled, Posted and Drafts, each post with its accounts, status and
 * results; Edit, Remove (with Undo) and Post again.
 */
export function PostsList({
    posts,
    onEdit,
    onRemove,
    onRepost,
}: {
    posts: PostList;
    onEdit: (post: SocialPost) => void;
    onRemove: (post: SocialPost) => void;
    onRepost: (post: SocialPost) => void;
}) {
    const { t, i18n } = useTranslation();
    const sections = [
        ['scheduled', posts.scheduled],
        ['posted', posts.posted],
        ['drafts', posts.drafts],
    ] as const;
    const empty =
        posts.scheduled.length + posts.posted.length + posts.drafts.length ===
        0;

    return (
        <section aria-labelledby="my-posts" className="space-y-4">
            <h2 id="my-posts" className="text-lg font-semibold">
                {t('social.posts.title')}
            </h2>
            <dl className="grid gap-3 sm:grid-cols-4">
                {(
                    [
                        'posts_this_month',
                        'reach',
                        'engagement',
                        'clicks',
                    ] as const
                ).map((key) => (
                    <div
                        key={key}
                        className="rounded-reos-lg border border-border bg-card p-4"
                    >
                        <dt className="text-base text-muted-foreground">
                            {t(`social.posts.stats.${key}`)}
                        </dt>
                        <dd className="text-2xl font-semibold">
                            {new Intl.NumberFormat(i18n.language).format(
                                posts.stats[key],
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
            <p className="text-base text-muted-foreground">
                {t('social.posts.resultsNote')}
            </p>
            {empty ? (
                <EmptyState
                    icon={Megaphone}
                    title={t('social.posts.emptyTitle')}
                    description={t('social.posts.emptyBody')}
                />
            ) : null}
            {sections.map(([key, list]) =>
                list.length === 0 ? null : (
                    <div key={key} className="space-y-3">
                        <h3 className="text-base font-semibold">
                            {t(`social.posts.${key}`, { count: list.length })}
                        </h3>
                        <ul className="space-y-3">
                            {list.map((post) => (
                                <li
                                    key={post.id}
                                    className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <p className="line-clamp-3 min-w-0 flex-1 text-base whitespace-pre-line">
                                            {post.body}
                                        </p>
                                        <span
                                            className={cn(
                                                'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                                TONE[post.status],
                                            )}
                                        >
                                            {t(`social.status.${post.status}`)}
                                        </span>
                                    </div>
                                    {post.approval_note &&
                                    post.status === 'draft' ? (
                                        <p className="rounded-reos-md bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg">
                                            {t('studio.checks.returned', {
                                                note: post.approval_note,
                                            })}
                                        </p>
                                    ) : null}
                                    <p className="text-base text-muted-foreground">
                                        {post.scheduled_at &&
                                        post.status === 'scheduled'
                                            ? t('social.compose.scheduled', {
                                                  date: when(
                                                      post.scheduled_at,
                                                      i18n.language,
                                                  ),
                                              })
                                            : post.published_at
                                              ? when(
                                                    post.published_at,
                                                    i18n.language,
                                                )
                                              : null}
                                    </p>
                                    <ul className="space-y-2">
                                        {post.targets.map((target) => (
                                            <li
                                                key={target.id}
                                                className="flex flex-wrap items-center gap-2 text-base"
                                            >
                                                <span className="font-semibold">
                                                    {t(
                                                        `social.channels.${target.channel}`,
                                                    )}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {target.name}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'rounded-reos-pill px-2 py-0.5 text-sm font-semibold',
                                                        TONE[target.status],
                                                    )}
                                                >
                                                    {t(
                                                        `social.status.${target.status}`,
                                                    )}
                                                </span>
                                                {target.status === 'failed' &&
                                                target.error === 'reconnect' ? (
                                                    <span className="text-destructive">
                                                        {t(
                                                            'social.errors.reconnect',
                                                        )}
                                                    </span>
                                                ) : null}
                                                {METRICS.filter(
                                                    (m) =>
                                                        target.insights[m] !==
                                                        undefined,
                                                ).map((m) => (
                                                    <span
                                                        key={m}
                                                        className="text-muted-foreground"
                                                    >
                                                        {t(
                                                            `social.insights.${m}`,
                                                        )}{' '}
                                                        {target.insights[m]}
                                                    </span>
                                                ))}
                                                {target.url ? (
                                                    <a
                                                        href={target.url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex min-h-12 items-center gap-1 text-primary underline-offset-4 hover:underline"
                                                    >
                                                        <ExternalLink
                                                            aria-hidden
                                                            className="size-4"
                                                        />
                                                        {t(
                                                            'social.posts.open',
                                                            {
                                                                channel: t(
                                                                    `social.channels.${target.channel}`,
                                                                ),
                                                            },
                                                        )}
                                                    </a>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="flex flex-wrap gap-2">
                                        {post.status === 'draft' ||
                                        post.status === 'scheduled' ? (
                                            <>
                                                <Button
                                                    variant="outline"
                                                    onClick={() => onEdit(post)}
                                                >
                                                    <Pencil aria-hidden />
                                                    {t('social.compose.edit')}
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    onClick={() =>
                                                        onRemove(post)
                                                    }
                                                >
                                                    <Trash2 aria-hidden />
                                                    {t(
                                                        'social.compose.removeMedia',
                                                    )}
                                                </Button>
                                            </>
                                        ) : (
                                            <Button
                                                variant="outline"
                                                onClick={() => onRepost(post)}
                                            >
                                                <Repeat2 aria-hidden />
                                                {t('social.compose.repost')}
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                ),
            )}
        </section>
    );
}
