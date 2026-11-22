import { Link } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import type { InboxItem } from '@/components/messages/types';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/time-ago';
import { cn } from '@/lib/utils';
import { index } from '@/routes/messages';

/** The conversation list (B6.1): who, the last message, when, and unread. */
export function ConversationList({
    inbox,
    selected,
    onNew,
}: {
    inbox: InboxItem[];
    selected: string | null;
    onNew?: () => void;
}) {
    const { t, i18n } = useTranslation();

    if (inbox.length === 0) {
        return (
            <EmptyState
                icon={MessageSquare}
                title={t('messages.empty')}
                description={t('messages.emptyBody')}
                action={
                    onNew ? (
                        <Button onClick={onNew}>{t('messages.new')}</Button>
                    ) : undefined
                }
            />
        );
    }

    return (
        <ul aria-label={t('messages.list')} className="divide-y divide-border">
            {inbox.map((item) => {
                const last = item.last_message;
                const preview = last
                    ? last.deleted
                        ? t('messages.deleted')
                        : `${last.sender ? `${last.sender}: ` : ''}${last.body ?? ''}`
                    : '';

                return (
                    <li key={item.id}>
                        <Link
                            href={index({ query: { c: item.id } })}
                            preserveScroll
                            aria-current={
                                selected === item.id ? 'true' : undefined
                            }
                            className={cn(
                                'flex min-h-16 items-start gap-3 px-4 py-3 hover:bg-accent',
                                selected === item.id && 'bg-accent',
                            )}
                        >
                            <span
                                aria-hidden
                                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-reos-tone-info-bg font-semibold text-reos-tone-info-fg"
                            >
                                {(item.title.trim()[0] ?? '?').toUpperCase()}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="flex items-baseline justify-between gap-2">
                                    <span
                                        className={cn(
                                            'truncate',
                                            item.unread > 0
                                                ? 'font-semibold'
                                                : 'font-medium',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                    {last ? (
                                        <span className="shrink-0 text-sm text-muted-foreground">
                                            {timeAgo(last.at, i18n.language)}
                                        </span>
                                    ) : null}
                                </span>
                                <span className="flex items-center justify-between gap-2">
                                    <span className="truncate text-body text-muted-foreground">
                                        {preview}
                                    </span>
                                    {item.unread > 0 ? (
                                        <span
                                            className="shrink-0 rounded-full bg-primary px-2 text-sm font-semibold text-primary-foreground"
                                            aria-label={t(
                                                'messages.unreadCount',
                                                {
                                                    count: item.unread,
                                                },
                                            )}
                                        >
                                            {item.unread}
                                        </span>
                                    ) : null}
                                </span>
                            </span>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}
