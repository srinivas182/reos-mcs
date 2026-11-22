import { Head, usePage } from '@inertiajs/react';
import { CalendarPlus, Video } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ScheduleDialog } from '@/components/sessions/schedule-dialog';
import { SessionCard } from '@/components/sessions/session-card';
import type { Contact, Session } from '@/components/sessions/types';
import { Button } from '@/components/ui/button';

/**
 * Sessions and live classes (B6.3; the instructor's Live classes, web
 * journey screen 46, and every role's sessions): coming up first, by day,
 * then the last month with their notes. Instructors and mentors schedule.
 */
export default function Sessions({
    sessions,
    canHost,
    contacts,
    lengths,
}: {
    sessions: Session[];
    canHost: boolean;
    contacts: Contact[];
    lengths: number[];
}) {
    const { t, i18n } = useTranslation();
    const { auth } = usePage().props;
    const me = auth.user?.id ?? 0;
    const [scheduling, setScheduling] = useState(false);
    const now = Date.now();
    const coming = sessions.filter(
        (s) =>
            new Date(s.ends_at).getTime() > now &&
            s.status !== 'ended' &&
            s.status !== 'cancelled',
    );
    const past = sessions
        .filter((s) => !coming.includes(s))
        .sort((a, b) => b.starts_at.localeCompare(a.starts_at));
    const days = new Map<string, Session[]>();

    for (const session of coming) {
        const day = new Date(session.starts_at).toLocaleDateString(
            i18n.language,
            { weekday: 'long', month: 'long', day: 'numeric' },
        );
        days.set(day, [...(days.get(day) ?? []), session]);
    }

    const schedule = canHost ? (
        <Button onClick={() => setScheduling(true)}>
            <CalendarPlus aria-hidden />
            {t('sessions.schedule.open')}
        </Button>
    ) : null;

    return (
        <>
            <Head title={t('sessions.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('sessions.title')}
                    description={t('sessions.purpose')}
                    actions={schedule}
                />

                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">
                        {t('sessions.coming')}
                    </h2>
                    {coming.length === 0 ? (
                        <EmptyState
                            icon={Video}
                            title={t('sessions.empty')}
                            description={
                                canHost
                                    ? t('sessions.emptyHost')
                                    : t('sessions.emptyGuest')
                            }
                            action={schedule}
                        />
                    ) : (
                        [...days.entries()].map(([day, list]) => (
                            <div key={day} className="space-y-3">
                                <h3 className="text-base font-medium text-muted-foreground">
                                    {day}
                                </h3>
                                {list.map((session) => (
                                    <SessionCard
                                        key={session.id}
                                        session={session}
                                        me={me}
                                    />
                                ))}
                            </div>
                        ))
                    )}
                </section>

                {past.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            {t('sessions.past')}
                        </h2>
                        {past.map((session) => (
                            <SessionCard
                                key={session.id}
                                session={session}
                                me={me}
                            />
                        ))}
                    </section>
                ) : null}
            </div>
            {canHost ? (
                <ScheduleDialog
                    open={scheduling}
                    onOpenChange={setScheduling}
                    contacts={contacts}
                    lengths={lengths}
                />
            ) : null}
        </>
    );
}
