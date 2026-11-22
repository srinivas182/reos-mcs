import '@livekit/components-styles';
import {
    ControlBar,
    GridLayout,
    LiveKitRoom,
    ParticipantTile,
    RoomAudioRenderer,
    useDataChannel,
    useTracks,
} from '@livekit/components-react';
import { Track } from 'livekit-client';
import { Circle, Clock, PenLine, Video } from 'lucide-react';
import { lazy, Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { RoomConnection, Session } from '@/components/sessions/types';
import {
    type BoardChannel,
    decodeBoard,
    encodeBoard,
} from '@/components/sessions/board-channel';
import {
    activeRecording,
    RecordingBanner,
} from '@/components/sessions/recording';
import { ShareActions } from '@/components/sessions/share-actions';
import { clock, useCountdown } from '@/components/sessions/use-countdown';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import { store as recordRoute } from '@/routes/sessions/recordings';

// Excalidraw is large; it loads only when someone opens the whiteboard.
const Whiteboard = lazy(() => import('@/components/sessions/whiteboard'));

function BoardLoading() {
    const { t } = useTranslation();

    return (
        <p className="text-base text-muted-foreground">
            {t('sessions.whiteboard.loading')}
        </p>
    );
}

/** The whiteboard in a real room: changes travel on the room's data channel. */
function LiveBoard({ sessionId }: { sessionId: string }) {
    const listeners = useRef(new Set<(elements: unknown[]) => void>());
    const { send } = useDataChannel('whiteboard', (message) => {
        const elements = decodeBoard(message.payload);

        if (elements) {
            listeners.current.forEach((listener) => listener(elements));
        }
    });
    const channel = useMemo<BoardChannel>(
        () => ({
            send: (elements) =>
                void send(encodeBoard(elements), { reliable: true }),
            receive: (listener) => {
                listeners.current.add(listener);

                return () => listeners.current.delete(listener);
            },
        }),
        [send],
    );

    return (
        <Suspense fallback={<BoardLoading />}>
            <Whiteboard sessionId={sessionId} channel={channel} />
        </Suspense>
    );
}

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
    onShared = () => undefined,
}: {
    session: Session;
    connection: RoomConnection;
    me: number;
    onLeave: (reason: 'left' | 'ended') => void;
    onEnd: () => void;
    /** The host shared a lesson or questions (reload the session). */
    onShared?: () => void;
}) {
    const { t, i18n } = useTranslation();
    const [view, setView] = useState<'video' | 'board'>('video');
    const recording = activeRecording(session);
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
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={view}
                    aria-label={t('sessions.room.view')}
                    onValueChange={(next) =>
                        next ? setView(next as 'video' | 'board') : undefined
                    }
                >
                    <ToggleGroupItem value="video" className="min-h-12 px-4">
                        <Video aria-hidden />
                        {t('sessions.room.video')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="board" className="min-h-12 px-4">
                        <PenLine aria-hidden />
                        {t('sessions.whiteboard.title')}
                    </ToggleGroupItem>
                </ToggleGroup>
                <div className="flex flex-wrap gap-2">
                    {session.me === 'host' ? (
                        <ShareActions
                            sessionId={session.id}
                            onShared={onShared}
                        />
                    ) : null}
                    {session.me === 'host' && recording === null ? (
                        <Button
                            variant="outline"
                            onClick={() =>
                                void postJson(recordRoute(session.id).url)
                                    .then(onShared)
                                    .catch(onShared)
                            }
                        >
                            <Circle aria-hidden className="fill-current" />
                            {t('sessions.recording.record')}
                        </Button>
                    ) : null}
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

            {recording ? (
                <RecordingBanner
                    session={session}
                    recording={recording}
                    onChanged={onShared}
                />
            ) : null}

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
                view === 'board' ? (
                    <Suspense fallback={<BoardLoading />}>
                        <Whiteboard sessionId={session.id} channel={null} />
                    </Suspense>
                ) : (
                    <SimulatedStage session={session} me={me} />
                )
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
                    {view === 'board' ? (
                        <LiveBoard sessionId={session.id} />
                    ) : (
                        <Stage />
                    )}
                    <RoomAudioRenderer />
                </LiveKitRoom>
            )}
        </section>
    );
}
