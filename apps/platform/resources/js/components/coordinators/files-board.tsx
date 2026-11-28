import {
    CalendarClock,
    FileWarning,
    FolderOpen,
    ListChecks,
} from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson, putJson } from '@/lib/content';
import type { FileCard, FileDetail, FilesBoardView } from './types';

const card = 'space-y-3 rounded-reos-lg border border-border bg-card p-5';

/** "Nov 27" in the reader's language. */
function useDateText() {
    const { i18n } = useTranslation();

    return (day: string): string =>
        new Date(`${day}T12:00:00`).toLocaleDateString(i18n.language, {
            month: 'short',
            day: 'numeric',
        });
}

function useStage() {
    const { t } = useTranslation();

    return (stage: string): string => {
        switch (stage) {
            case 'under_contract':
                return t('deals.stages.under_contract');
            case 'closed':
                return t('deals.stages.closed');
            case 'cancelled':
                return t('deals.stages.cancelled');
            default:
                return t('deals.stages.active');
        }
    };
}

function useDocStatus() {
    const { t } = useTranslation();

    return (status: string): string => {
        switch (status) {
            case 'complete':
                return t('deals.fileStatus.complete');
            case 'missing':
                return t('deals.fileStatus.missing');
            default:
                return t('deals.fileStatus.pending');
        }
    };
}

/**
 * My files (B17.1, provider screen 14): the coordinator's active files,
 * nearest deadline first, with the next date, missing documents and
 * progress. Only what each agent switched on.
 */
export function FilesBoard({
    board,
    href,
}: {
    board: FilesBoardView;
    href: (id: string) => string;
}) {
    const { t } = useTranslation();
    const dateText = useDateText();
    const stage = useStage();
    const counts = [
        {
            icon: FolderOpen,
            label: t('coordinators.counts.active'),
            value: board.counts.active,
        },
        {
            icon: CalendarClock,
            label: t('coordinators.counts.dueThisWeek'),
            value: board.counts.due_this_week,
        },
        {
            icon: FileWarning,
            label: t('coordinators.counts.missing'),
            value: board.counts.missing,
        },
    ];

    return (
        <div className="space-y-6">
            <ul className="grid gap-3 sm:grid-cols-3">
                {counts.map(({ icon: Icon, label, value }) => (
                    <li
                        key={label}
                        className="flex items-center gap-3 rounded-reos-lg border border-border bg-card p-4"
                    >
                        <Icon
                            aria-hidden
                            className="size-6 text-muted-foreground"
                        />
                        <div>
                            <p className="text-body text-muted-foreground">
                                {label}
                            </p>
                            <p className="text-2xl font-semibold">{value}</p>
                        </div>
                    </li>
                ))}
            </ul>

            {board.files.length === 0 ? (
                <EmptyState
                    icon={FolderOpen}
                    title={t('coordinators.empty')}
                    description={t('coordinators.emptyHint')}
                />
            ) : (
                <ul className="grid gap-4 lg:grid-cols-2">
                    {board.files.map((file: FileCard) => (
                        <li key={file.id} className={card}>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="text-lg font-semibold">
                                        {file.property}
                                    </p>
                                    <p className="text-body text-muted-foreground">
                                        {file.agent} · {stage(file.stage)}
                                    </p>
                                </div>
                            </div>
                            <p className="text-body">
                                {file.next === null
                                    ? t('coordinators.noNext')
                                    : t('coordinators.next', {
                                          label: file.next.label,
                                          date: dateText(file.next.due_on),
                                      })}
                            </p>
                            <p className="text-body text-muted-foreground">
                                {file.missing > 0
                                    ? `${t('coordinators.missing', { count: file.missing })} · `
                                    : ''}
                                {t('coordinators.progress', {
                                    done: file.progress.done,
                                    total: file.progress.total,
                                })}
                            </p>
                            <Button asChild variant="outline">
                                <a href={href(file.id)}>
                                    {t('coordinators.open', {
                                        property: file.property,
                                    })}
                                </a>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** Where a file's checklist and messages go. */
export type FileRoutes = {
    tick: string;
    message: string;
    chat: (conversation: string) => string;
};

/**
 * One file (B17.1, provider screen 15): the shared parts only, a checklist
 * the agent sees, and a drafted message to the agent.
 */
export function FilePanel({
    file,
    routes,
}: {
    file: FileDetail;
    routes: FileRoutes;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const dateText = useDateText();
    const docStatus = useDocStatus();
    const [dates, setDates] = useState(file.dates);
    const [documents, setDocuments] = useState(file.documents);
    const [body, setBody] = useState(file.draft);
    const [chat, setChat] = useState<string | null>(file.conversation);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const tick = async (item: string, done: boolean, apply: () => void) => {
        setError(null);
        try {
            await putJson(routes.tick, { item, done });
            apply();
            toast.success(t('coordinators.ticked', { agent: file.agent }));
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const notShared = (
        <p className="text-body text-muted-foreground">
            {t('coordinators.notShared', { agent: file.agent })}
        </p>
    );

    return (
        <div className="space-y-6">
            <p className="text-body text-muted-foreground">
                {t('coordinators.sharedBy', {
                    agent: file.agent,
                    date: dateText(file.shared_on),
                })}
            </p>

            <section aria-labelledby={`${ids}-dates`} className={card}>
                <h2
                    id={`${ids}-dates`}
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <CalendarClock aria-hidden className="size-5" />
                    {t('coordinators.dates')}
                </h2>
                {dates === null ? (
                    notShared
                ) : (
                    <ul className="space-y-1">
                        {dates.map((d) => (
                            <li
                                key={d.id}
                                className="flex min-h-12 items-center gap-3"
                            >
                                <Checkbox
                                    id={`${ids}-d-${d.id}`}
                                    checked={d.done}
                                    onCheckedChange={(on) =>
                                        void tick(d.id, on === true, () =>
                                            setDates(
                                                dates.map((x) =>
                                                    x.id === d.id
                                                        ? {
                                                              ...x,
                                                              done: on === true,
                                                          }
                                                        : x,
                                                ),
                                            ),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor={`${ids}-d-${d.id}`}
                                    className="text-base"
                                >
                                    {d.label} · {dateText(d.due_on)}
                                </Label>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby={`${ids}-docs`} className={card}>
                <h2
                    id={`${ids}-docs`}
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <ListChecks aria-hidden className="size-5" />
                    {t('coordinators.documents')}
                </h2>
                {documents === null ? (
                    notShared
                ) : (
                    <ul className="space-y-1">
                        {documents.map((d) => (
                            <li
                                key={d.name}
                                className="flex min-h-12 items-center gap-3"
                            >
                                <Checkbox
                                    id={`${ids}-doc-${d.name}`}
                                    checked={d.done}
                                    disabled={d.status === 'complete'}
                                    onCheckedChange={(on) =>
                                        void tick(
                                            `doc:${d.name}`,
                                            on === true,
                                            () =>
                                                setDocuments(
                                                    documents.map((x) =>
                                                        x.name === d.name
                                                            ? {
                                                                  ...x,
                                                                  done:
                                                                      on ===
                                                                      true,
                                                              }
                                                            : x,
                                                    ),
                                                ),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor={`${ids}-doc-${d.name}`}
                                    className="text-base"
                                >
                                    {d.name} · {docStatus(d.status)}
                                </Label>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby={`${ids}-parties`} className={card}>
                <h2 id={`${ids}-parties`} className="text-lg font-semibold">
                    {t('coordinators.parties')}
                </h2>
                {file.parties === null ? (
                    notShared
                ) : (
                    <ul className="space-y-1">
                        {file.parties.map((p) => (
                            <li
                                key={`${p.name}-${p.role}`}
                                className="text-body"
                            >
                                {p.name} · {p.role}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby={`${ids}-message`} className={card}>
                <h2 id={`${ids}-message`} className="text-lg font-semibold">
                    {t('coordinators.message', { agent: file.agent })}
                </h2>
                {file.switches.messages ? (
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setBusy(true);
                            setError(null);
                            postJson<{ data: { conversation: string } }>(
                                routes.message,
                                { body },
                            )
                                .then((r) => {
                                    setChat(r.data.conversation);
                                    toast.success(
                                        t('coordinators.sent', {
                                            agent: file.agent,
                                        }),
                                    );
                                })
                                .catch((err: unknown) =>
                                    setError(
                                        err instanceof Error
                                            ? err.message
                                            : String(err),
                                    ),
                                )
                                .finally(() => setBusy(false));
                        }}
                    >
                        <p
                            id={`${ids}-hint`}
                            className="text-body text-muted-foreground"
                        >
                            {t('coordinators.messageHint', {
                                agent: file.agent,
                            })}
                        </p>
                        <Textarea
                            aria-labelledby={`${ids}-message`}
                            aria-describedby={`${ids}-hint`}
                            rows={5}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                        />
                        <div className="flex flex-wrap gap-3">
                            <Button
                                type="submit"
                                disabled={busy || body.trim().length < 2}
                            >
                                {t('coordinators.send')}
                            </Button>
                            {chat !== null ? (
                                <Button asChild variant="outline">
                                    <a href={routes.chat(chat)}>
                                        {t('coordinators.openChat')}
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                    </form>
                ) : (
                    <p className="text-body text-muted-foreground">
                        {t('coordinators.messagesOff', { agent: file.agent })}
                    </p>
                )}
                <InputError message={error ?? undefined} />
            </section>
        </div>
    );
}
