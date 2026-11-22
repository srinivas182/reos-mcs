import { Head, usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CeHoursTile } from '@/components/ce-requirement';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { TodayCard } from '@/components/today-card';
import { activeMembership, roleName } from '@/lib/roles';
import type { CeRequirement } from '@/types';
import type { CalendarDay } from '@/types/calendar';

/**
 * Each role's dashboard (web journey screens 4, 12, 24, 30, 42, 49, 54, 58)
 * is built in that role's sprint. Agents (screen 12) have their CE hours
 * (A5.2) and today's calendar (B4.1). Until the other cards arrive: the journey's greeting and an honest
 * note instead of placeholder boxes.
 */
export default function Dashboard({
    today = null,
    ce = null,
}: {
    today?: CalendarDay | null;
    ce?: CeRequirement | null;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const current = activeMembership(auth.roles);
    const first = auth.user.name?.split(' ')[0];
    const title = first
        ? t('dashboard.greeting', { name: first })
        : t('dashboard.greetingNoName');

    return (
        <>
            <Head title={t('nav.dashboard')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={title}
                    description={
                        current
                            ? t('dashboard.description', {
                                  role: roleName(t, current.role),
                              })
                            : undefined
                    }
                />
                {ce ? (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <CeHoursTile ce={ce} />
                    </div>
                ) : null}
                {today ? <TodayCard today={today} /> : null}
                <EmptyState
                    icon={LayoutDashboard}
                    title={t('dashboard.comingTitle')}
                    description={t('dashboard.comingDescription')}
                />
            </div>
        </>
    );
}
