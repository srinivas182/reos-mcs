import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import type { DragEvent } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { taskTone, useTaskTitle } from '@/components/practice/task-row';
import type { StudyTask, StudyToday } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import { cn } from '@/lib/utils';
import { index as connections } from '@/routes/connections';
import { move, settings, show } from '@/routes/study-plan';

type Day = { day: string; minutes: number; tasks: StudyTask[] };

/**
 * The study plan's week (candidate web screen 6, A8.3): tasks per day,
 * coloured by type; the weekly goal, the catch-up rule and the reminder.
 * Drag a task to another day (or choose the day from its menu); change the
 * exam month or minutes a day, and the plan is rebuilt. The next two weeks
 * are on the person's calendar.
 */
export default function StudyPlanPage({
    week,
    days,
    summary,
    calendar,
}: {
    week: { from: string; previous: string; next: string; today: string };
    days: Day[];
    summary: StudyToday;
    calendar: { tool: string; calendar: string } | null;
}) {
    const { t, i18n } = useTranslation();
    const title = useTaskTitle();
    const [dragging, setDragging] = useState<number | null>(null);
    const form = useForm({
        exam_month: summary.exam_on.slice(0, 7),
        minutes_per_day: summary.minutes_per_day,
        reminder_time: summary.reminder_time ?? '',
    });
    const label = (day: string) =>
        new Date(`${day}T12:00:00`).toLocaleDateString(i18n.language, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
        });
    const moveTo = (task: number, day: string) =>
        router.put(move(task).url, { day }, { preserveScroll: true });
    const drop = (day: string) => (event: DragEvent) => {
        event.preventDefault();
        if (dragging !== null) moveTo(dragging, day);
        setDragging(null);
    };
    const open = days.filter((d) => d.day >= week.today);

    return (
        <>
            <Head title={t('practice.plan.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.plan.title')}
                    description={t('practice.plan.description')}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={show({
                                        query: { week: week.previous },
                                    })}
                                >
                                    <ArrowLeft aria-hidden />
                                    {t('practice.plan.previousWeek')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={show({ query: { week: week.next } })}
                                >
                                    {t('practice.plan.nextWeek')}
                                    <ArrowRight aria-hidden />
                                </Link>
                            </Button>
                        </>
                    }
                />

                <section className="grid gap-3 rounded-reos-lg border border-border bg-card p-5 text-body md:grid-cols-3">
                    <p>
                        {t('practice.plan.goal', {
                            count: summary.minutes_per_day * 7,
                        })}
                    </p>
                    <p>{t('practice.plan.catchUp')}</p>
                    <p>
                        {calendar
                            ? t('practice.plan.onCalendar', {
                                  calendar: calendar.calendar,
                              })
                            : t('practice.plan.reosCalendar')}{' '}
                        {calendar ? null : (
                            <Link
                                href={connections()}
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {t('practice.plan.connect')}
                            </Link>
                        )}
                    </p>
                </section>

                <ol className="grid gap-3 md:grid-cols-7">
                    {days.map((day) => (
                        <li
                            key={day.day}
                            onDragOver={(e) =>
                                day.day >= week.today && e.preventDefault()
                            }
                            onDrop={drop(day.day)}
                            className={cn(
                                'min-h-40 space-y-2 rounded-reos-lg border p-3',
                                day.day === week.today
                                    ? 'border-primary bg-accent'
                                    : 'border-border bg-card',
                            )}
                        >
                            <h2 className="flex items-baseline justify-between gap-2 font-semibold">
                                {label(day.day)}
                                <span className="text-sm font-normal text-muted-foreground">
                                    {day.minutes > 0
                                        ? t('practice.tasks.minutes', {
                                              count: day.minutes,
                                          })
                                        : ''}
                                </span>
                            </h2>
                            <ul className="space-y-2">
                                {day.tasks.map((task) => (
                                    <li
                                        key={task.id}
                                        draggable={
                                            !task.done && day.day >= week.today
                                        }
                                        onDragStart={() => setDragging(task.id)}
                                        className={cn(
                                            'space-y-1 rounded-reos-md p-2 text-sm',
                                            taskTone[task.kind],
                                            task.done && 'opacity-60',
                                        )}
                                    >
                                        <p className="font-medium">
                                            {title(task)}
                                        </p>
                                        <p>
                                            {t('practice.tasks.minutes', {
                                                count: task.minutes,
                                            })}
                                            {' · '}
                                            {t(`practice.why.${task.why}`)}
                                        </p>
                                        {!task.done && day.day >= week.today ? (
                                            <select
                                                aria-label={t(
                                                    'practice.plan.moveTo',
                                                    { task: title(task) },
                                                )}
                                                className={`${nativeSelect} h-12 bg-background text-sm`}
                                                value={day.day}
                                                onChange={(e) =>
                                                    moveTo(
                                                        task.id,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {open.map((d) => (
                                                    <option
                                                        key={d.day}
                                                        value={d.day}
                                                    >
                                                        {label(d.day)}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ol>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(settings().url, { preserveScroll: true });
                    }}
                    className="grid gap-4 rounded-reos-lg border border-border bg-card p-5 md:grid-cols-4 md:items-end"
                >
                    <div className="space-y-2">
                        <Label htmlFor="exam_month">
                            {t('practice.plan.examMonth')}
                        </Label>
                        <input
                            id="exam_month"
                            type="month"
                            className={nativeSelect}
                            value={form.data.exam_month}
                            onChange={(e) =>
                                form.setData('exam_month', e.target.value)
                            }
                        />
                        {form.errors.exam_month ? (
                            <p className="text-sm text-destructive">
                                {form.errors.exam_month}
                            </p>
                        ) : null}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="minutes_per_day">
                            {t('practice.plan.minutes')}
                        </Label>
                        <select
                            id="minutes_per_day"
                            className={nativeSelect}
                            value={form.data.minutes_per_day}
                            onChange={(e) =>
                                form.setData(
                                    'minutes_per_day',
                                    Number(e.target.value),
                                )
                            }
                        >
                            {[10, 20, 30, 45, 60].map((m) => (
                                <option key={m} value={m}>
                                    {t('practice.tasks.minutes', { count: m })}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="reminder_time">
                            {t('practice.plan.reminder')}
                        </Label>
                        <input
                            id="reminder_time"
                            type="time"
                            className={nativeSelect}
                            value={form.data.reminder_time}
                            onChange={(e) =>
                                form.setData('reminder_time', e.target.value)
                            }
                        />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {t('practice.plan.rebuild')}
                    </Button>
                </form>
            </div>
        </>
    );
}
