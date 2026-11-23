import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { SpeedRound } from '@/components/practice/speed-round';

/** The speed round on the web (the phone's screen 50, A9.2d). */
export default function SpeedPage({ best }: { best: number | null }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('practice.speed.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.speed.title')}
                    description={t('practice.speed.description')}
                />
                <SpeedRound best={best} />
            </div>
        </>
    );
}
