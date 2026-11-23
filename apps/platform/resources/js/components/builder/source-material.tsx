import { router } from '@inertiajs/react';
import { Trash2, Upload } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { fileSize, postJson, put } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { cn } from '@/lib/utils';
import { complete, destroy, store } from '@/routes/builder/sources';
import type { RoleRule, SourceRole, SourceRow } from './types';
import { statusTone } from './types';

type Uploading = {
    key: string;
    name: string;
    progress: number;
    error: string | null;
};

/**
 * A module's source material (screen 43, A7.2): scripts, slides, forms and
 * recorded classes, uploaded straight to storage with a signed link, then
 * checked for viruses and read.
 */
export function SourceMaterial({
    moduleId,
    sources,
    roles,
}: {
    moduleId: number;
    sources: SourceRow[];
    roles: RoleRule[];
}) {
    const { t, i18n } = useTranslation();
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const [role, setRole] = useState<SourceRole>('script');
    const [uploading, setUploading] = useState<Uploading[]>([]);
    const rule = roles.find((r) => r.role === role);

    const update = (key: string, change: Partial<Uploading>) =>
        setUploading((list) =>
            list.map((u) => (u.key === key ? { ...u, ...change } : u)),
        );

    const send = async (file: File) => {
        const key = `${file.name}-${file.size}-${uploading.length}`;
        setUploading((list) => [
            ...list,
            { key, name: file.name, progress: 0, error: null },
        ]);

        try {
            const started = await postJson<{
                id: number;
                upload: { url: string; headers: Record<string, string> };
            }>(store(moduleId).url, { role, name: file.name, size: file.size });
            await put(started.upload, file, (progress) =>
                update(key, { progress }),
            );
            await postJson(complete(started.id).url);
            setUploading((list) => list.filter((u) => u.key !== key));
            router.reload({ only: ['module', 'modules'] });
        } catch (error) {
            update(key, {
                error:
                    error instanceof Error && !error.message.startsWith('HTTP')
                        ? error.message
                        : t('builder.sources.uploadFailed'),
            });
        }
    };

    const detail = (source: SourceRow): string => {
        if (source.status !== 'ready') {
            return (
                source.failure ?? t(`builder.sources.statuses.${source.status}`)
            );
        }

        if (source.role === 'recording') {
            return t('builder.sources.recordingReady');
        }

        return source.pages
            ? t('builder.sources.readPages', {
                  pages: source.pages,
                  words: (source.words ?? 0).toLocaleString(i18n.language),
              })
            : t('builder.sources.read', {
                  words: (source.words ?? 0).toLocaleString(i18n.language),
              });
    };

    return (
        <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            <div className="space-y-1">
                <h2 className="text-lg font-semibold">
                    {t('builder.sources.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('builder.sources.intro')}
                </p>
            </div>

            {sources.length === 0 && uploading.length === 0 ? (
                <EmptyState
                    icon={Upload}
                    title={t('builder.sources.emptyTitle')}
                    description={t('builder.sources.empty')}
                />
            ) : (
                <ul className="divide-y divide-border">
                    {sources.map((source) => (
                        <li
                            key={source.id}
                            className="flex flex-wrap items-center gap-3 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold break-all">
                                    {source.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {t(`builder.sources.roles.${source.role}`)}{' '}
                                    · {fileSize(source.size, i18n.language)} ·{' '}
                                    {detail(source)}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                    statusTone(source.status),
                                )}
                            >
                                {t(`builder.sources.statuses.${source.status}`)}
                            </span>
                            <Button
                                variant="ghost"
                                aria-label={t('builder.sources.removeLabel', {
                                    name: source.name,
                                })}
                                onClick={() =>
                                    router.delete(destroy(source.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        </li>
                    ))}
                    {uploading.map((u) => (
                        <li key={u.key} className="py-3">
                            <p className="font-semibold break-all">{u.name}</p>
                            {u.error ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {u.error}
                                </p>
                            ) : (
                                <progress
                                    className="w-full"
                                    value={u.progress}
                                    max={1}
                                    aria-label={u.name}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <div className="grid gap-2">
                    <Label htmlFor={`${id}-role`}>
                        {t('builder.sources.role')}
                    </Label>
                    <select
                        id={`${id}-role`}
                        className={nativeSelect}
                        value={role}
                        onChange={(e) => setRole(e.target.value as SourceRole)}
                    >
                        {roles.map((r) => (
                            <option key={r.role} value={r.role}>
                                {t(`builder.sources.roles.${r.role}`)}
                            </option>
                        ))}
                    </select>
                </div>
                <input
                    ref={input}
                    type="file"
                    className="sr-only"
                    tabIndex={-1}
                    aria-hidden
                    accept={rule?.types.map((ext) => `.${ext}`).join(',')}
                    onChange={(e) => {
                        Array.from(e.target.files ?? []).forEach(
                            (file) => void send(file),
                        );
                        e.target.value = '';
                    }}
                />
                <Button onClick={() => input.current?.click()}>
                    <Upload aria-hidden />
                    {t('builder.sources.upload')}
                </Button>
                {rule ? (
                    <p className="text-sm text-muted-foreground">
                        {t('builder.sources.accepts', {
                            types: rule.types.join(', ').toUpperCase(),
                            size: rule.max_mb,
                        })}
                    </p>
                ) : null}
            </div>
        </section>
    );
}
