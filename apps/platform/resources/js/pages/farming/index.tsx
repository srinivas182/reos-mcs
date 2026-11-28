import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { FarmingView } from '@/components/farming/farming-view';
import type { FarmArea } from '@/components/farming/types';
import { PageHeader } from '@/components/page-header';
import { check } from '@/routes/compliance';
import { destroy, draft, send, store } from '@/routes/farming';

const routes = {
    create: store().url,
    remove: (id: string) => destroy(id).url,
    draft: (id: string) => draft(id).url,
    send: (id: string) => send(id).url,
    check: check().url,
};

/** Farming (B14.3c): a monthly market update for each area, from MLS figures, checked for compliance. */
export default function FarmingPage({
    areas,
    tags,
}: {
    areas: FarmArea[];
    tags: string[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('leads.farm.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('leads.farm.title')}
                    description={t('leads.farm.purpose')}
                />
                <FarmingView areas={areas} tags={tags} routes={routes} />
            </div>
        </>
    );
}
