import { createInertiaApp, router } from '@inertiajs/react';
import { I18nextProvider } from 'react-i18next';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme, setSignedIn } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import { rememberTimezone } from '@/lib/calendar';
import { i18n } from '@/lib/i18n';
import type { MonitoringConfig } from '@/lib/monitoring';
import { identify, startMonitoring, trackPageView } from '@/lib/monitoring';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { configureRealtime } from '@/lib/realtime';

// Live messages through Reverb (B6.1), when this build has its key.
configureRealtime();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'connections/simulator' ||
                name === 'billing/checkout' ||
                name === 'billing/complete' ||
                name === 'payouts/simulate':
                return null;
            case name === 'welcome' ||
                name.startsWith('public/') ||
                name.startsWith('get-started/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <I18nextProvider i18n={i18n}>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </I18nextProvider>
        );
    },
    progress: {
        // REOS action blue (packages/tokens).
        color: '#2952CC',
    },
});

// Applies the server-rendered theme and text size, then keeps the account in
// sync: preference changes are saved to the account only when signed in.
initializeTheme();
rememberTimezone();
router.on('navigate', (event) => {
    const { page } = event.detail;
    const props = page.props as {
        auth?: { user?: { id: number } | null };
        monitoring?: MonitoringConfig;
    };

    setSignedIn(Boolean(props.auth?.user));

    // Error tracking and analytics, if switched on in Settings › Integrations.
    void startMonitoring(props.monitoring).then(() => {
        identify(props.auth?.user?.id ?? null);
        trackPageView(page.url);
    });
});
