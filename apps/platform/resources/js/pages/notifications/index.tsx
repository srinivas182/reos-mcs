import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/time-ago';
import { cn } from '@/lib/utils';
import { read, readAll } from '@/routes/notifications';
import { edit as settings } from '@/routes/notification-settings';

type Item = {
    id: string;
    kind: string;
    title: string;
    body: string | null;
    url: string | null;
    read: boolean;
    at: string;
};

/**
 * Notifications (B6.2): everything REOS told you, newest first, unread in
 * bold. Opening one marks it read; Mark all read; the settings link sets
 * channels, quiet hours and digests.
 */
export default function Notifications({
    unread,
    items,
}: {
    unread: number;
    items: Item[];
}) {
    const { t, i18n } = useTranslation();

    const open = (item: Item) => {
        if (!item.read) {
            router.post(read(item.id).url, {}, { preserveScroll: true });
        }

        if (item.url) {
            window.location.assign(item.url);
        }
    };

    return (
        <>
            <Head title={t('notifications.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('notifications.title')}
                    description={t('notifications.purpose')}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                disabled={unread === 0}
                                onClick={() =>
                                    router.post(
                                        readAll().url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck aria-hidden />
                                {t('notifications.readAll')}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={settings()}>
                                    {t('notifications.settings')}
                                </Link>
                            </Button>
                        </div>
                    }
                />
                <section className="rounded-reos-lg border border-border bg-card">
                    {items.length === 0 ? (
                        <div className="p-6">
                            <EmptyState
                                icon={Bell}
                                title={t('notifications.empty')}
                                description={t('notifications.emptyBody')}
                            />
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {items.map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => open(item)}
                                        className={cn(
                                            'flex min-h-16 w-full items-start gap-3 px-5 py-4 text-left hover:bg-accent',
                                            !item.read &&
                                                'bg-reos-tone-info-bg/40',
                                        )}
                                    >
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'mt-2 size-2.5 shrink-0 rounded-full',
                                                item.read
                                                    ? 'bg-transparent'
                                                    : 'bg-primary',
                                            )}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className={cn(
                                                    'block',
                                                    item.read
                                                        ? 'font-medium'
                                                        : 'font-semibold',
                                                )}
                                            >
                                                {item.title}
                                                {!item.read ? (
                                                    <span className="sr-only">
                                                        {' '}
                                                        (
                                                        {t(
                                                            'notifications.unread',
                                                        )}
                                                        )
                                                    </span>
                                                ) : null}
                                            </span>
                                            {item.body ? (
                                                <span className="block text-body text-muted-foreground">
                                                    {item.body}
                                                </span>
                                            ) : null}
                                        </span>
                                        <time
                                            dateTime={item.at}
                                            className="shrink-0 text-sm text-muted-foreground"
                                        >
                                            {timeAgo(item.at, i18n.language)}
                                        </time>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
