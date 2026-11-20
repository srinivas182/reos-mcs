import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppearanceSettings from '@/components/appearance-settings';
import Heading from '@/components/heading';
import { i18n } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';

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

Appearance.layout = {
    breadcrumbs: [
        {
            get title() {
                return i18n.t('appearance.pageTitle');
            },
            href: editAppearance(),
        },
    ],
};
