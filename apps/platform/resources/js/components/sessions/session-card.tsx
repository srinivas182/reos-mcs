import { Link } from '@inertiajs/react';
import { Users, Video } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Session } from '@/components/sessions/types';
import { withWhom } from '@/components/sessions/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show } from '@/routes/sessions';

const tone: Record<Session['status'], string> = {
    scheduled: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    live: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    ended: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    cancelled: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
};

/** "Tue, Nov 24 · 3:00 – 3:45 PM" in the viewer's language and time zone. */
export function sessionTime(session: Session, language: string): string {
    const start = new Date(session.starts_at);
    const end = new Date(session.ends_at);
    const day = start.toLocaleDateString(language, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
    const time = new Intl.DateTimeFormat(language, {
        hour: 'numeric',
        minute: '2-digit',
    }).formatRange(start, end);

    return `${day} · ${time}`;
}

export function SessionStatus({ status }: { status: Session['status'] }) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                tone[status],
            )}
        >
            {t(`sessions.statuses.${status}`)}
        </span>
    );
}

/** One session in a list (B6.3): when, what, with whom, and Join once it opens. */
export function SessionCard({ session, me }: { session: Session; me: number }) {
    const { t, i18n } = useTranslation();
    const others = withWhom(session, me);
    const Icon = session.kind === 'live_class' ? Users : Video;
    const opens = new Date(session.join_opens_at).toLocaleTimeString(
        i18n.language,
        { hour: 'numeric', minute: '2-digit' },
    );

    const label =
        session.status === 'scheduled'
            ? t('sessions.opensAt', { time: opens })
            : t('sessions.view');

    return (
        <article className="flex flex-wrap items-center justify-between gap-4 rounded-reos-lg border border-border bg-card p-4">
            <div className="flex min-w-0 items-start gap-3">
                <Icon aria-hidden className="mt-1 size-5 shrink-0" />
                <div className="min-w-0 space-y-1">
                    <h3 className="text-base font-semibold">{session.title}</h3>
                    <p className="text-base text-muted-foreground">
                        {sessionTime(session, i18n.language)}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {t(`sessions.kinds.${session.kind}`)} ·{' '}
                        {session.kind === 'live_class'
                            ? t('sessions.people', { count: others.length })
                            : t('sessions.with', { names: others.join(', ') })}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <SessionStatus status={session.status} />
                {session.can_join ? (
                    <Button asChild>
                        <Link href={show(session.id).url}>
                            {t('sessions.join')}
                        </Link>
                    </Button>
                ) : (
                    <Button asChild variant="outline">
                        <Link
                            href={show(session.id).url}
                            aria-label={t('sessions.open', {
                                action: label,
                                title: session.title,
                            })}
                        >
                            {label}
                        </Link>
                    </Button>
                )}
            </div>
        </article>
    );
}
