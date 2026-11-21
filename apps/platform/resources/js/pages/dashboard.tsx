import { Head, usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { TodayCard } from '@/components/today-card';
import { activeMembership, roleName } from '@/lib/roles';
import type { CalendarDay } from '@/types/calendar';

/**
 * Each role's dashboard (web journey screens 4, 12, 24, 30, 42, 49, 54, 58)
 * is built in that role's sprint. Agents (screen 12) have today's calendar
 * (B4.1). Until the other cards arrive: the journey's greeting and an honest
 * note instead of placeholder boxes.
 */
export default function Dashboard({
    today = null,
}: {
    today?: CalendarDay | null;
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
