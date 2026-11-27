import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import type { MenteeRow, MentorTodayData } from '@/components/mentoring/types';
import { MentorTodayView } from '@/components/mentoring/workspace';
import { PageHeader } from '@/components/page-header';
import { show } from '@/routes/mentoring';

/**
 * Mentor workspace (A13.1b; web screen 48, mentor screens 22 and 23):
 * who needs you, upcoming sessions, wrap-ups to send, and all mentees.
 */
export default function MentorWorkspacePage({
    today,
    mentees,
}: {
    today: MentorTodayData;
    mentees: MenteeRow[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('mentoring.workspace.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('mentoring.workspace.title')}
                    description={t('mentoring.workspace.purpose', {
                        brokerage: today.brokerage,
                    })}
                />
                <MentorTodayView
                    today={today}
                    mentees={mentees}
                    menteeHref={(id) => show(id).url}
                />
            </div>
        </>
    );
}
