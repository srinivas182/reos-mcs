import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { CommunityPanel } from '@/components/communities/community';
import type { CommunityView } from '@/components/communities/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { communityRoutes } from '@/lib/communities';
import { deleteJson } from '@/lib/content';
import { index, leave } from '@/routes/communities';

/** A community as a member sees it (B17.2, provider screen 17). */
export default function CommunityShow({
    community,
}: {
    community: CommunityView;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={community.name} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={community.name}
                    description={t('communities.ledBy', {
                        name: community.owner,
                    })}
                    actions={
                        community.is_owner ? null : (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    void deleteJson(
                                        leave(community.id).url,
                                    ).then(() => {
                                        toast.success(
                                            t('communities.left', {
                                                name: community.name,
                                            }),
                                        );
                                        router.visit(index().url);
                                    })
                                }
                            >
                                {t('communities.leave')}
                            </Button>
                        )
                    }
                />
                <CommunityPanel
                    community={community}
                    routes={communityRoutes(community.id)}
                    onChanged={() => router.reload({ only: ['community'] })}
                />
            </div>
        </>
    );
}
