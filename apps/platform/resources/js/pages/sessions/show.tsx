import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { ArrowLeft, Circle } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SessionStatus, sessionTime } from '@/components/sessions/session-card';
import { SessionNotes } from '@/components/sessions/session-notes';
import {
    activeRecording,
    SessionRecordingsList,
} from '@/components/sessions/recording';
import { SessionRoom } from '@/components/sessions/session-room';
import { SessionShares } from '@/components/sessions/session-shares';
import type { RoomConnection, Session } from '@/components/sessions/types';
import { withWhom } from '@/components/sessions/types';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { realtimeEnabled } from '@/lib/realtime';
import {
    cancel,
    end,
    index,
    join,
    leave as leaveRoute,
} from '@/routes/sessions';
import { answer } from '@/routes/sessions/recordings';

/** Closes the room at once when the session ends elsewhere (the host, or the booked end). */
function LiveEnd({
    me,
    session,
    onEnded,
    onShared,
}: {
    me: number;
    session: string;
    onEnded: () => void;
    onShared: () => void;
}) {
    useEcho<{ session: string }>(
        `users.${me}`,
        '.session.ended',
        (event) => {
            if (event.session === session) {
                onEnded();
            }
        },
        [session],
    );
    // The host shared a lesson or practice questions (B6.3b), or recording changed (B6.3c).
    useEcho<{ session: string }>(
        `users.${me}`,
        ['.session.shared', '.session.recording'],
        (event) => {
            if (event.session === session) {
                onShared();
            }
        },
        [session],
    );

    return null;
}

/**
 * A session (B6.3): before it opens, when and with whom, and Join from five
 * minutes before; inside, the room (instructor screen 15); afterwards, the
 * session notes (screen 16) everyone in it shares.
 */
export default function SessionPage({
    session,
}: {
    session: Session;
    simulated: boolean;
}) {
    const { t, i18n } = useTranslation();
    const { auth } = usePage().props;
    const me = auth.user?.id ?? 0;
    const [connection, setConnection] = useState<RoomConnection | null>(null);
    const [joining, setJoining] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const others = withWhom(session, me);
    const over = session.status === 'ended' || session.status === 'cancelled';

    const leave = useCallback(() => {
        setConnection(null);
        // Out of the room: recording no longer waits for this person (B6.3c).
        void postJson(leaveRoute(session.id).url).catch(() => undefined);
        router.reload({ only: ['session'] });
    }, [session.id]);
    const recording = activeRecording(session);
    // Joining while it records: agree first (B6.3c).
    const mustAgree =
        recording !== null &&
        recording.status === 'recording' &&
        recording.my_answer !== true;
    const refresh = useCallback(() => router.reload({ only: ['session'] }), []);

    const enter = async () => {
        setJoining(true);
        setError(undefined);

        try {
            setConnection(await postJson<RoomConnection>(join(session.id).url));
            // The session is live now.
            router.reload({ only: ['session'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
            // A recording may have started meanwhile: show its prompt.
            refresh();
        } finally {
            setJoining(false);
        }
    };

    const opens = new Date(session.join_opens_at).toLocaleTimeString(
        i18n.language,
        { hour: 'numeric', minute: '2-digit' },
    );

    return (
        <>
            <Head title={session.title} />
            {realtimeEnabled() && me > 0 ? (
                <LiveEnd
                    me={me}
                    session={session.id}
                    onEnded={leave}
                    onShared={refresh}
                />
            ) : null}
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    back={
                        <Link
                            href={index().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('sessions.title')}
                        </Link>
                    }
                    title={session.title}
                    description={`${sessionTime(session, i18n.language)} · ${
                        session.kind === 'live_class'
                            ? t('sessions.people', { count: others.length })
                            : t('sessions.with', { names: others.join(', ') })
                    }`}
                    actions={
                        <>
                            <SessionStatus status={session.status} />
                            {session.me === 'host' &&
                            session.status === 'scheduled' &&
                            !connection ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(cancel(session.id).url)
                                    }
                                >
                                    {t('sessions.cancel')}
                                </Button>
                            ) : null}
                        </>
                    }
                />

                {connection ? (
                    <SessionRoom
                        session={session}
                        connection={connection}
                        me={me}
                        onLeave={leave}
                        onShared={refresh}
                        onEnd={() => {
                            setConnection(null);
                            router.post(end(session.id).url);
                        }}
                    />
                ) : over ? null : (
                    <section className="space-y-4 rounded-reos-lg border border-border bg-card p-6">
                        {session.agenda ? (
                            <div className="space-y-1">
                                <h2 className="text-base font-semibold">
                                    {t('sessions.agenda')}
                                </h2>
                                <p className="text-base whitespace-pre-line">
                                    {session.agenda}
                                </p>
                            </div>
                        ) : null}
                        <p className="text-base text-muted-foreground">
                            {session.can_join
                                ? t('sessions.ready')
                                : t('sessions.opensNote', { time: opens })}
                        </p>
                        {mustAgree && session.can_join && recording ? (
                            <div className="space-y-3 rounded-reos-md border border-reos-tone-danger-fg bg-reos-tone-danger-bg p-4 text-reos-tone-danger-fg">
                                <h2 className="flex items-center gap-2 text-base font-semibold">
                                    <Circle
                                        aria-hidden
                                        className="size-4 fill-current"
                                    />
                                    {t('sessions.recording.joinFirst')}
                                </h2>
                                <p className="text-base">
                                    {t('sessions.recording.joinBody')}
                                </p>
                            </div>
                        ) : null}
                        <Button
                            size="lg"
                            disabled={!session.can_join || joining}
                            onClick={() =>
                                void (mustAgree && recording
                                    ? postJson(
                                          answer({
                                              session: session.id,
                                              recording: recording.id,
                                          }).url,
                                          { agreed: true },
                                      ).then(enter)
                                    : enter())
                            }
                        >
                            {!session.can_join
                                ? t('sessions.opensAt', { time: opens })
                                : mustAgree
                                  ? t('sessions.recording.agreeAndJoin')
                                  : t('sessions.join')}
                        </Button>
                        <InputError message={error} />
                    </section>
                )}

                {session.status === 'cancelled' ? (
                    <p className="text-base text-muted-foreground">
                        {t('sessions.wasCancelled')}
                    </p>
                ) : null}

                <SessionShares shares={session.shares} />

                {!connection ? (
                    <SessionRecordingsList session={session} />
                ) : null}

                {!connection && session.whiteboard.image_url ? (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            {t('sessions.whiteboard.title')}
                        </h2>
                        <img
                            src={session.whiteboard.image_url}
                            alt={t('sessions.whiteboard.picture', {
                                title: session.title,
                            })}
                            className="max-h-[60vh] w-full rounded-reos-lg border border-border bg-card object-contain"
                        />
                    </section>
                ) : null}

                <SessionNotes session={session} />
            </div>
        </>
    );
}
