import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PageBrandStyle } from '@/components/brand-style';
import { ConnectionAlerts } from '@/components/connection-alert';
import { SupportSessionBanner } from '@/components/support-session-banner';
import { AdminTwoStepBanner } from '@/components/two-step-notice';
import type { AppLayoutProps } from '@/types';

/**
 * The signed-in web app: the journey's sidebar and top bar around each page.
 * Pages show their own title (and a back link where needed), not breadcrumbs.
 */
export default function AppSidebarLayout({ children }: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <PageBrandStyle />
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <SupportSessionBanner />
                <AppSidebarHeader />
                <AdminTwoStepBanner />
                <ConnectionAlerts />
                {children}
            </AppContent>
        </AppShell>
    );
}
