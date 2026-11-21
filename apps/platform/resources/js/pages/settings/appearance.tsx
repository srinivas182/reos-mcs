import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppearanceSettings from '@/components/appearance-settings';
import Heading from '@/components/heading';

export default function Appearance() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('appearance.pageTitle')} />

            <h1 className="sr-only">{t('appearance.pageTitle')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('appearance.pageTitle')}
                    description={t('appearance.description')}
                />
                <AppearanceSettings />
            </div>
        </>
    );
}
