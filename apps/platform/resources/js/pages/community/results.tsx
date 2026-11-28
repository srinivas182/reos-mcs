import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ResultsView } from '@/components/communities/owner';
import type { CommunityResults } from '@/components/communities/types';
import { PageHeader } from '@/components/page-header';

/** Results (B17.2, provider screen 18): members, how they joined, posts, sessions and attendance. */
export default function CommunityResultsPage({
    results,
}: {
    results: CommunityResults;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('communities.results.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('communities.results.title')}
                    description={t('communities.results.purpose')}
                />
                <ResultsView results={results} />
            </div>
        </>
    );
}
