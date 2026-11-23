import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { BriefSection, BriefSettings } from './types';

/**
 * When the morning brief goes out and what it covers (A11.1c). The time is
 * in the browser's own time zone, sent with it.
 */
export function BriefSettingsForm({
    settings,
    sections,
    onSave,
}: {
    settings: BriefSettings;
    sections: BriefSection[];
    onSave: (settings: BriefSettings) => void;
}) {
    const { t } = useTranslation();
    const [value, setValue] = useState(settings);
    const toggle = (section: BriefSection, on: boolean) =>
        setValue((v) => ({
            ...v,
            sections: on
                ? sections.filter(
                      (s) => s === section || v.sections.includes(s),
                  )
                : v.sections.filter((s) => s !== section),
        }));

    return (
        <form
            aria-labelledby="brief-settings"
            className="space-y-5 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                onSave({
                    ...value,
                    timezone:
                        Intl.DateTimeFormat().resolvedOptions().timeZone ||
                        value.timezone,
                });
            }}
        >
            <h2 id="brief-settings" className="text-lg font-semibold">
                {t('agents.brief.settings.title')}
            </h2>
            <div className="flex items-start justify-between gap-4">
                <div className="space-y-1">
                    <Label htmlFor="brief-enabled" className="text-base">
                        {t('agents.brief.settings.enabled')}
                    </Label>
                    <p className="text-base text-muted-foreground">
                        {t('agents.brief.settings.enabledHint')}
                    </p>
                </div>
                <Switch
                    id="brief-enabled"
                    checked={value.enabled}
                    onCheckedChange={(enabled) =>
                        setValue((v) => ({ ...v, enabled }))
                    }
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="brief-at" className="text-base">
                    {t('agents.brief.settings.time')}
                </Label>
                <Input
                    id="brief-at"
                    type="time"
                    className="w-40"
                    value={value.at}
                    onChange={(e) =>
                        setValue((v) => ({ ...v, at: e.target.value }))
                    }
                />
            </div>
            <fieldset className="space-y-3">
                <legend className="text-base font-medium">
                    {t('agents.brief.settings.sections')}
                </legend>
                {sections.map((section) => (
                    <div
                        key={section}
                        className="flex min-h-12 items-center gap-3"
                    >
                        <Checkbox
                            id={`brief-${section}`}
                            checked={value.sections.includes(section)}
                            onCheckedChange={(on) =>
                                toggle(section, on === true)
                            }
                        />
                        <Label
                            htmlFor={`brief-${section}`}
                            className="text-base font-normal"
                        >
                            {t(`agents.brief.settings.names.${section}`)}
                        </Label>
                    </div>
                ))}
            </fieldset>
            <p className="text-base text-muted-foreground">
                {t('agents.brief.settings.privacy')}
            </p>
            <Button type="submit">{t('agents.brief.settings.save')}</Button>
        </form>
    );
}
