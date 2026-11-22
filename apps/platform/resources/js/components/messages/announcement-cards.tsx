import { Megaphone } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { AnnouncementItem } from '@/components/messages/types';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/time-ago';

/** Announcements not yet read (B6.1b), above Messages: from whom, when, and Mark as read. */
export function AnnouncementCards({
    announcements,
    onRead,
}: {
    announcements: AnnouncementItem[];
    onRead: (announcement: AnnouncementItem) => void;
}) {
    const { t, i18n } = useTranslation();

    if (announcements.length === 0) {
        return null;
    }

    return (
        <section aria-label={t('announcements.title')} className="space-y-3">
            {announcements.map((item) => (
                <article
                    key={item.id}
                    className="flex flex-col gap-3 rounded-reos-lg border border-border bg-card p-5 sm:flex-row sm:items-start"
                >
                    <span
                        aria-hidden
                        className="flex size-12 shrink-0 items-center justify-center rounded-reos-md bg-reos-tone-info-bg text-reos-tone-info-fg"
                    >
                        <Megaphone className="size-6" />
                    </span>
                    <div className="min-w-0 flex-1 space-y-1">
                        <h2 className="font-semibold">{item.title}</h2>
                        <p className="text-sm text-muted-foreground">
                            {t('announcements.from', {
                                from: item.from,
                                ago: timeAgo(item.sent_at, i18n.language),
                            })}
                        </p>
                        <p className="text-body whitespace-pre-wrap">
                            {item.body}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => onRead(item)}
                        aria-label={t('announcements.markReadFor', {
                            title: item.title,
                        })}
                    >
                        {t('announcements.markRead')}
                    </Button>
                </article>
            ))}
        </section>
    );
}
