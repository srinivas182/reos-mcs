import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { DailyGoalsCard } from '@/components/rewards/daily-goals';
import { RewardsPanel } from '@/components/rewards/rewards-panel';
import type { RewardsSummary, Today } from '@/components/rewards/types';
import { index as mentorsIndex } from '@/routes/mentors';
import { redeem } from '@/routes/rewards';

/**
 * Goals and rewards (A13.3a; agent screens 111 and 112): today's goals and
 * points, the streak and Share, the balance and what points pay for, and
 * recent points.
 */
export default function RewardsPage({
    today,
    rewards,
}: {
    today: Today;
    rewards: RewardsSummary;
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    const share = async (text: string) => {
        try {
            if (typeof navigator.share === 'function') {
                await navigator.share({ text });

                return;
            }
            await navigator.clipboard.writeText(text);
            toast.success(t('rewards.copied'));
        } catch {
            // The person closed the share sheet.
        }
    };

    return (
        <>
            <Head title={t('rewards.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('rewards.title')}
                    description={t('rewards.purpose')}
                />
                <DailyGoalsCard
                    today={today}
                    onShare={(text) => void share(text)}
                />
                <RewardsPanel
                    rewards={rewards}
                    busy={busy}
                    mentorsHref={mentorsIndex().url}
                    onRedeem={(o) => {
                        setBusy(true);
                        router.post(
                            redeem().url,
                            { price: o.price },
                            {
                                preserveScroll: true,
                                onFinish: () => setBusy(false),
                            },
                        );
                    }}
                />
            </div>
        </>
    );
}
