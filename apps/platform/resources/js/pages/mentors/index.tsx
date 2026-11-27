import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { InstructorCard } from '@/components/bookings/instructor-card';
import type { InstructorOffer } from '@/components/bookings/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as bookings } from '@/routes/bookings';

/**
 * Your mentors (candidate f04, A10.3c): a new agent's own brokerage's
 * mentors with rating, languages and next free hour; Book through 1-on-1
 * sessions. Exam instructors stay available for CE questions. The
 * assigned mentor comes first and is free (A13.1a, screens 114 and 116).
 */
export default function MentorsPage({
    brokerage,
    mentors,
}: {
    brokerage: string | null;
    mentors: (InstructorOffer & { assigned?: boolean; points?: boolean })[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('mentors.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('mentors.title')}
                    description={
                        brokerage
                            ? t('mentors.purpose', { brokerage })
                            : t('mentors.purposeNone')
                    }
                    actions={
                        <Button asChild variant="outline">
                            <Link href={bookings().url}>
                                {t('mentors.instructors')}
                            </Link>
                        </Button>
                    }
                />
                {mentors.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={
                            brokerage
                                ? t('mentors.empty')
                                : t('mentors.noBrokerage')
                        }
                        description={
                            brokerage
                                ? t('mentors.emptyBody', { brokerage })
                                : t('mentors.noBrokerageBody')
                        }
                    />
                ) : (
                    <ul className="grid gap-3 md:grid-cols-2">
                        {mentors.map((m) => (
                            <li key={m.id} className="space-y-2">
                                {m.assigned ? (
                                    <p className="text-base font-semibold">
                                        {t('mentoring.agent.assigned')} ·{' '}
                                        {t('mentoring.agent.free')}
                                    </p>
                                ) : null}
                                <InstructorCard offer={m} />
                                {m.assigned ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('mentoring.agent.assignedNote')}
                                    </p>
                                ) : m.points && m.price_60 > 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('mentoring.agent.points')}
                                    </p>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
