import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { index as builderIndex } from '@/routes/builder';

export type Teaching = {
    courses: number;
    reviews: number;
    drafts: number;
    sessions_week: number;
    pass_rate: number | null;
    rating: number | null;
    today: { id: string; title: string; starts_at: string }[];
    missed: { topic: string; missed: number; answered: number }[];
};

/**
 * The instructor's dashboard (web screen 42, A7.2): reviews, sessions, pass
 * rate, rating, today's list and the most-missed topics.
 */
export function TeachingCard({ teaching }: { teaching: Teaching }) {
    const { t, i18n } = useTranslation();
    const tiles: [string, string | number][] = [
        [t('builder.dashboard.reviews'), teaching.reviews],
        [t('builder.dashboard.drafts'), teaching.drafts],
        [t('builder.dashboard.sessions'), teaching.sessions_week],
        [
            t('builder.dashboard.passRate'),
            teaching.pass_rate === null
                ? t('builder.dashboard.noData')
                : `${teaching.pass_rate}%`,
        ],
        [
            t('builder.dashboard.rating'),
            teaching.rating ?? t('builder.dashboard.noData'),
        ],
    ];

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">
                    {t('builder.dashboard.title')}
                </h2>
                <Link
                    href={builderIndex().url}
                    className="inline-flex min-h-12 items-center text-base font-medium text-primary underline-offset-4 hover:underline"
                >
                    {t('builder.dashboard.open')}
                </Link>
            </div>
            <dl className="grid gap-4 sm:grid-cols-3 xl:grid-cols-5">
                {tiles.map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-reos-lg border border-border bg-card p-4"
                    >
                        <dt className="text-sm text-muted-foreground">
                            {label}
                        </dt>
                        <dd className="text-2xl font-semibold">{value}</dd>
                    </div>
                ))}
            </dl>
            <div className="grid gap-4 lg:grid-cols-2">
                <div className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                    <h3 className="font-semibold">
                        {t('builder.dashboard.today')}
                    </h3>
                    {teaching.today.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('builder.dashboard.nothingToday')}
                        </p>
                    ) : (
                        <ul className="space-y-1 text-base">
                            {teaching.today.map((s) => (
                                <li key={s.id}>
                                    {new Intl.DateTimeFormat(i18n.language, {
                                        timeStyle: 'short',
                                    }).format(new Date(s.starts_at))}{' '}
                                    · {s.title}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                    <h3 className="font-semibold">
                        {t('builder.dashboard.missed')}
                    </h3>
                    {teaching.missed.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('builder.dashboard.noMissed')}
                        </p>
                    ) : (
                        <ul className="space-y-1 text-base">
                            {teaching.missed.map((m) => (
                                <li key={m.topic}>
                                    {t('builder.dashboard.missedLine', {
                                        topic: t(
                                            `onboarding.topics.${m.topic as 'agency'}`,
                                        ),
                                        missed: m.missed,
                                        answered: m.answered,
                                    })}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </section>
    );
}
