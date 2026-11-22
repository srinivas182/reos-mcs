import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /**
             * The state you're working in (the journey's state chip), and the
             * states you can switch to (A5.1; more than one shows the switcher).
             */
            region: {
                code: string;
                name: string;
                options?: { code: string; name: string }[];
            } | null;
            sidebarOpen: boolean;
            /** The tenant's brand (A4.2a): the role in use, or the tenant whose address this is. */
            brand: import('@/types/brand').BrandProps | null;
            /** REOS support inside a tenant with the owner's consent (A4.1c). */
            supportSession: { tenant: string; ends_at: string | null } | null;
            /** Your connections that need a reconnect (B3.1). */
            connectionAlerts: import('@/components/connection-alert').ConnectionAlertItem[];
            whatsNew: import('@/components/whats-new').WhatsNewItem | null;
            messages: { unread: number; user: number } | null;
            /** Live states' names, for the public site's state chip. */
            liveStates: string[];
            /** One-time notices after a redirect. */
            flash: {
                joined: boolean;
            };
            [key: string]: unknown;
        };
    }
}
