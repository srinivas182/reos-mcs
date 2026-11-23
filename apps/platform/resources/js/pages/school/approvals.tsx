import { Head } from '@inertiajs/react';
import { BookOpen, FileBadge, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApprovalDialog } from '@/components/approvals/approval-dialog';
import type { DialogMode } from '@/components/approvals/approval-dialog';
import { ApprovalsTable } from '@/components/approvals/approvals-table';
import type {
    ApprovalOptions,
    ApprovalRow,
} from '@/components/approvals/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

/**
 * Course catalogue and state approvals (school admin web screen 50, A7.1):
 * every approval of the school's courses, with Add approval, Add state and
 * Renew. No CE or qualifying label without a recorded approval.
 */
export default function StateApprovals({
    approvals,
    ...options
}: ApprovalOptions & { school: { name: string }; approvals: ApprovalRow[] }) {
    const { t } = useTranslation();
    const [mode, setMode] = useState<DialogMode | null>(null);
    const hasCourses = options.courses.length > 0;

    return (
        <>
            <Head title={t('approvals.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('approvals.title')}
                    description={t('approvals.description')}
                    actions={
                        hasCourses ? (
                            <Button onClick={() => setMode({ kind: 'add' })}>
                                <Plus aria-hidden />
                                {t('approvals.add')}
                            </Button>
                        ) : undefined
                    }
                />
                {!hasCourses ? (
                    <EmptyState
                        icon={BookOpen}
                        title={t('approvals.noCoursesTitle')}
                        description={t('approvals.noCourses')}
                    />
                ) : approvals.length === 0 ? (
                    <EmptyState
                        icon={FileBadge}
                        title={t('approvals.emptyTitle')}
                        description={t('approvals.empty')}
                        action={
                            <Button onClick={() => setMode({ kind: 'add' })}>
                                {t('approvals.add')}
                            </Button>
                        }
                    />
                ) : (
                    <ApprovalsTable approvals={approvals} onOpen={setMode} />
                )}
            </div>
            <ApprovalDialog
                mode={mode}
                options={options}
                onClose={() => setMode(null)}
            />
        </>
    );
}
