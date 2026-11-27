import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import {
    CampaignForm,
    CampaignList,
    ModeTargetsForm,
} from '@/components/rewards/broker-rewards';
import type {
    Campaign,
    GoalKey,
    Mode,
    ModeTargets,
} from '@/components/rewards/types';
import { goals as saveGoals } from '@/routes/organisation/rewards';
import { end, store } from '@/routes/organisation/rewards/campaigns';

/**
 * Goals and rewards for the brokerage (A13.3a): daily goals per mode (they
 * replace REOS's for its agents) and bonus campaigns it funds.
 */
export default function BrokerRewardsPage({
    brokerage,
    goals,
    modes,
    campaigns,
    multipliers,
}: {
    brokerage: string;
    goals: GoalKey[];
    modes: ModeTargets;
    campaigns: Campaign[];
    multipliers: number[];
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const done = { preserveScroll: true, onFinish: () => setBusy(false) };

    return (
        <>
            <Head title={t('rewards.broker.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('rewards.broker.title')}
                    description={t('rewards.broker.purpose')}
                />
                <p className="text-base text-muted-foreground">{brokerage}</p>
                <section aria-labelledby="targets-title" className="space-y-3">
                    <div className="space-y-1">
                        <h2
                            id="targets-title"
                            className="text-lg font-semibold"
                        >
                            {t('rewards.broker.targets')}
                        </h2>
                        <p className="text-base text-muted-foreground">
                            {t('rewards.broker.targetsHint')}
                        </p>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        {(Object.keys(modes) as Mode[]).map((mode) => (
                            <ModeTargetsForm
                                key={mode}
                                mode={mode}
                                value={modes[mode]}
                                goals={goals}
                                busy={busy}
                                onSave={(m, targets) => {
                                    setBusy(true);
                                    router.put(
                                        saveGoals(m).url,
                                        { targets },
                                        done,
                                    );
                                }}
                            />
                        ))}
                    </div>
                </section>
                <section
                    aria-labelledby="campaigns-title"
                    className="space-y-3"
                >
                    <div className="space-y-1">
                        <h2
                            id="campaigns-title"
                            className="text-lg font-semibold"
                        >
                            {t('rewards.campaignsAdmin.title')}
                        </h2>
                        <p className="text-base text-muted-foreground">
                            {t('rewards.campaignsAdmin.purpose')}
                        </p>
                    </div>
                    <CampaignForm
                        goals={goals}
                        multipliers={multipliers}
                        busy={busy}
                        onCreate={(values, reset) => {
                            setBusy(true);
                            router.post(store().url, values, {
                                ...done,
                                onSuccess: () => reset(),
                                onError: () =>
                                    toast.error(t('rewards.errors.dates')),
                            });
                        }}
                    />
                    <CampaignList
                        campaigns={campaigns}
                        busy={busy}
                        onEnd={(c) => {
                            setBusy(true);
                            router.post(
                                end(c.id).url,
                                {},
                                {
                                    ...done,
                                    onSuccess: () =>
                                        toast.success(
                                            t(
                                                'rewards.campaignsAdmin.endedToast',
                                            ),
                                        ),
                                },
                            );
                        }}
                    />
                </section>
            </div>
        </>
    );
}
