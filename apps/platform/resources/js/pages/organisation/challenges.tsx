import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { ChallengeForm } from '@/components/pods/challenge-form';
import { ChallengeList } from '@/components/pods/pod-view';
import type { ChallengeMetric, ChallengeRow } from '@/components/pods/types';
import { PageHeader } from '@/components/page-header';
import { store } from '@/routes/organisation/challenges';

/**
 * Brokerage challenges (A13.2): the broker sets opt-in challenges on agents'
 * own numbers; only agents who join appear on the total and most-improved boards.
 */
export default function BrokerChallengesPage({
    brokerage,
    metrics,
    challenges,
}: {
    brokerage: string;
    metrics: ChallengeMetric[];
    challenges: ChallengeRow[];
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    return (
        <>
            <Head title={t('pods.challenges.broker.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('pods.challenges.broker.title')}
                    description={t('pods.challenges.broker.purpose')}
                />
                <p className="text-base text-muted-foreground">{brokerage}</p>
                <ChallengeForm
                    metrics={metrics}
                    busy={busy}
                    onCreate={(values, reset) => {
                        setBusy(true);
                        router.post(store().url, values, {
                            preserveScroll: true,
                            onSuccess: () => {
                                reset();
                                toast.success(t('pods.challenges.created'));
                            },
                            onError: () =>
                                toast.error(t('pods.challenges.errors.dates')),
                            onFinish: () => setBusy(false),
                        });
                    }}
                />
                <ChallengeList challenges={challenges} />
            </div>
        </>
    );
}
