import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { FixPlan } from '@/components/practice/fix-plan';
import type { TopicPlan } from '@/components/practice/types';

/** Fix a weak topic on the web (the phone's screen 46, A9.2b). */
export default function FixPage({ plan }: { plan: TopicPlan }) {
    const { t } = useTranslation();
    const { url } = usePage();
    const title = t('practice.fix.title', {
        topic: t(`practice.topics.${plan.topic}`),
    });

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={title}
                    description={t('practice.fix.description')}
                />
                <FixPlan plan={plan} showMisses={url.includes('misses=1')} />
            </div>
        </>
    );
}
