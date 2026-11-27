import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import type { GoalKey, Mode, Rates } from '@/components/rewards/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/points';

/**
 * Points (A13.3a, super admin): what each daily goal earns, the bonus for
 * every goal, the daily cap and REOS's targets for each mode.
 */
export default function PointRatesPage({
    rates,
    goals,
    modes,
}: {
    rates: Rates;
    goals: GoalKey[];
    modes: Mode[];
}) {
    const { t } = useTranslation();
    const [v, setV] = useState<Rates>(rates);
    const [busy, setBusy] = useState(false);
    const number = (value: string) => Math.max(0, Number(value) || 0);

    return (
        <>
            <Head title={t('rewards.admin.title')} />
            <form
                className="space-y-8 px-4 py-6 md:px-8"
                onSubmit={(e) => {
                    e.preventDefault();
                    setBusy(true);
                    router.put(update().url, v, {
                        preserveScroll: true,
                        onFinish: () => setBusy(false),
                    });
                }}
            >
                <PageHeader
                    title={t('rewards.admin.title')}
                    description={t('rewards.admin.purpose')}
                    actions={
                        <Button type="submit" disabled={busy}>
                            {t('rewards.admin.save')}
                        </Button>
                    }
                />
                <p className="text-base text-muted-foreground">
                    {t('rewards.admin.respa')}
                </p>
                <section
                    aria-labelledby="points-title"
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                >
                    <h2 id="points-title" className="text-lg font-semibold">
                        {t('rewards.admin.points')}
                    </h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        {goals.map((g) => (
                            <div key={g} className="space-y-2">
                                <Label htmlFor={`points-${g}`}>
                                    {t(`rewards.goals.${g}`)}
                                </Label>
                                <Input
                                    id={`points-${g}`}
                                    type="number"
                                    min={0}
                                    max={1000}
                                    value={v.points[g]}
                                    onChange={(e) =>
                                        setV({
                                            ...v,
                                            points: {
                                                ...v.points,
                                                [g]: number(e.target.value),
                                            },
                                        })
                                    }
                                />
                            </div>
                        ))}
                        <div className="space-y-2">
                            <Label htmlFor="points-all">
                                {t('rewards.admin.allDone')}
                            </Label>
                            <Input
                                id="points-all"
                                type="number"
                                min={0}
                                max={1000}
                                value={v.all_done}
                                onChange={(e) =>
                                    setV({
                                        ...v,
                                        all_done: number(e.target.value),
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="points-cap">
                                {t('rewards.admin.dailyCap')}
                            </Label>
                            <Input
                                id="points-cap"
                                type="number"
                                min={0}
                                max={5000}
                                value={v.daily_cap}
                                onChange={(e) =>
                                    setV({
                                        ...v,
                                        daily_cap: number(e.target.value),
                                    })
                                }
                            />
                        </div>
                    </div>
                </section>
                <section
                    aria-labelledby="targets-title"
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                >
                    <h2 id="targets-title" className="text-lg font-semibold">
                        {t('rewards.admin.targets')}
                    </h2>
                    <div className="grid gap-6 md:grid-cols-2">
                        {modes.map((mode) => (
                            <fieldset key={mode} className="space-y-3">
                                <legend className="text-base font-semibold">
                                    {t(`rewards.broker.modes.${mode}`)}
                                </legend>
                                <div className="grid grid-cols-2 gap-3">
                                    {goals.map((g) => (
                                        <div key={g} className="space-y-2">
                                            <Label htmlFor={`${mode}-${g}`}>
                                                {t(`rewards.goals.${g}`)}
                                            </Label>
                                            <Input
                                                id={`${mode}-${g}`}
                                                type="number"
                                                min={0}
                                                max={50}
                                                value={v.targets[mode][g]}
                                                onChange={(e) =>
                                                    setV({
                                                        ...v,
                                                        targets: {
                                                            ...v.targets,
                                                            [mode]: {
                                                                ...v.targets[
                                                                    mode
                                                                ],
                                                                [g]: number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            },
                                                        },
                                                    })
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            </fieldset>
                        ))}
                    </div>
                </section>
            </form>
        </>
    );
}
