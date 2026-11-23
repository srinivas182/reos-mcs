import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { draft as draftRoute, regenerate } from '@/routes/builder/lessons';
import type { LessonDraft } from './types';
import { statusTone } from './types';

const th = 'px-3 py-2 text-left align-top';
const td = 'px-3 py-3 align-top';

/**
 * An AI lesson's draft (A7.3, screen 43): the script line by line in English
 * and Spanish, side by side, each line with its source, the legal flags
 * that stop rendering, the two videos, and what waits for review (A8.1).
 */
export function LessonDraftView({ draft }: { draft: LessonDraft }) {
    const { t } = useTranslation();
    const flagged = (n: number) => draft.flags.filter((f) => f.line === n);

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-2 text-base">
                <span
                    className={cn(
                        'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                        statusTone(draft.status),
                    )}
                >
                    {t(`builder.ai.statuses.${draft.status}`)}
                </span>
                <span className="text-muted-foreground">
                    {t('builder.ai.version', {
                        version: draft.version,
                        count: draft.versions,
                    })}
                </span>
                <span className="text-muted-foreground">
                    {t(`builder.settings.vendors.${draft.vendor}`)}
                </span>
                <span className="text-muted-foreground">
                    {t('builder.ai.credits', { credits: draft.credits })}
                </span>
            </div>
            {draft.failure ? (
                <p className="text-base font-medium text-destructive">
                    {draft.failure}
                </p>
            ) : null}
            {draft.flags.length > 0 ? (
                <p className="flex items-start gap-2 rounded-reos-md bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg">
                    <AlertTriangle aria-hidden className="mt-0.5 size-5" />
                    {t('builder.ai.flagged', { count: draft.flags.length })}
                </p>
            ) : null}

            {draft.renders.length > 0 ? (
                <section className="space-y-3">
                    <h3 className="text-lg font-semibold">
                        {t('builder.ai.videos')}
                    </h3>
                    <div className="grid gap-4 md:grid-cols-2">
                        {draft.renders.map((render) => (
                            <figure
                                key={render.language}
                                className="space-y-2 rounded-reos-md border border-border p-3"
                            >
                                <figcaption className="flex flex-wrap items-center gap-2 font-semibold">
                                    {t(
                                        `builder.settings.languages.${render.language}`,
                                    )}
                                    <span
                                        className={cn(
                                            'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                            statusTone(render.status),
                                        )}
                                    >
                                        {t(
                                            `builder.ai.renders.${render.status}`,
                                        )}
                                    </span>
                                </figcaption>
                                {render.url ? (
                                    <video
                                        controls
                                        preload="metadata"
                                        src={render.url}
                                        className="aspect-video w-full rounded-reos-md bg-muted"
                                        aria-label={`${t('builder.ai.videos')}: ${t(`builder.settings.languages.${render.language}`)}`}
                                    />
                                ) : null}
                                {render.simulated ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('builder.ai.simulated')}
                                    </p>
                                ) : null}
                                {render.failure ? (
                                    <p className="text-sm text-destructive">
                                        {render.failure}
                                    </p>
                                ) : null}
                            </figure>
                        ))}
                    </div>
                </section>
            ) : null}

            <section className="space-y-3">
                <h3 className="text-lg font-semibold">
                    {t('builder.ai.script')}
                </h3>
                <div className="overflow-x-auto rounded-reos-md border border-border">
                    <table className="w-full text-base">
                        <caption className="sr-only">
                            {t('builder.ai.script')}
                        </caption>
                        <thead className="bg-muted text-sm text-muted-foreground">
                            <tr>
                                <th scope="col" className={th}>
                                    #
                                </th>
                                <th scope="col" className={th}>
                                    {t('builder.settings.languages.en')}
                                </th>
                                <th scope="col" className={th}>
                                    {t('builder.settings.languages.es')}
                                </th>
                                <th scope="col" className={th}>
                                    {t('builder.ai.source')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {draft.lines.map((line) => {
                                const flags = flagged(line.n);

                                return (
                                    <tr
                                        key={line.n}
                                        className={cn(
                                            flags.length > 0 &&
                                                'bg-reos-tone-warning-bg',
                                        )}
                                    >
                                        <td
                                            className={cn(
                                                td,
                                                'text-muted-foreground',
                                            )}
                                        >
                                            {line.n + 1}
                                        </td>
                                        <td className={td}>{line.en}</td>
                                        <td className={td} lang="es">
                                            {line.es}
                                        </td>
                                        <td
                                            className={cn(
                                                td,
                                                'text-sm text-muted-foreground',
                                            )}
                                        >
                                            {line.page === null
                                                ? line.source
                                                : t('builder.ai.page', {
                                                      source: line.source,
                                                      page: line.page,
                                                  })}
                                            {flags.map((f) => (
                                                <span
                                                    key={`${f.rule}-${f.language}`}
                                                    className="mt-1 block font-semibold text-reos-tone-warning-fg"
                                                >
                                                    {t(
                                                        `content.checks.rules.${f.rule}`,
                                                    )}{' '}
                                                    (
                                                    {t(
                                                        `builder.settings.languages.${f.language}`,
                                                    )}
                                                    )
                                                </span>
                                            ))}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </section>

            <p className="text-base text-muted-foreground">
                {t('builder.ai.review', {
                    checkins: draft.review.checkins,
                    questions: draft.review.questions,
                    flashcards: draft.review.flashcards,
                })}
            </p>
        </div>
    );
}

/** Opens an AI lesson's draft from the lesson list; Generate again makes a new version. */
export function LessonDraftDialog({
    lessonId,
    onClose,
}: {
    lessonId: number | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState<LessonDraft | null>(null);

    useEffect(() => {
        if (lessonId === null) {
            return;
        }

        let live = true;
        void fetch(draftRoute(lessonId).url, {
            headers: { Accept: 'application/json' },
        })
            .then((r) =>
                r.ok ? (r.json() as Promise<{ data: LessonDraft }>) : null,
            )
            .then((body) => live && setDraft(body?.data ?? null));

        return () => {
            live = false;
            setDraft(null);
        };
    }, [lessonId]);

    const done =
        draft !== null && ['flagged', 'ready', 'failed'].includes(draft.status);

    return (
        <Dialog
            open={lessonId !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        {draft?.lesson.title ?? t('builder.ai.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('builder.ai.intro')}
                    </DialogDescription>
                </DialogHeader>
                {draft ? (
                    <LessonDraftView draft={draft} />
                ) : (
                    <p className="text-base text-muted-foreground">
                        {t('builder.ai.loading')}
                    </p>
                )}
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('builder.ai.close')}
                    </Button>
                    {draft && done && lessonId !== null ? (
                        <Button
                            type="button"
                            onClick={() =>
                                router.post(
                                    regenerate(lessonId).url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: onClose,
                                    },
                                )
                            }
                        >
                            {t('builder.ai.regenerate')}
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
