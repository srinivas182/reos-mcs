import { useTranslation } from 'react-i18next';
import type { WeeklyReport } from './types';

const date = (
    day: string,
    language: string,
    options: Intl.DateTimeFormatOptions,
) => new Date(`${day}T12:00:00`).toLocaleDateString(language, options);

/**
 * The weekly progress report's figures (screen 55, A9.2e): time studied
 * with minutes each day against the plan, questions, flashcards, the
 * readiness change, lessons and course hours, the weakest topic and the
 * streak. The candidate's page, the school's Students page and a study
 * buddy's link all show it.
 */
export function WeeklyReportView({ report }: { report: WeeklyReport }) {
    const { t, i18n } = useTranslation();
    const goalPerDay = report.goal_minutes / 7;
    const top = Math.max(goalPerDay, ...report.per_day, 1);
    const change = report.readiness.change;
    const tiles: [string, string][] = [
        [
            t('practice.reports.minutes'),
            t('practice.reports.minutesValue', { count: report.minutes }),
        ],
        [t('practice.reports.questions'), String(report.questions)],
        [t('practice.reports.cards'), String(report.cards)],
        [
            t('practice.reports.readiness'),
            report.readiness.now === null
                ? t('practice.reports.notYet')
                : change === null
                  ? t('practice.reports.readinessNow', {
                        percent: report.readiness.now,
                    })
                  : t('practice.reports.readinessChange', {
                        percent: report.readiness.now,
                        change: change > 0 ? `+${change}` : String(change),
                    }),
        ],
        [t('practice.reports.lessons'), String(report.lessons)],
        [
            t('practice.reports.hours'),
            t('practice.reports.hoursValue', { hours: report.hours }),
        ],
        [
            t('practice.reports.weakest'),
            report.weakest
                ? t(`practice.topics.${report.weakest}`)
                : t('practice.reports.none'),
        ],
        [
            t('practice.reports.streak'),
            t('practice.reports.streakValue', { count: report.streak }),
        ],
    ];

    return (
        <div className="space-y-4">
            <p className="text-body text-muted-foreground">
                {t('practice.reports.week', {
                    start: date(report.week_start, i18n.language, {
                        month: 'long',
                        day: 'numeric',
                    }),
                    end: date(report.week_end, i18n.language, {
                        month: 'long',
                        day: 'numeric',
                    }),
                })}
            </p>
            <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {tiles.map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-reos-lg border border-border bg-card p-4"
                    >
                        <dt className="text-sm text-muted-foreground">
                            {label}
                        </dt>
                        <dd className="text-xl font-semibold">{value}</dd>
                    </div>
                ))}
            </dl>
            <section
                aria-labelledby="per-day"
                className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
            >
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="per-day" className="text-lg font-semibold">
                        {t('practice.reports.perDay')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('practice.reports.goal', {
                            minutes: report.goal_minutes,
                        })}
                    </p>
                </div>
                <ul className="relative flex h-40 items-end gap-2">
                    <span
                        aria-hidden
                        className="absolute inset-x-0 border-t border-dashed border-foreground/40"
                        style={{ bottom: `${(goalPerDay / top) * 100}%` }}
                    />
                    {report.per_day.map((minutes, i) => {
                        const day = new Date(`${report.week_start}T12:00:00`);
                        day.setDate(day.getDate() + i);
                        const name = day.toLocaleDateString(i18n.language, {
                            weekday: 'short',
                        });

                        return (
                            <li
                                key={name}
                                className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                aria-label={t('practice.reports.day', {
                                    day: name,
                                    minutes,
                                })}
                            >
                                <span
                                    aria-hidden
                                    className={
                                        minutes >= goalPerDay
                                            ? 'w-full rounded-t-reos-sm bg-reos-tone-success-fg'
                                            : 'w-full rounded-t-reos-sm bg-primary'
                                    }
                                    style={{
                                        height: `${Math.max(2, (minutes / top) * 100)}%`,
                                    }}
                                />
                                <span
                                    aria-hidden
                                    className="text-sm text-muted-foreground"
                                >
                                    {name}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            </section>
        </div>
    );
}
