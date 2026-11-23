import { Head } from '@inertiajs/react';
import { Plus, Save, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DAYS, type SessionOfferValues } from '@/components/bookings/types';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { money } from '@/lib/billing';
import { putJson } from '@/lib/content';
import { save } from '@/routes/bookings/hours';

const blank = (): SessionOfferValues => ({
    bio: '',
    languages: ['en'],
    topics: [],
    hours: { mon: [['13:00', '17:00']] },
    timezone:
        Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Chicago',
    price_30_cents: 3500,
    price_60_cents: 6000,
    active: true,
});

/**
 * Hours and prices (instructor screen i14 › Hours, A10.2a): weekly hours in
 * the instructor's time zone, 30- and 60-minute prices (0 means included)
 * and what they help with. Paid sessions are paid out like courses.
 */
export default function HoursPage({
    offer,
    limits = { max_30_cents: null, max_60_cents: null },
}: {
    offer: SessionOfferValues | null;
    /** The most the school allows, in cents (A10.2d); null is no limit. */
    limits?: { max_30_cents: number | null; max_60_cents: number | null };
}) {
    const { t, i18n } = useTranslation();
    const [values, setValues] = useState<SessionOfferValues>(offer ?? blank());
    const [topics, setTopics] = useState((offer?.topics ?? []).join(', '));
    const [error, setError] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);
    const set = (patch: Partial<SessionOfferValues>) => {
        setValues((v) => ({ ...v, ...patch }));
        setSaved(false);
    };
    const setDay = (day: string, ranges: [string, string][]) => {
        const hours = { ...values.hours };
        if (ranges.length === 0) {
            delete hours[day];
        } else {
            hours[day] = ranges;
        }
        set({ hours });
    };

    const submit = async () => {
        try {
            await putJson(save().url, {
                ...values,
                topics: topics
                    .split(',')
                    .map((x) => x.trim())
                    .filter(Boolean),
            });
            setError(null);
            setSaved(true);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('bookings.hours.title')} />
            <div className="max-w-3xl space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('bookings.hours.title')}
                    description={t('bookings.hours.purpose')}
                />
                <div className="flex min-h-12 items-center justify-between gap-3">
                    <Label htmlFor="offer-active">
                        {t('bookings.hours.active')}
                    </Label>
                    <Switch
                        id="offer-active"
                        checked={values.active}
                        onCheckedChange={(on) => set({ active: on })}
                    />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    {(['price_30_cents', 'price_60_cents'] as const).map(
                        (key) => {
                            const max =
                                limits[
                                    key === 'price_30_cents'
                                        ? 'max_30_cents'
                                        : 'max_60_cents'
                                ];

                            return (
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={key}>
                                        {t('bookings.hours.price', {
                                            count:
                                                key === 'price_30_cents'
                                                    ? 30
                                                    : 60,
                                        })}
                                    </Label>
                                    <Input
                                        id={key}
                                        type="number"
                                        min={0}
                                        max={
                                            max === null ? undefined : max / 100
                                        }
                                        step={1}
                                        aria-describedby={
                                            max === null
                                                ? undefined
                                                : `${key}-limit`
                                        }
                                        value={values[key] / 100}
                                        onChange={(e) =>
                                            set({
                                                [key]: Math.round(
                                                    Number(e.target.value) *
                                                        100,
                                                ),
                                            } as Partial<SessionOfferValues>)
                                        }
                                    />
                                    {/* The school's limit (A10.2d, decision 3). */}
                                    {max !== null ? (
                                        <p
                                            id={`${key}-limit`}
                                            className="text-sm text-muted-foreground"
                                        >
                                            {t('bookings.hours.limitHint', {
                                                price: money(
                                                    max,
                                                    i18n.language,
                                                ),
                                            })}
                                        </p>
                                    ) : null}
                                </div>
                            );
                        },
                    )}
                </div>
                <p className="text-sm text-muted-foreground">
                    {t('bookings.hours.priceHint')}
                </p>

                <fieldset className="space-y-2">
                    <legend className="font-semibold">
                        {t('bookings.hours.week', { zone: values.timezone })}
                    </legend>
                    {DAYS.map((day) => {
                        const ranges = values.hours[day] ?? [];

                        return (
                            <div
                                key={day}
                                className="flex flex-wrap items-center gap-2"
                            >
                                <span className="w-28 font-medium">
                                    {t(`bookings.days.${day}`)}
                                </span>
                                {ranges.map((range, i) => (
                                    <span
                                        key={i}
                                        className="flex items-center gap-1"
                                    >
                                        {[0, 1].map((edge) => (
                                            <Input
                                                key={edge}
                                                type="time"
                                                step={1800}
                                                className="w-32"
                                                aria-label={t(
                                                    edge === 0
                                                        ? 'bookings.hours.from'
                                                        : 'bookings.hours.to',
                                                    {
                                                        day: t(
                                                            `bookings.days.${day}`,
                                                        ),
                                                    },
                                                )}
                                                value={range[edge]}
                                                onChange={(e) =>
                                                    setDay(
                                                        day,
                                                        ranges.map((r, j) =>
                                                            j === i
                                                                ? ((edge === 0
                                                                      ? [
                                                                            e
                                                                                .target
                                                                                .value,
                                                                            r[1],
                                                                        ]
                                                                      : [
                                                                            r[0],
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ]) as [
                                                                      string,
                                                                      string,
                                                                  ])
                                                                : r,
                                                        ),
                                                    )
                                                }
                                            />
                                        ))}
                                        <Button
                                            variant="ghost"
                                            aria-label={t(
                                                'bookings.hours.remove',
                                            )}
                                            onClick={() =>
                                                setDay(
                                                    day,
                                                    ranges.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                )
                                            }
                                        >
                                            <X aria-hidden />
                                        </Button>
                                    </span>
                                ))}
                                {ranges.length < 4 ? (
                                    <Button
                                        variant="outline"
                                        aria-label={t('bookings.hours.add', {
                                            day: t(`bookings.days.${day}`),
                                        })}
                                        onClick={() =>
                                            setDay(day, [
                                                ...ranges,
                                                ['13:00', '17:00'],
                                            ])
                                        }
                                    >
                                        <Plus aria-hidden />
                                    </Button>
                                ) : null}
                            </div>
                        );
                    })}
                </fieldset>

                <fieldset className="space-y-2">
                    <legend className="font-semibold">
                        {t('bookings.hours.languages')}
                    </legend>
                    {(['en', 'es'] as const).map((l) => (
                        <div
                            key={l}
                            className="flex min-h-12 items-center gap-3"
                        >
                            <Checkbox
                                id={`lang-${l}`}
                                checked={values.languages.includes(l)}
                                onCheckedChange={(on) =>
                                    set({
                                        languages:
                                            on === true
                                                ? [...values.languages, l]
                                                : values.languages.filter(
                                                      (x) => x !== l,
                                                  ),
                                    })
                                }
                            />
                            <Label htmlFor={`lang-${l}`}>
                                {t(`bookings.languages.${l}`)}
                            </Label>
                        </div>
                    ))}
                </fieldset>
                <div className="grid gap-2">
                    <Label htmlFor="offer-topics">
                        {t('bookings.hours.topics')}
                    </Label>
                    <Input
                        id="offer-topics"
                        value={topics}
                        onChange={(e) => setTopics(e.target.value)}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="offer-bio">{t('bookings.hours.bio')}</Label>
                    <Textarea
                        id="offer-bio"
                        rows={3}
                        maxLength={1000}
                        value={values.bio ?? ''}
                        onChange={(e) => set({ bio: e.target.value })}
                    />
                </div>
                {error ? <InputError message={error} /> : null}
                {saved ? (
                    <p
                        className="text-sm text-reos-tone-success-fg"
                        aria-live="polite"
                    >
                        {t('bookings.hours.saved')}
                    </p>
                ) : null}
                <Button
                    onClick={() => void submit()}
                    disabled={values.languages.length === 0}
                >
                    <Save aria-hidden />
                    {t('bookings.hours.save')}
                </Button>
            </div>
        </>
    );
}
