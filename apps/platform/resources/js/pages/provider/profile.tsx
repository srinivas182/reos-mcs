import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { ProfileBuilder } from '@/components/providers/profile-builder';
import type { ProfileBuilderData } from '@/components/providers/types';
import { bio, photo, update } from '@/routes/provider-profile';

const routes = { save: update().url, bio: bio().url, photo: photo().url };

/** Build your profile (B15.3, provider screen 7), with the preview agents see. */
export default function ProviderProfile({
    builder,
}: {
    builder: ProfileBuilderData;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('providers.profile.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('providers.profile.title')}
                    description={t('providers.profile.purpose')}
                />
                <ProfileBuilder builder={builder} routes={routes} />
            </div>
        </>
    );
}
