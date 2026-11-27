import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import type { MenteeDetailData } from '@/components/mentoring/types';
import { MenteeDetailView } from '@/components/mentoring/workspace';
import { PageHeader } from '@/components/page-header';
import { deleteJson, postJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import { store as goalStore } from '@/routes/mentoring/goals';
import {
    destroy as noteDestroy,
    restore as noteRestore,
    store as noteStore,
} from '@/routes/mentoring/notes';

/**
 * A mentee (A13.1b; mentor screen 24): plan, scorecard, deals, sessions,
 * goals set together (the mentee's to-dos) and private notes (the mentor
 * only, removable with Undo).
 */
export default function MenteePage({ detail }: { detail: MenteeDetailData }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const reload = () => router.reload({ only: ['detail'] });
    const run = (work: Promise<unknown>, done?: () => void) => {
        setBusy(true);
        void work
            .then(() => {
                reload();
                done?.();
            })
            .catch((e: unknown) =>
                toast.error(e instanceof Error ? e.message : String(e)),
            )
            .finally(() => setBusy(false));
    };

    return (
        <>
            <Head title={detail.mentee.name} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={detail.mentee.name}
                    description={[
                        t(`mentoring.goals.${detail.mentee.goal}`),
                        t(
                            `mentoring.workspace.signals.${detail.mentee.signal}`,
                        ),
                    ].join(' · ')}
                />
                <MenteeDetailView
                    detail={detail}
                    busy={busy}
                    onAddNote={(body) =>
                        run(postJson(noteStore(detail.mentee.id).url, { body }))
                    }
                    onRemoveNote={(id) =>
                        run(deleteJson(noteDestroy(id).url), () =>
                            undoToast({
                                message: t('mentoring.workspace.noteRemoved'),
                                onUndo: async () => {
                                    await postJson(noteRestore(id).url, {});
                                    reload();
                                },
                            }),
                        )
                    }
                    onAddGoal={(title, due) =>
                        run(
                            postJson(goalStore(detail.mentee.id).url, {
                                title,
                                due_date: due,
                            }),
                            () =>
                                toast.success(
                                    t('mentoring.workspace.goalAdded', {
                                        name: detail.mentee.name,
                                    }),
                                ),
                        )
                    }
                />
            </div>
        </>
    );
}
