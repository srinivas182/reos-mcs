import { Link } from '@inertiajs/react';
import { Award, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { LiveClassRow } from '@/components/classes/types';
import { SessionStatus } from '@/components/sessions/session-card';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/sessions';

/** "Tue, Nov 24 · 10:30 AM – 12:30 PM" in the viewer's language and zone. */
export function classTime(row: LiveClassRow, language: string): string {
    const start = new Date(row.starts_at);
    const day = start.toLocaleDateString(language, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
    const time = new Intl.DateTimeFormat(language, {
        hour: 'numeric',
        minute: '2-digit',
    }).formatRange(start, new Date(row.ends_at));

    return `${day} · ${time}`;
}

/**
 * One live class (A10.2b; instructor i11, candidate L12 and the course's
 * Live classes): course, time, CE label and registrations, with Register,
 * Join or Open.
 */
export function ClassCard({
    row,
    onRegister,
    onUnregister,
    busy = false,
}: {
    row: LiveClassRow;
    onRegister?: () => void;
    onUnregister?: () => void;
    busy?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const open = show(row.id).url;

    return (
        <article className="flex flex-wrap items-center justify-between gap-4 rounded-reos-lg border border-border bg-card p-4">
            <div className="min-w-0 space-y-1">
                <h3 className="text-base font-semibold">{row.title}</h3>
                <p className="text-base text-muted-foreground">
                    {classTime(row, i18n.language)}
                </p>
                <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                    <span>{row.course.title}</span>
                    {row.me !== 'host' ? (
                        <span>{t('classes.with', { name: row.host })}</span>
                    ) : null}
                    <span className="inline-flex items-center gap-1">
                        <Users aria-hidden className="size-4" />
                        {t('classes.registered', {
                            count: row.registered,
                            capacity: row.capacity,
                        })}
                    </span>
                    {row.ce ? (
                        <span className="inline-flex items-center gap-1 rounded-reos-pill bg-reos-tone-success-bg px-2.5 py-0.5 font-semibold text-reos-tone-success-fg">
                            <Award aria-hidden className="size-4" />
                            {row.ce_hours !== null
                                ? t('classes.ceHours', { count: row.ce_hours })
                                : t('classes.ce')}
                        </span>
                    ) : null}
                </p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <SessionStatus status={row.status} />
                {row.can_join ? (
                    <Button asChild>
                        <Link href={open}>{t('sessions.join')}</Link>
                    </Button>
                ) : row.me !== null ? (
                    <Button asChild variant="outline">
                        <Link
                            href={open}
                            aria-label={t('classes.openLabel', {
                                title: row.title,
                            })}
                        >
                            {t('classes.open')}
                        </Link>
                    </Button>
                ) : null}
                {row.me === 'registered' &&
                row.status === 'scheduled' &&
                onUnregister ? (
                    <Button
                        variant="ghost"
                        disabled={busy}
                        onClick={onUnregister}
                    >
                        {t('classes.unregister')}
                    </Button>
                ) : null}
                {row.me === null && onRegister ? (
                    <Button
                        disabled={busy || !row.can_register}
                        onClick={onRegister}
                    >
                        {row.full ? t('classes.full') : t('classes.register')}
                    </Button>
                ) : null}
            </div>
        </article>
    );
}
