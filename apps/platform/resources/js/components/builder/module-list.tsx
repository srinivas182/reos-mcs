import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { show } from '@/routes/builder';
import { store } from '@/routes/builder/modules';
import type { ModuleRow } from './types';

/** The module list with status (screen 43), and Add module. */
export function ModuleList({
    courseId,
    modules,
    current,
}: {
    courseId: number;
    modules: ModuleRow[];
    current: number | null;
}) {
    const { t } = useTranslation();
    const [title, setTitle] = useState('');

    return (
        <nav
            aria-label={t('builder.modules')}
            className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
        >
            <h2 className="text-lg font-semibold">{t('builder.modules')}</h2>
            <ul className="space-y-1">
                {modules.map((module) => (
                    <li key={module.id}>
                        <Link
                            href={
                                show(courseId, { query: { module: module.id } })
                                    .url
                            }
                            aria-current={
                                module.id === current ? 'page' : undefined
                            }
                            preserveScroll
                            className={cn(
                                'block min-h-12 rounded-reos-md px-3 py-2 hover:bg-muted',
                                module.id === current &&
                                    'bg-reos-tone-info-bg text-reos-tone-info-fg',
                            )}
                        >
                            <span className="block font-semibold">
                                {module.title}
                            </span>
                            <span className="block text-sm">
                                {t(`builder.moduleStatus.${module.status}`)}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
            <form
                className="space-y-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.post(
                        store(courseId).url,
                        { title },
                        { onSuccess: () => setTitle('') },
                    );
                }}
            >
                <Label htmlFor="new-module">{t('builder.moduleTitle')}</Label>
                <Input
                    id="new-module"
                    value={title}
                    maxLength={160}
                    onChange={(e) => setTitle(e.target.value)}
                />
                <Button
                    type="submit"
                    variant="outline"
                    className="w-full"
                    disabled={title.trim().length < 2}
                >
                    {t('builder.addModule')}
                </Button>
            </form>
        </nav>
    );
}
