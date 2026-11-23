import { router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { sessionLimits } from '@/routes/school/instructors';

export type SessionLimits = {
    max_30_cents: number | null;
    max_60_cents: number | null;
};

const dollars = (cents: number | null) =>
    cents === null ? '' : String(cents / 100);
const cents = (text: string) =>
    text.trim() === '' ? null : Math.round(Number(text) * 100);

/**
 * The school's limit on 1-on-1 session prices (A10.2d, sprint decision 3;
 * school admin screen 52). Empty means no limit; prices above a new limit
 * come down at once.
 */
export function SessionLimitsCard({ limits }: { limits: SessionLimits }) {
    const { t } = useTranslation();
    const [max30, setMax30] = useState(dollars(limits.max_30_cents));
    const [max60, setMax60] = useState(dollars(limits.max_60_cents));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    return (
        <section
            aria-labelledby="session-limits"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h2 id="session-limits" className="text-lg font-semibold">
                    {t('instructors.sessionLimits.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('instructors.sessionLimits.description')}
                </p>
            </div>
            <form
                className="flex flex-wrap items-end gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.put(
                        sessionLimits().url,
                        {
                            max_30_cents: cents(max30),
                            max_60_cents: cents(max60),
                        },
                        {
                            preserveScroll: true,
                            onStart: () => setBusy(true),
                            onFinish: () => setBusy(false),
                            onError: setErrors,
                        },
                    );
                }}
            >
                {(
                    [
                        [30, max30, setMax30, 'max_30_cents'],
                        [60, max60, setMax60, 'max_60_cents'],
                    ] as const
                ).map(([minutes, value, setValue, key]) => (
                    <div key={key} className="grid w-44 gap-2">
                        <Label htmlFor={`limit-${minutes}`}>
                            {t('instructors.sessionLimits.max', {
                                count: minutes,
                            })}
                        </Label>
                        <Input
                            id={`limit-${minutes}`}
                            type="number"
                            min={0}
                            step={1}
                            placeholder={t('instructors.sessionLimits.none')}
                            value={value}
                            onChange={(e) => setValue(e.target.value)}
                        />
                        <InputError message={errors[key]} />
                    </div>
                ))}
                <Button type="submit" disabled={busy}>
                    <Save aria-hidden />
                    {t('instructors.sessionLimits.save')}
                </Button>
            </form>
        </section>
    );
}
