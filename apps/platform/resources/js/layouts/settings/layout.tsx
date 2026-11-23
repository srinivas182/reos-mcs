import { edit as editPrivacy } from '@/routes/consents';
import { edit as editNotifications } from '@/routes/notification-settings';
import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { PageTabs } from '@/components/page-tabs';
import { activeMembership } from '@/lib/roles';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editAvatar } from '@/routes/avatar';
import { index as billing } from '@/routes/billing';
import { edit as editGoal } from '@/routes/goal';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

/**
 * Settings (web journey screen 23): "Account, roles, privacy and
 * notifications", one page per tab. Super-admin areas live in the back
 * office menu, not here.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    // Instructors at a school give or withdraw avatar consent here (A7.1b).
    const instructor = activeMembership(auth.roles)?.role === 'instructor';
    // Agents change their goal (mode) and flags here (A11.1a, screen 146).
    const agent = activeMembership(auth.roles)?.role === 'agent';

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
                    ...(agent
                        ? [{ title: t('nav.goal'), href: editGoal() }]
                        : []),
                    { title: t('nav.security'), href: editSecurity() },
                    { title: t('nav.privacy'), href: editPrivacy() },
                    {
                        title: t('nav.notifications'),
                        href: editNotifications(),
                    },
                    ...(instructor
                        ? [{ title: t('nav.avatar'), href: editAvatar() }]
                        : []),
                    { title: t('nav.appearance'), href: editAppearance() },
                    { title: t('nav.planAndBilling'), href: billing() },
                ]}
            />

            <section className="max-w-2xl space-y-12">{children}</section>
        </div>
    );
}
