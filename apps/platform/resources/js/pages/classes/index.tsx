import { Head, Link, router } from '@inertiajs/react';
import { CalendarPlus, Users, Video } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ClassCard } from '@/components/classes/class-card';
import { ScheduleClassDialog } from '@/components/classes/schedule-class-dialog';
import type { ClassCourse, LiveClassRow } from '@/components/classes/types';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { deleteJson, postJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import { register, unregister } from '@/routes/classes';
import { index as sessionsIndex } from '@/routes/sessions';

type Group = 'thisWeek' | 'nextWeek' | 'later' | 'past';

/** Monday 00:00 of the week `weeks` from now, in the viewer's time zone. */
function weekStart(weeks: number): number {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7) + weeks * 7);

    return d.getTime();
}

/** This week, next week, later, and past (A10.2b, instructor i11). */
export function groupClasses(
    rows: LiveClassRow[],
    now = Date.now(),
): Record<Group, LiveClassRow[]> {
    const next = weekStart(1);
    const after = weekStart(2);
    const groups: Record<Group, LiveClassRow[]> = {
        thisWeek: [],
        nextWeek: [],
        later: [],
        past: [],
    };

    for (const row of rows) {
        const starts = new Date(row.starts_at).getTime();

        if (
            row.status === 'ended' ||
            row.status === 'cancelled' ||
            new Date(row.ends_at).getTime() <= now
        ) {
            groups.past.push(row);
        } else if (starts < next) {
            groups.thisWeek.push(row);
        } else if (starts < after) {
            groups.nextWeek.push(row);
        } else {
            groups.later.push(row);
        }
    }

    groups.past.reverse();

    return groups;
}

/**
 * Live classes (A10.2b). Instructors (Web App Journey w505, i11): the week's
 * schedule with registrations and CE label, and Schedule a class. Students
 * (L12, the course's Live classes): classes for their courses, Register,
 * and Join from ten minutes before.
 */
export default function ClassesPage({
    classes,
    can_schedule,
    courses,
    lengths,
}: {
    classes: LiveClassRow[];
    can_schedule: boolean;
    courses: ClassCourse[];
    lengths: number[];
}) {
    const { t } = useTranslation();
    const [scheduling, setScheduling] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const groups = groupClasses(classes);
    const reload = () => router.reload({ only: ['classes'] });

    const act = async (id: string, run: () => Promise<unknown>) => {
        setBusy(id);
        setError(null);

        try {
            await run();
            reload();

            return true;
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));

            return false;
        } finally {
            setBusy(null);
        }
    };

    const cancelRegistration = async (row: LiveClassRow) => {
        if (await act(row.id, () => deleteJson(unregister(row.id).url))) {
            undoToast({
                message: t('classes.unregistered', { title: row.title }),
                onUndo: () =>
                    act(row.id, () => postJson(register(row.id).url)).then(
                        () => undefined,
                    ),
            });
        }
    };

    return (
        <>
            <Head title={t('classes.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('classes.title')}
                    description={
                        can_schedule
                            ? t('classes.purposeHost')
                            : t('classes.purpose')
                    }
                    actions={
                        can_schedule ? (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={sessionsIndex().url}>
                                        <Video aria-hidden />
                                        {t('classes.allSessions')}
                                    </Link>
                                </Button>
                                <Button
                                    onClick={() => setScheduling(true)}
                                    disabled={courses.length === 0}
                                >
                                    <CalendarPlus aria-hidden />
                                    {t('classes.schedule.title')}
                                </Button>
                            </>
                        ) : undefined
                    }
                />
                {error ? <InputError message={error} /> : null}

                {classes.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={
                            can_schedule
                                ? t('classes.emptyHost')
                                : t('classes.empty')
                        }
                        description={
                            can_schedule
                                ? courses.length === 0
                                    ? t('classes.noCourses')
                                    : t('classes.emptyHostBody')
                                : t('classes.emptyBody')
                        }
                        action={
                            can_schedule && courses.length > 0 ? (
                                <Button onClick={() => setScheduling(true)}>
                                    {t('classes.schedule.title')}
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    (['thisWeek', 'nextWeek', 'later', 'past'] as const).map(
                        (group) =>
                            groups[group].length > 0 ? (
                                <section
                                    key={group}
                                    aria-labelledby={`classes-${group}`}
                                    className="space-y-3"
                                >
                                    <h2
                                        id={`classes-${group}`}
                                        className="text-lg font-semibold"
                                    >
                                        {t(`classes.groups.${group}`)}
                                    </h2>
                                    <ul className="space-y-3">
                                        {groups[group].map((row) => (
                                            <li key={row.id}>
                                                <ClassCard
                                                    row={row}
                                                    busy={busy === row.id}
                                                    onRegister={() =>
                                                        void act(row.id, () =>
                                                            postJson(
                                                                register(row.id)
                                                                    .url,
                                                            ),
                                                        )
                                                    }
                                                    onUnregister={() =>
                                                        void cancelRegistration(
                                                            row,
                                                        )
                                                    }
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ) : null,
                    )
                )}
            </div>
            {can_schedule ? (
                <ScheduleClassDialog
                    open={scheduling}
                    onOpenChange={setScheduling}
                    courses={courses}
                    lengths={lengths}
                    onScheduled={reload}
                />
            ) : null}
        </>
    );
}
