import { Head, Link, router } from '@inertiajs/react';
import { CalendarCog, GraduationCap } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BookingRow } from '@/components/bookings/booking-row';
import { InstructorCard } from '@/components/bookings/instructor-card';
import { SendTasksDialog } from '@/components/bookings/send-tasks-dialog';
import type { Booking, InstructorOffer } from '@/components/bookings/types';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { cancel, hours, rating } from '@/routes/bookings';

/**
 * Book an instructor (candidate screen 18 on the web, A10.2a): instructors
 * and mentors with their rating, rate and next free hour; your bookings,
 * to join, pay, cancel or rate. Instructors also reach their hours here.
 */
export default function BookingsPage({
    instructors,
    bookings,
    can_teach,
}: {
    instructors: InstructorOffer[];
    bookings: Booking[];
    can_teach: boolean;
}) {
    const { t } = useTranslation();
    const [error, setError] = useState<string | null>(null);
    const [tasksFor, setTasksFor] = useState<Booking | null>(null);
    const act = async (url: string, body: unknown = {}) => {
        try {
            await postJson(url, body);
            setError(null);
            router.reload({ only: ['bookings', 'instructors'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('bookings.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('bookings.title')}
                    description={t('bookings.purpose')}
                    actions={
                        can_teach ? (
                            <Button variant="outline" asChild>
                                <Link href={hours().url}>
                                    <CalendarCog aria-hidden />
                                    {t('bookings.hours.title')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
                {error ? <InputError message={error} /> : null}

                {bookings.length > 0 ? (
                    <section
                        aria-labelledby="my-bookings"
                        className="space-y-3"
                    >
                        <h2 id="my-bookings" className="text-lg font-semibold">
                            {t('bookings.mine')}
                        </h2>
                        <ul className="space-y-3">
                            {bookings.map((b) => (
                                <BookingRow
                                    key={b.uuid}
                                    booking={b}
                                    onCancel={() =>
                                        void act(cancel(b.uuid).url)
                                    }
                                    onRate={(stars) =>
                                        void act(rating(b.uuid).url, { stars })
                                    }
                                    onTasks={() => setTasksFor(b)}
                                />
                            ))}
                        </ul>
                    </section>
                ) : null}

                <section aria-labelledby="instructors" className="space-y-3">
                    <h2 id="instructors" className="text-lg font-semibold">
                        {t('bookings.instructors')}
                    </h2>
                    {instructors.length === 0 ? (
                        <EmptyState
                            icon={GraduationCap}
                            title={t('bookings.emptyTitle')}
                            description={t('bookings.empty')}
                        />
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2">
                            {instructors.map((o) => (
                                <InstructorCard key={o.id} offer={o} />
                            ))}
                        </div>
                    )}
                </section>
                <p className="text-sm text-muted-foreground">
                    {t('bookings.rules')}
                </p>
            </div>
            {tasksFor ? (
                <SendTasksDialog
                    booking={tasksFor}
                    onClose={() => setTasksFor(null)}
                    onSent={() => {
                        setTasksFor(null);
                        router.reload({ only: ['bookings'] });
                    }}
                />
            ) : null}
        </>
    );
}
