import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import { settings as settingsRoute } from '@/routes/builder';
import type { BuilderSettings } from './types';

/** The course's settings for AI lessons (screen 43): language, lesson length and presenter. */
export function BuilderSettingsCard({
    courseId,
    settings,
    presenters,
}: {
    courseId: number;
    settings: BuilderSettings;
    presenters: { value: string; label: string }[];
}) {
    const { t } = useTranslation();
    const id = useId();
    const [draft, setDraft] = useState(settings);

    return (
        <form
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(settingsRoute(courseId).url, draft, {
                    preserveScroll: true,
                });
            }}
        >
            <h2 className="text-lg font-semibold">
                {t('builder.settings.title')}
            </h2>
            <div className="grid gap-2">
                <Label htmlFor={`${id}-language`}>
                    {t('builder.settings.language')}
                </Label>
                <select
                    id={`${id}-language`}
                    className={nativeSelect}
                    value={draft.language}
                    onChange={(e) =>
                        setDraft({
                            ...draft,
                            language: e.target
                                .value as BuilderSettings['language'],
                        })
                    }
                >
                    {(['both', 'en', 'es'] as const).map((l) => (
                        <option key={l} value={l}>
                            {t(`builder.settings.languages.${l}`)}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${id}-minutes`}>
                    {t('builder.settings.lessonMinutes')}
                </Label>
                <Input
                    id={`${id}-minutes`}
                    type="number"
                    min={3}
                    max={30}
                    value={draft.lesson_minutes}
                    onChange={(e) =>
                        setDraft({
                            ...draft,
                            lesson_minutes: Number(e.target.value),
                        })
                    }
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${id}-presenter`}>
                    {t('builder.settings.presenter')}
                </Label>
                <select
                    id={`${id}-presenter`}
                    className={nativeSelect}
                    value={draft.presenter}
                    onChange={(e) =>
                        setDraft({ ...draft, presenter: e.target.value })
                    }
                >
                    <option value="stock">{t('builder.settings.stock')}</option>
                    {presenters.map((p) => (
                        <option key={p.value} value={p.value}>
                            {p.label}
                        </option>
                    ))}
                </select>
                <p className="text-sm text-muted-foreground">
                    {t('builder.settings.presenterHint')}
                </p>
            </div>
            <Button type="submit" variant="outline">
                {t('builder.settings.save')}
            </Button>
        </form>
    );
}
