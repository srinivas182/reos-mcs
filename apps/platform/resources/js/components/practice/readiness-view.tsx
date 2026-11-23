import { router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    CalendarCheck,
    CircleCheck,
    CircleDashed,
    Gauge,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ReadinessRing } from './readiness-ring';
import type { Portion, ReadinessMap } from './types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { start } from '@/routes/practice';

type Filter = 'all' | Portion;

/**
 * Readiness (candidate web sidebar; the phone's Readiness map and What
 * you're good at, screens 54 and 44, A9.2): the predicted pass chance for
 * each portion against the 90% booking line, what booking still needs,
 * every topic weakest first with its trend, and the gain from fixing the
 * weakest.
 */
export function ReadinessView({ map }: { map: ReadinessMap }) {
    const { t, i18n } = useTranslation();
    const [filter, setFilter] = useState<Filter>('all');
    const practise = (topic: string) =>
        router.post(start().url, { kind: 'topic', topic });
    const known = map.portions.national !== null || map.portions.state !== null;
    const topics = map.topics.filter(
        (x) => filter === 'all' || x.portion === filter,
    );

    return (
        <div className="space-y-6 px-4 py-6 md:px-8">
            <PageHeader
                title={t('practice.readinessMap.title')}
                description={t('practice.readinessMap.description')}
            />

            {!known ? (
                <EmptyState
                    icon={Gauge}
                    title={t('practice.readinessMap.emptyTitle')}
                    description={t('practice.readinessMap.empty')}
                />
            ) : (
                <>
                    <section className="grid gap-4 md:grid-cols-2">
                        {(['national', 'state'] as const).map((portion) => (
                            <PortionCard
                                key={portion}
                                portion={portion}
                                percent={map.portions[portion]}
                                line={map.line}
                            />
                        ))}
                    </section>

                    <Booking booking={map.booking} line={map.line} />
                </>
            )}

            <section
                aria-labelledby="good-at"
                className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            >
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h2 id="good-at" className="text-lg font-semibold">
                            {t('practice.readinessMap.goodAt.title')}
                        </h2>
                        <p className="text-body text-muted-foreground">
                            {t('practice.readinessMap.goodAt.description')}
                        </p>
                    </div>
                    <div
                        role="group"
                        aria-label={t('practice.readinessMap.goodAt.filter')}
                        className="flex flex-wrap gap-2"
                    >
                        {(['all', 'national', 'state'] as const).map((key) => (
                            <Button
                                key={key}
                                variant={filter === key ? 'default' : 'outline'}
                                aria-pressed={filter === key}
                                onClick={() => setFilter(key)}
                            >
                                {t(`practice.readinessMap.goodAt.${key}`)}
                            </Button>
                        ))}
                    </div>
                </div>

                {map.gain && map.gain.points > 0 ? (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-reos-md bg-reos-tone-info-bg p-4">
                        <p className="text-body">
                            {t('practice.readinessMap.gain', {
                                topic: t(`practice.topics.${map.gain.topic}`),
                                points: map.gain.points,
                                portion: t(
                                    `practice.portions.${map.gain.portion}.title`,
                                ),
                            })}
                        </p>
                        <Button
                            onClick={() => map.gain && practise(map.gain.topic)}
                        >
                            {t('practice.readinessMap.fixWeakest')}
                        </Button>
                    </div>
                ) : null}

                <ul className="divide-y divide-border">
                    {topics.map((x) => (
                        <li
                            key={x.topic}
                            className="flex flex-wrap items-center justify-between gap-3 py-3"
                        >
                            <div className="min-w-0 flex-1 space-y-1">
                                <p className="flex flex-wrap items-center gap-2 font-medium">
                                    {t(`practice.topics.${x.topic}`)}
                                    {x.weak ? (
                                        <Badge variant="outline">
                                            {t(
                                                'practice.readinessMap.goodAt.weak',
                                            )}
                                        </Badge>
                                    ) : null}
                                </p>
                                <p className="text-body text-muted-foreground">
                                    {x.shown && x.percent !== null
                                        ? t(
                                              'practice.readinessMap.goodAt.percent',
                                              { percent: x.percent },
                                          )
                                        : x.answers === 0
                                          ? t(
                                                'practice.readinessMap.goodAt.noneYet',
                                            )
                                          : t(
                                                'practice.readinessMap.goodAt.needMore',
                                                { count: x.answers },
                                            )}
                                    {x.trend !== null ? (
                                        <Trend points={x.trend} />
                                    ) : null}
                                </p>
                                {x.shown && x.percent !== null ? (
                                    <div
                                        aria-hidden
                                        className="h-2 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            className={cn(
                                                'h-full rounded-full',
                                                x.weak
                                                    ? 'bg-reos-tone-warning-fg'
                                                    : 'bg-reos-tone-success-fg',
                                            )}
                                            style={{
                                                width: `${x.percent}%`,
                                            }}
                                        />
                                    </div>
                                ) : null}
                            </div>
                            <Button
                                variant="outline"
                                aria-label={t(
                                    'practice.readinessMap.goodAt.practiceTopic',
                                    {
                                        topic: t(`practice.topics.${x.topic}`),
                                    },
                                )}
                                onClick={() => practise(x.topic)}
                            >
                                {t('practice.readinessMap.goodAt.practice')}
                                <ArrowRight aria-hidden />
                            </Button>
                        </li>
                    ))}
                </ul>
            </section>

            {map.computed_on ? (
                <p className="text-sm text-muted-foreground">
                    {t('practice.readinessMap.updated', {
                        date: new Date(
                            `${map.computed_on}T12:00:00`,
                        ).toLocaleDateString(i18n.language, {
                            month: 'long',
                            day: 'numeric',
                        }),
                    })}
                </p>
            ) : null}
        </div>
    );
}

function PortionCard({
    portion,
    percent,
    line,
}: {
    portion: Portion;
    percent: number | null;
    line: number;
}) {
    const { t } = useTranslation();
    const name = t(`practice.portions.${portion}.title`);

    return (
        <article className="flex items-center gap-5 rounded-reos-lg border border-border bg-card p-5">
            <ReadinessRing
                percent={percent}
                label={
                    percent === null
                        ? `${name}: ${t('practice.readinessMap.noAnswers')}`
                        : t('practice.readinessMap.chanceLabel', {
                              portion: name,
                              percent,
                          })
                }
                caption={name}
            />
            <div className="space-y-2">
                <h2 className="text-lg font-semibold">{name}</h2>
                <p className="text-body text-muted-foreground">
                    {percent === null
                        ? t('practice.readinessMap.noAnswers')
                        : percent >= line
                          ? t('practice.readinessMap.aboveLine')
                          : t('practice.readinessMap.toGo', {
                                count: line - percent,
                            })}
                </p>
                <p className="text-sm text-muted-foreground">
                    {t('practice.readinessMap.line', { line })}
                </p>
            </div>
        </article>
    );
}

function Booking({
    booking,
    line,
}: {
    booking: ReadinessMap['booking'];
    line: number;
}) {
    const { t } = useTranslation();

    if (booking.ready) {
        return (
            <section className="flex flex-wrap items-center justify-between gap-4 rounded-reos-lg border border-border bg-reos-tone-success-bg p-5">
                <div className="space-y-1">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <CalendarCheck aria-hidden className="size-5" />
                        {t('practice.readinessMap.booking.readyTitle')}
                    </h2>
                    <p className="text-body">
                        {t('practice.readinessMap.booking.readyBody')}
                    </p>
                </div>
                {booking.url ? (
                    <Button asChild>
                        <a href={booking.url} target="_blank" rel="noreferrer">
                            {t('practice.readinessMap.booking.book')}
                        </a>
                    </Button>
                ) : null}
            </section>
        );
    }

    const checks = [
        {
            key: 'national',
            met: booking.national,
            text: t('practice.readinessMap.booking.national', { line }),
        },
        {
            key: 'state',
            met: booking.state,
            text: t('practice.readinessMap.booking.state', { line }),
        },
        {
            key: 'simulations',
            met: booking.simulations >= booking.needed,
            text: t('practice.readinessMap.booking.simulations', {
                done: booking.simulations,
                needed: booking.needed,
            }),
        },
    ];

    return (
        <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
            <h2 className="text-lg font-semibold">
                {t('practice.readinessMap.booking.notYetTitle')}
            </h2>
            <ul className="space-y-2">
                {checks.map((c) => (
                    <li key={c.key} className="flex items-center gap-3">
                        {c.met ? (
                            <CircleCheck
                                aria-hidden
                                className="size-5 text-reos-tone-success-fg"
                            />
                        ) : (
                            <CircleDashed
                                aria-hidden
                                className="size-5 text-muted-foreground"
                            />
                        )}
                        <span className="text-body">{c.text}</span>
                        <span className="sr-only">
                            {c.met
                                ? t('practice.readinessMap.booking.met')
                                : t('practice.readinessMap.booking.notMet')}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Trend({ points }: { points: number }) {
    const { t } = useTranslation();
    const Icon = points > 0 ? ArrowUpRight : ArrowDownRight;

    return (
        <span className="ml-2 inline-flex items-center gap-1">
            {points !== 0 ? <Icon aria-hidden className="size-4" /> : null}
            {points > 0
                ? t('practice.readinessMap.goodAt.trendUp', { count: points })
                : points < 0
                  ? t('practice.readinessMap.goodAt.trendDown', {
                        count: -points,
                    })
                  : t('practice.readinessMap.goodAt.trendFlat')}
        </span>
    );
}
