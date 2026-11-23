import { Link } from '@inertiajs/react';
import { CalendarDays, Flame, Play } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { lesson as openLesson } from '@/routes/learn';
import { index as practiceIndex } from '@/routes/practice';
import { show as studyPlan } from '@/routes/study-plan';
import { ReadinessRing, TopicBars } from './readiness-ring';
import { TaskRow } from './task-row';
import type { StudyToday, Topic } from './types';

/**
 * The candidate dashboard (web screen 4, A8.3): readiness, streak and exam
 * date; Continue lesson; today's plan with reasons; readiness by topic.
 */
export function CandidateToday({ study }: { study: StudyToday }) {
    const { t, i18n } = useTranslation();
    const exam = new Date(`${study.exam_on}T12:00:00`).toLocaleDateString(
        i18n.language,
        { month: 'long', day: 'numeric', year: 'numeric' },
    );

    return (
        <div className="space-y-6">
            <section
                aria-label={t('practice.today.summary')}
                className="grid gap-4 rounded-reos-lg border border-border bg-card p-5 md:grid-cols-[auto_1fr]"
            >
                <ReadinessRing percent={study.readiness} />
                <div className="grid content-center gap-3 sm:grid-cols-2">
                    <p className="flex items-center gap-2 text-body">
                        <CalendarDays
                            aria-hidden
                            className="size-5 text-primary"
                        />
                        {t('practice.today.daysToExam', {
                            count: study.days_to_exam,
                            date: exam,
                        })}
                    </p>
                    <p className="flex items-center gap-2 text-body">
                        <Flame aria-hidden className="size-5 text-primary" />
                        {t('practice.today.streak', { count: study.streak })}
                    </p>
                    {study.continue ? (
                        <div className="sm:col-span-2">
                            <Button asChild>
                                <Link
                                    href={openLesson(study.continue.lesson_id, {
                                        query:
                                            study.continue.position > 0
                                                ? { t: study.continue.position }
                                                : {},
                                    })}
                                >
                                    <Play aria-hidden />
                                    {t('practice.today.continue', {
                                        title: study.continue.title,
                                    })}
                                </Link>
                            </Button>
                        </div>
                    ) : null}
                </div>
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            {t('practice.today.plan', {
                                count: study.minutes_per_day,
                            })}
                        </h2>
                        <Link
                            href={studyPlan()}
                            className="inline-flex min-h-12 items-center font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {t('practice.plan.title')}
                        </Link>
                    </div>
                    {study.behind_minutes > 0 ? (
                        <p className="rounded-reos-md bg-reos-tone-info-bg p-3 text-body text-reos-tone-info-fg">
                            {t('practice.today.behind', {
                                hours: Math.ceil(study.behind_minutes / 60),
                            })}
                        </p>
                    ) : null}
                    {study.tasks.length > 0 ? (
                        <ul className="divide-y divide-border">
                            {study.tasks.map((task) => (
                                <li key={task.id}>
                                    <TaskRow task={task} />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-body text-muted-foreground">
                            {t('practice.today.rest')}
                        </p>
                    )}
                </section>

                <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            {t('practice.readiness.byTopic')}
                        </h2>
                        <Link
                            href={practiceIndex()}
                            className="inline-flex min-h-12 items-center font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {t('practice.title')}
                        </Link>
                    </div>
                    {study.diagnostic_done ? (
                        <TopicBars
                            topics={(
                                Object.entries(study.topics) as [
                                    Topic,
                                    number | null,
                                ][]
                            ).map(([topic, percent]) => ({ topic, percent }))}
                        />
                    ) : (
                        <p className="text-body text-muted-foreground">
                            {t('practice.readiness.takeDiagnostic')}
                        </p>
                    )}
                </section>
            </div>
        </div>
    );
}
