import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { CommunityList } from '@/components/communities/community';
import type { CommunityCard } from '@/components/communities/types';
import { PageHeader } from '@/components/page-header';
import { show } from '@/routes/communities';

/** Communities I've joined (B17.2). */
export default function CommunitiesMine({
    communities,
}: {
    communities: CommunityCard[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('communities.mineTitle')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('communities.mineTitle')}
                    description={t('communities.minePurpose')}
                />
                <CommunityList
                    communities={communities}
                    href={(id) => show(id).url}
                />
            </div>
        </>
    );
}
