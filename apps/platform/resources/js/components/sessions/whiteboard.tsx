import './excalidraw-assets';
import '@excalidraw/excalidraw/index.css';
import {
    CaptureUpdateAction,
    Excalidraw,
    exportToBlob,
    getSceneVersion,
    reconcileElements,
} from '@excalidraw/excalidraw';
import type { OrderedExcalidrawElement } from '@excalidraw/excalidraw/element/types';
import type {
    AppState,
    ExcalidrawImperativeAPI,
} from '@excalidraw/excalidraw/types';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { BoardChannel } from '@/components/sessions/board-channel';
import { whiteboard as loadRoute } from '@/routes/sessions';
import { save as saveRoute } from '@/routes/sessions/whiteboard';

type Remote = Parameters<typeof reconcileElements>[1];

/** Changes go to the others at most this often (ms). */
const SEND_EVERY = 150;
/** The board is saved with the session this long after the last change (ms). */
const SAVE_AFTER = 4000;
/** Elements per data-channel message, to stay under LiveKit's packet size. */
const CHUNK = 30;

function xsrf(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1] ?? '') : '';
}

/**
 * The shared whiteboard (B6.3b, instructor screen 15): Excalidraw, drawn
 * together live through the room's data channel (only what changed is
 * sent), and saved with the session as the drawing and a picture of it.
 * Someone joining late starts from the saved board.
 */
export default function Whiteboard({
    sessionId,
    channel,
    onSaved,
}: {
    sessionId: string;
    channel: BoardChannel | null;
    onSaved?: () => void;
}) {
    const { t, i18n } = useTranslation();
    const [api, setApi] = useState<ExcalidrawImperativeAPI | null>(null);
    const [initial, setInitial] = useState<OrderedExcalidrawElement[] | null>(
        null,
    );
    const sent = useRef(new Map<string, number>());
    const lastVersion = useRef(0);
    const sendTimer = useRef<number | undefined>(undefined);
    const saveTimer = useRef<number | undefined>(undefined);

    useEffect(() => {
        void fetch(loadRoute(sessionId).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((data: { elements: OrderedExcalidrawElement[] }) => {
                for (const el of data.elements) {
                    sent.current.set(el.id, el.version);
                }
                setInitial(data.elements);
            })
            .catch(() => setInitial([]));
    }, [sessionId]);

    const save = useCallback(async () => {
        if (!api) {
            return;
        }

        const elements = api.getSceneElementsIncludingDeleted();
        const form = new FormData();
        form.append('elements', JSON.stringify(elements));

        const visible = elements.filter((el) => !el.isDeleted);

        if (visible.length > 0) {
            const image = await exportToBlob({
                elements: visible,
                appState: { exportBackground: true },
                files: api.getFiles(),
                mimeType: 'image/png',
            });
            form.append('image', image, 'whiteboard.png');
        }

        await fetch(saveRoute(sessionId).url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
            body: form,
        });
        onSaved?.();
    }, [api, sessionId, onSaved]);

    // Changes from the others, merged by element version.
    useEffect(() => {
        if (!api || !channel) {
            return undefined;
        }

        return channel.receive((remote) => {
            const merged = reconcileElements(
                api.getSceneElementsIncludingDeleted(),
                remote as Remote,
                api.getAppState(),
            );

            for (const el of remote as OrderedExcalidrawElement[]) {
                sent.current.set(el.id, el.version);
            }

            api.updateScene({
                elements: merged,
                captureUpdate: CaptureUpdateAction.NEVER,
            });
        });
    }, [api, channel]);

    const changed = (elements: readonly OrderedExcalidrawElement[]) => {
        const version = getSceneVersion(elements);

        if (version === lastVersion.current) {
            return;
        }

        lastVersion.current = version;

        window.clearTimeout(saveTimer.current);
        saveTimer.current = window.setTimeout(() => void save(), SAVE_AFTER);

        if (!channel) {
            return;
        }

        window.clearTimeout(sendTimer.current);
        sendTimer.current = window.setTimeout(() => {
            const fresh = elements.filter(
                (el) => sent.current.get(el.id) !== el.version,
            );

            for (const el of fresh) {
                sent.current.set(el.id, el.version);
            }

            for (let i = 0; i < fresh.length; i += CHUNK) {
                channel.send(fresh.slice(i, i + CHUNK));
            }
        }, SEND_EVERY);
    };

    useEffect(
        () => () => {
            window.clearTimeout(sendTimer.current);
            window.clearTimeout(saveTimer.current);
        },
        [],
    );

    if (initial === null) {
        return (
            <p className="text-base text-muted-foreground">
                {t('sessions.whiteboard.loading')}
            </p>
        );
    }

    return (
        <div
            className="h-[60vh] min-h-80 overflow-hidden rounded-reos-lg border border-border"
            role="application"
            aria-label={t('sessions.whiteboard.label')}
        >
            <Excalidraw
                initialData={{ elements: initial }}
                excalidrawAPI={setApi}
                onChange={(
                    elements: readonly OrderedExcalidrawElement[],
                    _state: AppState,
                ) => changed(elements)}
                langCode={i18n.language.startsWith('es') ? 'es-ES' : 'en'}
                UIOptions={{
                    canvasActions: {
                        loadScene: false,
                        saveToActiveFile: false,
                        export: false,
                        saveAsImage: true,
                    },
                }}
            />
        </div>
    );
}
