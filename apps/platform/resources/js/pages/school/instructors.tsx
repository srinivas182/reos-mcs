import { Head } from '@inertiajs/react';
import { GraduationCap, MailPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { CoursesDialog } from '@/components/instructors/courses-dialog';
import { InstructorsTable } from '@/components/instructors/instructors-table';
import { TwinsDialog } from '@/components/instructors/twins-dialog';
import type { InstructorRow } from '@/components/instructors/types';
import { InviteSheet } from '@/components/invite-sheet';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { InviteOptions } from '@/types';

/**
 * Instructors (school admin web screen 52, A7.1b): manage instructors and
 * avatar consent. Invite, Edit (courses they teach), Revoke with Undo.
 */
export default function Instructors({
    school,
    instructors,
    courses,
    invite,
}: {
    school: { name: string };
    instructors: InstructorRow[];
    courses: { id: number; title: string }[];
    invite: InviteOptions;
}) {
    const { t } = useTranslation();
    const [inviting, setInviting] = useState(false);
    const [editing, setEditing] = useState<InstructorRow | null>(null);
    const [twins, setTwins] = useState<InstructorRow | null>(null);
    const inviteButton = (
        <Button onClick={() => setInviting(true)}>
            <MailPlus aria-hidden className="size-5" />
            {t('instructors.invite')}
        </Button>
    );

    return (
        <>
            <Head title={t('instructors.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('instructors.title')}
                    description={t('instructors.description')}
                    actions={inviteButton}
                />
                {instructors.length === 0 ? (
                    <EmptyState
                        icon={GraduationCap}
                        title={t('instructors.emptyTitle')}
                        description={t('instructors.empty')}
                        action={inviteButton}
                    />
                ) : (
                    <InstructorsTable
                        instructors={instructors}
                        onEdit={setEditing}
                        onTwins={setTwins}
                    />
                )}
            </div>
            <InviteSheet
                open={inviting}
                onOpenChange={setInviting}
                options={invite}
                tenant={school.name}
                defaultRole="instructor"
            />
            <CoursesDialog
                instructor={editing}
                courses={courses}
                onClose={() => setEditing(null)}
            />
            <TwinsDialog instructor={twins} onClose={() => setTwins(null)} />
        </>
    );
}
