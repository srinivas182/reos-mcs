import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    MyFeedbackPanel,
    ToRateList,
} from '@/components/feedback/feedback-view';
import type { FeedbackView } from '@/components/feedback/types';
import { PageHeader } from '@/components/page-header';
import { undoToast } from '@/lib/undo-toast';
import { report, respond, store, visibility } from '@/routes/feedback';

/**
 * Feedback from agents (A13.3b; agent screen 125): rate the other side's
 * agent after a verified closing, and see your own feedback once three
 * agents have rated you, with a tip, responses and reports.
 */
export default function FeedbackPage({ to_rate, mine }: FeedbackView) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const done = { preserveScroll: true, onFinish: () => setBusy(false) };

    return (
        <>
            <Head title={t('feedback.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('feedback.title')}
                    description={t('feedback.purpose')}
                />
                <section aria-labelledby="to-rate-title" className="space-y-3">
                    <div className="space-y-1">
                        <h2
                            id="to-rate-title"
                            className="text-lg font-semibold"
                        >
                            {t('feedback.toRate')}
                        </h2>
                        <p className="text-base text-muted-foreground">
                            {t('feedback.toRateHint')}
                        </p>
                    </div>
                    <ToRateList
                        rows={to_rate}
                        busy={busy}
                        onSend={(row, r) => {
                            setBusy(true);
                            router.post(
                                store().url,
                                {
                                    deal: row.deal,
                                    agent: row.agent.id,
                                    communication: r.communication,
                                    responsiveness: r.responsiveness,
                                    professionalism: r.professionalism,
                                    comment: r.comment,
                                },
                                done,
                            );
                        }}
                    />
                </section>
                <section aria-labelledby="yours-title" className="space-y-3">
                    <h2 id="yours-title" className="text-lg font-semibold">
                        {t('feedback.yours')}
                    </h2>
                    <MyFeedbackPanel
                        mine={mine}
                        busy={busy}
                        onPublic={(value) => {
                            setBusy(true);
                            router.put(
                                visibility().url,
                                { public: value },
                                done,
                            );
                        }}
                        onRespond={(item, text) => {
                            setBusy(true);
                            router.put(
                                respond(item.id).url,
                                { response: text },
                                done,
                            );
                        }}
                        onReport={(item, reason) => {
                            setBusy(true);
                            router.post(
                                report(item.id).url,
                                { reason },
                                {
                                    ...done,
                                    onSuccess: () =>
                                        undoToast({
                                            message: t('feedback.reported'),
                                            onUndo: () =>
                                                new Promise<void>((resolve) =>
                                                    router.post(
                                                        report(item.id).url,
                                                        { reason: null },
                                                        {
                                                            preserveScroll: true,
                                                            onFinish: () =>
                                                                resolve(),
                                                        },
                                                    ),
                                                ),
                                        }),
                                },
                            );
                        }}
                    />
                </section>
            </div>
        </>
    );
}
