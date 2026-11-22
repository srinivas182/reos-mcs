import '@livekit/components-styles';
import {
    ControlBar,
    GridLayout,
    LiveKitRoom,
    ParticipantTile,
    RoomAudioRenderer,
    useTracks,
} from '@livekit/components-react';
import { Track } from 'livekit-client';
import { Clock } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import type { RoomConnection, Session } from '@/components/sessions/types';
import { clock, useCountdown } from '@/components/sessions/use-countdown';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Everyone's camera (and a shared screen) in a grid, with mic, camera and share controls. */
function Stage() {
    const tracks = useTracks(
        [
            { source: Track.Source.Camera, withPlaceholder: true },
            { source: Track.Source.ScreenShare, withPlaceholder: false },
        ],
        { onlySubscribed: false },
    );

    return (
        <div className="space-y-3">
            <GridLayout
                tracks={tracks}
                className="h-[60vh] min-h-80 overflow-hidden rounded-reos-lg"
            >
                <ParticipantTile />
            </GridLayout>
            <ControlBar
                variation="minimal"
                controls={{
                    microphone: true,
                    camera: true,
                    screenShare: true,
                    leave: false,
                    chat: false,
                }}
            />
        </div>
    );
}

const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

/**
 * The room without a media server (outside production, until LiveKit is
 * set up): the same screen, rules and timer, with name tiles for video.
 */
export function SimulatedStage({
    session,
    me,
}: {
    session: Session;
    me: number;
}) {
    const { t } = useTranslation();
    const people = session.people.slice(0, 7);

    return (
        <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
                {t('sessions.room.simulated')}
            </p>
            <ul
                aria-label={t('sessions.room.people')}
                className={cn(
                    'grid min-h-80 gap-3',
                    people.length <= 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3',
                )}
            >
                {people.map((person) => (
                    <li
                        key={person.id}
                        className="flex aspect-video flex-col items-center justify-center gap-3 rounded-reos-lg bg-reos-hero-bg text-reos-hero-text"
                    >
                        <span
                            aria-hidden
                            className="flex size-20 items-center justify-center rounded-full bg-reos-action-tint text-title font-semibold text-foreground"
                        >
                            {initials(person.name)}
                        </span>
                        <span className="text-base font-medium">
                            {person.id === me
                                ? t('sessions.room.you', { name: person.name })
                                : person.name}
                            {person.role === 'host'
                                ? ` · ${t('sessions.host')}`
                                : ''}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * The session room (instructor screen 15): everyone's video, a timer with a
 * two-minute warning, Leave, and End for everyone (the host). At the booked
 * end the room closes for everyone, here and on the server.
 */
export function SessionRoom({
    session,
    connection,
    me,
    onLeave,
    onEnd,
}: {
    session: Session;
    connection: RoomConnection;
    me: number;
    onLeave: (reason: 'left' | 'ended') => void;
    onEnd: () => void;
}) {
    const { t, i18n } = useTranslation();
    const left = useCountdown(connection.ends_at);
    const warn = left > 0 && left <= 120;
    const closesAt = new Date(connection.ends_at).toLocaleTimeString(
        i18n.language,
        { hour: 'numeric', minute: '2-digit' },
    );

    useEffect(() => {
        if (left === 0) {
            onLeave('ended');
        }
    }, [left, onLeave]);

    return (
        <section aria-label={t('sessions.room.label')} className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-reos-lg border border-border bg-card p-4">
                <p className="flex items-center gap-2 text-base">
                    <Clock aria-hidden className="size-5" />
                    <span
                        role="timer"
                        aria-label={t('sessions.room.timeLeft', {
                            time: clock(left),
                        })}
                        className="font-semibold tabular-nums"
                    >
                        {clock(left)}
                    </span>
                    <span className="text-muted-foreground">
                        {t('sessions.room.left', { time: closesAt })}
                    </span>
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" onClick={() => onLeave('left')}>
                        {t('sessions.room.leave')}
                    </Button>
                    {session.me === 'host' ? (
                        <Button variant="destructive" onClick={onEnd}>
                            {t('sessions.room.end')}
                        </Button>
                    ) : null}
                </div>
            </div>

            <div aria-live="polite">
                {warn ? (
                    <Alert>
                        <AlertDescription className="text-base">
                            {t('sessions.room.twoMinutes', { time: closesAt })}
                        </AlertDescription>
                    </Alert>
                ) : null}
            </div>

            {connection.simulated || !connection.url || !connection.token ? (
                <SimulatedStage session={session} me={me} />
            ) : (
                <LiveKitRoom
                    serverUrl={connection.url}
                    token={connection.token}
                    connect
                    audio
                    video
                    data-lk-theme="default"
                    onDisconnected={() => onLeave('left')}
                >
                    <Stage />
                    <RoomAudioRenderer />
                </LiveKitRoom>
            )}
        </section>
    );
}
