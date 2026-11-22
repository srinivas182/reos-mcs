import { router } from '@inertiajs/react';
import { Circle, Square, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Recording, Session } from '@/components/sessions/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { answer, destroy, stop } from '@/routes/sessions/recordings';

/** The recording being asked for or made, if any. */
export function activeRecording(session: Session): Recording | null {
    return (
        session.recordings.find((r) =>
            ['asking', 'recording', 'stopping'].includes(r.status),
        ) ?? null
    );
}

/**
 * Recording in the room (B6.3c): the question to everyone in it (it starts
 * only when all agree; one "no" cancels), then the "Recording" sign with
 * Stop for anyone.
 */
export function RecordingBanner({
    session,
    recording,
    onChanged,
}: {
    session: Session;
    recording: Recording;
    onChanged: () => void;
}) {
    const { t } = useTranslation();
    const [error, setError] = useState<string | undefined>();
    const run = async (url: string, body: unknown = {}) => {
        try {
            await postJson(url, body);
            setError(undefined);
            onChanged();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    if (recording.status === 'recording' || recording.status === 'stopping') {
        return (
            <div
                role="status"
                className="flex flex-wrap items-center justify-between gap-3 rounded-reos-lg border border-reos-tone-danger-fg bg-reos-tone-danger-bg p-4 text-reos-tone-danger-fg"
            >
                <p className="flex items-center gap-2 text-base font-semibold">
                    <Circle aria-hidden className="size-4 fill-current" />
                    {recording.status === 'stopping'
                        ? t('sessions.recording.stopping')
                        : t('sessions.recording.recording')}
                </p>
                {recording.status === 'recording' ? (
                    <Button
                        variant="outline"
                        onClick={() =>
                            void run(
                                stop({
                                    session: session.id,
                                    recording: recording.id,
                                }).url,
                            )
                        }
                    >
                        <Square aria-hidden />
                        {t('sessions.recording.stop')}
                    </Button>
                ) : null}
            </div>
        );
    }

    return (
        <Alert role="alertdialog" aria-labelledby="recording-ask">
            <AlertTitle id="recording-ask" className="text-base">
                {t('sessions.recording.asking', {
                    name: recording.requested_by ?? '',
                })}
            </AlertTitle>
            <AlertDescription className="space-y-3 text-base">
                <p>{t('sessions.recording.askBody')}</p>
                {recording.waiting.length > 0 ? (
                    <p className="text-muted-foreground">
                        {t('sessions.recording.waiting', {
                            names: recording.waiting.join(', '),
                        })}
                    </p>
                ) : null}
                {recording.my_answer === null ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            onClick={() =>
                                void run(
                                    answer({
                                        session: session.id,
                                        recording: recording.id,
                                    }).url,
                                    { agreed: true },
                                )
                            }
                        >
                            {t('sessions.recording.agree')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                void run(
                                    answer({
                                        session: session.id,
                                        recording: recording.id,
                                    }).url,
                                    { agreed: false },
                                )
                            }
                        >
                            {t('sessions.recording.decline')}
                        </Button>
                    </div>
                ) : (
                    <Button
                        variant="outline"
                        onClick={() =>
                            void run(
                                stop({
                                    session: session.id,
                                    recording: recording.id,
                                }).url,
                            )
                        }
                    >
                        {t('sessions.recording.decline')}
                    </Button>
                )}
                {error ? (
                    <p className="text-reos-tone-danger-fg">{error}</p>
                ) : null}
            </AlertDescription>
        </Alert>
    );
}

/** Finished recordings (B6.3c): watch, when it's deleted, delete now. */
export function SessionRecordingsList({ session }: { session: Session }) {
    const { t, i18n } = useTranslation();
    const done = session.recordings.filter(
        (r) => r.status === 'ready' || r.status === 'failed',
    );

    if (done.length === 0) {
        return null;
    }

    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">
                {t('sessions.recording.title')}
            </h2>
            {done.map((recording) => (
                <article
                    key={recording.id}
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
                >
                    {recording.status === 'failed' ? (
                        <p className="text-base">
                            {t('sessions.recording.failed')}
                        </p>
                    ) : recording.play_url ? (
                        <video
                            controls
                            preload="metadata"
                            src={recording.play_url}
                            aria-label={t('sessions.recording.watch')}
                            className="w-full rounded-reos-md bg-reos-hero-bg"
                        >
                            <track kind="captions" />
                        </video>
                    ) : (
                        <p className="text-base text-muted-foreground">
                            {t('sessions.recording.simulated')}
                        </p>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {[
                                recording.duration_seconds
                                    ? t('sessions.recording.duration', {
                                          minutes: Math.max(
                                              1,
                                              Math.round(
                                                  recording.duration_seconds /
                                                      60,
                                              ),
                                          ),
                                      })
                                    : null,
                                recording.expires_at
                                    ? t('sessions.recording.expires', {
                                          date: new Date(
                                              recording.expires_at,
                                          ).toLocaleDateString(i18n.language, {
                                              dateStyle: 'medium',
                                          }),
                                      })
                                    : null,
                                recording.agreed.length > 0
                                    ? t('sessions.recording.agreedBy', {
                                          names: recording.agreed.join(', '),
                                      })
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.delete(
                                    destroy({
                                        session: session.id,
                                        recording: recording.id,
                                    }).url,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Trash2 aria-hidden />
                            {t('sessions.recording.delete')}
                        </Button>
                    </div>
                </article>
            ))}
        </section>
    );
}
