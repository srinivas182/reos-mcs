import { Head, router } from '@inertiajs/react';
import type { Locale } from '@reos/i18n';
import { localeNames, supportedLocales } from '@reos/i18n';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { PillGroup } from '@/components/pill-group';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { store } from '@/routes/get-started/setup';

type Choices = {
    exam_month: string | null;
    minutes_per_day: number | null;
    reminder_time: string | null;
    locale: Locale;
};

const MINUTES = ['10', '20', '30', '45', '60'] as const;
const REMINDER_TIMES = ['07:30', '12:30', '19:30', '21:00'] as const;

/** The next four months as YYYY-MM, starting next month (as on the phone). */
function nextMonths(count: number): string[] {
    const now = new Date();

    return Array.from({ length: count }, (_, i) => {
        const date = new Date(now.getFullYear(), now.getMonth() + 1 + i, 1);

        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    });
}

/**
 * "Your plan · 3 quick questions" (candidate journey screen 8, on the web):
 * exam month, time a day, language and a reminder time. The reminder itself
 * comes from the phone app.
 */
export default function PlanSetup({ choices }: { choices: Choices }) {
    const { t, i18n } = useTranslation();
    const { updateLocale } = useAppearance();
    const months = useMemo(() => nextMonths(4), []);
    const [exam, setExam] = useState<string>(
        choices.exam_month ?? months[1] ?? 'not_sure',
    );
    const [minutes, setMinutes] = useState<string>(
        String(choices.minutes_per_day ?? 20),
    );
    const [locale, setLocale] = useState<Locale>(choices.locale);
    const [reminder, setReminder] = useState<string>(
        choices.reminder_time ?? '19:30',
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const monthName = (value: string) =>
        new Intl.DateTimeFormat(i18n.language, { month: 'short' }).format(
            new Date(`${value}-15T12:00:00`),
        );
    const timeName = (value: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(`2026-01-01T${value}:00`));
    const monthOptions = [
        ...months.map((value) => ({ value, label: monthName(value) })),
        { value: 'not_sure', label: t('onboarding.setup.notSure') },
    ];
    const minuteOptions = MINUTES.map((value) => ({
        value,
        label:
            value === '60'
                ? t('onboarding.setup.hourPlus')
                : t('onboarding.setup.minutes', { count: Number(value) }),
    }));
    // Keep a saved month that isn't in the next four (for example after a reload).
    if (!monthOptions.some((option) => option.value === exam)) {
        monthOptions.unshift({ value: exam, label: monthName(exam) });
    }

    const build = () => {
        setSending(true);
        router.post(
            store.url(),
            {
                exam_month: exam === 'not_sure' ? null : exam,
                minutes_per_day: Number(minutes),
                locale,
                reminder_time: reminder === 'none' ? null : reminder,
            },
            {
                onError: (next) => setErrors(next),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title={t('site.getStarted.setupTitle')} />
            <div className="mx-auto max-w-2xl space-y-8 px-4 py-10 md:py-16">
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{t('onboarding.setup.header')}</span>
                    <span className="rounded-reos-pill bg-muted px-3 py-1 font-semibold text-foreground">
                        {t('onboarding.setup.time')}
                    </span>
                </div>

                <section className="space-y-3">
                    <h1 className="text-2xl font-bold text-foreground">
                        {t('onboarding.setup.examTitle')}
                    </h1>
                    <PillGroup
                        label={t('onboarding.setup.examTitle')}
                        value={exam}
                        options={monthOptions}
                        onChange={setExam}
                    />
                    <InputError message={errors.exam_month} />
                </section>

                <section className="space-y-3">
                    <h2 className="text-2xl font-bold text-foreground">
                        {t('onboarding.setup.minutesTitle')}
                    </h2>
                    <PillGroup
                        label={t('onboarding.setup.minutesTitle')}
                        value={minutes}
                        options={minuteOptions}
                        onChange={setMinutes}
                    />
                    <InputError message={errors.minutes_per_day} />
                </section>

                <section className="space-y-3">
                    <h2 className="text-2xl font-bold text-foreground">
                        {t('onboarding.setup.languageTitle')}
                    </h2>
                    <PillGroup
                        label={t('onboarding.setup.languageTitle')}
                        value={locale}
                        options={supportedLocales.map((value) => ({
                            value,
                            label: localeNames[value],
                            lang: value,
                        }))}
                        onChange={(value) => {
                            setLocale(value);
                            updateLocale(value);
                        }}
                    />
                </section>

                <section className="space-y-3">
                    <h2 className="text-2xl font-bold text-foreground">
                        {t('onboarding.setup.remindTitle')}
                    </h2>
                    <PillGroup
                        label={t('site.getStarted.reminderTime')}
                        value={reminder}
                        options={[
                            ...REMINDER_TIMES.map((value) => ({
                                value,
                                label: timeName(value),
                            })),
                            {
                                value: 'none',
                                label: t('onboarding.setup.remindNone'),
                            },
                        ]}
                        onChange={setReminder}
                    />
                    <p className="text-muted-foreground">
                        {t('site.getStarted.reminderHelp')}
                    </p>
                    <InputError message={errors.reminder_time} />
                </section>

                <Button className="w-full" disabled={sending} onClick={build}>
                    {t('onboarding.setup.build')}
                </Button>
            </div>
        </>
    );
}
