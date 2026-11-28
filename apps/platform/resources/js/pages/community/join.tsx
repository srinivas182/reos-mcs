import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { JoinCard } from '@/components/communities/community';
import type { CommunityInvite } from '@/components/communities/types';
import { PageHeader } from '@/components/page-header';
import { show } from '@/routes/communities';
import { store } from '@/routes/communities/join';

/** The invite to a community (B17.2, provider screen 16), from its link or QR code. */
export default function CommunityJoin({
    invite,
    code,
}: {
    invite: CommunityInvite;
    code: string;
}) {
    const { t } = useTranslation();
    const via =
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('via') === 'qr'
            ? 'qr'
            : 'link';

    return (
        <>
            <Head title={t('communities.join.title', { name: invite.name })} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('communities.join.title', { name: invite.name })}
                    description={t('communities.join.purpose', {
                        owner: invite.owner,
                    })}
                />
                <JoinCard
                    invite={invite}
                    joinUrl={store(code).url}
                    via={via}
                    openUrl={show(invite.id).url}
                />
            </div>
        </>
    );
}
