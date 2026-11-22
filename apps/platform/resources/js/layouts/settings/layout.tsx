import { edit as editPrivacy } from '@/routes/consents';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { PageTabs } from '@/components/page-tabs';
import { edit as editAppearance } from '@/routes/appearance';
import { index as billing } from '@/routes/billing';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

/**
 * Settings (web journey screen 23): "Account, roles, privacy and
 * notifications", one page per tab. Super-admin areas live in the back
 * office menu, not here.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation();

    return (
        <div className="space-y-6 px-4 py-6 md:px-8">
            <PageHeader
                title={t('nav.settings')}
                description={t('nav.settingsDescription')}
            />

            <PageTabs
                label={t('nav.settings')}
                tabs={[
                    { title: t('nav.profile'), href: edit() },
                    { title: t('nav.security'), href: editSecurity() },
                    { title: t('nav.privacy'), href: editPrivacy() },
                    { title: t('nav.appearance'), href: editAppearance() },
                    { title: t('nav.planAndBilling'), href: billing() },
                ]}
            />

            <section className="max-w-2xl space-y-12">{children}</section>
        </div>
    );
}
