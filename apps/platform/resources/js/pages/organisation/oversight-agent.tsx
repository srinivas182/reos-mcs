import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { OversightProfileView } from '@/components/brokerage/oversight-board';
import type { OversightProfile } from '@/components/brokerage/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/oversight';

const DEFAULTS = { quiet_days: 14, no_deal_days: 90, new_days: 90 };

/** An agent as their broker sees them (A16.1): counts and stages, never clients. */
export default function OversightAgent({
    profile,
    settings = DEFAULTS,
}: {
    profile: OversightProfile;
    settings?: typeof DEFAULTS;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={profile.agent.name} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={profile.agent.name}
                    description={
                        profile.agent.team ?? t('brokerage.oversight.purpose')
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index().url}>
                                {t('brokerage.oversight.profile.back')}
                            </Link>
                        </Button>
                    }
                />
                <OversightProfileView profile={profile} settings={settings} />
            </div>
        </>
    );
}
