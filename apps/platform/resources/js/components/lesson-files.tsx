import { router } from '@inertiajs/react';
import {
    Captions as CaptionsIcon,
    FileText,
    Presentation,
    Trash2,
    Upload,
    Video,
} from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { KindRule, LessonFile, MediaKind } from '@/lib/content';
import { clock, fileSize, fileTone, postJson, put } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { cn } from '@/lib/utils';
import { complete, destroy, store } from '@/routes/content/files';

const icons = {
    video: Video,
    slides: Presentation,
    document: FileText,
    captions: CaptionsIcon,
};

type Uploading = {
    key: string;
    name: string;
    progress: number;
    error: string | null;
};

type Started = {
    id: number;
    upload: { method: string; url: string; headers: Record<string, string> };
};

/**
 * A lesson's files (A5.3): video, slides, documents and captions. A file
 * uploads straight to storage with a short-lived signed link, then is
 * scanned for viruses and processed (video streams from Mux; slides and
 * documents are read so their words are searchable). Each shows where it
 * is; removing one asks first, because the file is deleted.
 */
export function LessonFiles({
    lessonId,
    files,
    kinds,
}: {
    lessonId: number;
    files: LessonFile[];
    kinds: KindRule[];
}) {
    const { t, i18n } = useTranslation();
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const [kind, setKind] = useState<MediaKind>('video');
    const [uploading, setUploading] = useState<Uploading[]>([]);
    const [removing, setRemoving] = useState<LessonFile | null>(null);
    const rule = kinds.find((k) => k.kind === kind);

    const update = (key: string, change: Partial<Uploading>) =>
        setUploading((list) =>
            list.map((u) => (u.key === key ? { ...u, ...change } : u)),
        );

    const send = async (file: File) => {
        const key = `${file.name}-${Date.now()}`;
        setUploading((list) => [
            ...list,
            { key, name: file.name, progress: 0, error: null },
        ]);

        try {
            const started = await postJson<Started>(store(lessonId).url, {
                kind,
                name: file.name,
                size: file.size,
            });
            await put(started.upload, file, (progress) =>
                update(key, { progress }),
            );
            await postJson(complete(started.id).url);
            setUploading((list) => list.filter((u) => u.key !== key));
            router.reload({ only: ['files', 'lesson', 'knowledge'] });
        } catch (error) {
            update(key, {
                error:
                    error instanceof Error && !error.message.startsWith('HTTP')
                        ? error.message
                        : t('content.files.uploadFailed'),
            });
        }
    };

    return (
        <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            <div className="space-y-1">
                <h2 className="font-semibold">{t('content.files.title')}</h2>
                <p className="text-body text-muted-foreground">
                    {t('content.files.intro')}
                </p>
            </div>

            {files.length === 0 && uploading.length === 0 ? (
                <EmptyState
                    icon={Upload}
                    title={t('content.files.emptyTitle')}
                    description={t('content.files.empty')}
                />
            ) : (
                <ul className="divide-y divide-border">
                    {files.map((file) => {
                        const Icon = icons[file.kind];
                        const details = [
                            t(`content.kinds.${file.kind}`),
                            fileSize(file.size, i18n.language),
                            file.pages !== null
                                ? t('content.files.pages', {
                                      count: file.pages,
                                  })
                                : null,
                            file.duration_seconds !== null
                                ? clock(file.duration_seconds)
                                : null,
                            file.kind === 'video' && file.captions
                                ? t('content.files.captioned')
                                : null,
                        ].filter(Boolean);

                        return (
                            <li
                                key={file.id}
                                className="flex flex-wrap items-center gap-3 py-3"
                            >
                                <Icon
                                    aria-hidden
                                    className="size-5 shrink-0 text-muted-foreground"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {file.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {details.join(' · ')}
                                    </p>
                                    {file.failure ? (
                                        <p className="text-sm text-destructive">
                                            {t(file.failure)}
                                        </p>
                                    ) : null}
                                </div>
                                <span
                                    className={cn(
                                        'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                        fileTone[file.status],
                                    )}
                                >
                                    {t(`content.files.status.${file.status}`)}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('content.files.remove', {
                                        name: file.name,
                                    })}
                                    onClick={() => setRemoving(file)}
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </li>
                        );
                    })}
                    {uploading.map((upload) => (
                        <li key={upload.key} className="space-y-2 py-3">
                            <p className="font-medium">{upload.name}</p>
                            {upload.error ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {upload.error}
                                </p>
                            ) : (
                                <progress
                                    className="h-2 w-full accent-primary"
                                    max={1}
                                    value={upload.progress}
                                    aria-label={t('content.files.uploading', {
                                        name: upload.name,
                                    })}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <div className="grid gap-2">
                    <Label htmlFor={`${id}-kind`}>
                        {t('content.files.kind')}
                    </Label>
                    <select
                        id={`${id}-kind`}
                        className={cn(nativeSelect, 'w-56')}
                        value={kind}
                        onChange={(e) => setKind(e.target.value as MediaKind)}
                    >
                        {kinds.map((k) => (
                            <option key={k.kind} value={k.kind}>
                                {t(`content.kinds.${k.kind}`)}
                            </option>
                        ))}
                    </select>
                </div>
                <input
                    ref={input}
                    id={`${id}-file`}
                    type="file"
                    className="sr-only"
                    tabIndex={-1}
                    aria-label={t('content.files.add')}
                    accept={rule?.extensions.map((e) => `.${e}`).join(',')}
                    multiple
                    onChange={(e) => {
                        Array.from(e.target.files ?? []).forEach(
                            (file) => void send(file),
                        );
                        e.target.value = '';
                    }}
                />
                <Button onClick={() => input.current?.click()}>
                    <Upload aria-hidden />
                    {t('content.files.add')}
                </Button>
                {rule ? (
                    <p className="text-sm text-muted-foreground">
                        {t('content.files.accepts', {
                            types: rule.extensions
                                .map((e) => e.toUpperCase())
                                .join(', '),
                            size: fileSize(
                                rule.max_mb * 1024 * 1024,
                                i18n.language,
                            ),
                        })}
                    </p>
                ) : null}
            </div>

            <Dialog
                open={removing !== null}
                onOpenChange={(open) => (open ? null : setRemoving(null))}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('content.files.removeTitle', {
                                name: removing?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('content.files.removeBody')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRemoving(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (removing) {
                                    router.delete(destroy(removing.id).url, {
                                        preserveScroll: true,
                                        onFinish: () => setRemoving(null),
                                    });
                                }
                            }}
                        >
                            {t('content.files.removeConfirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
