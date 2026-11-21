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
            /** The state you're working in (the journey's state chip). */
            region: { code: string; name: string } | null;
            sidebarOpen: boolean;
            /** Live states' names, for the public site's state chip. */
            liveStates: string[];
            /** One-time notices after a redirect. */
            flash: {
                joined: boolean;
                /** After connecting a tool on the website. */
                connection: {
                    outcome: 'connected' | 'cancelled' | 'failed';
                    integration: string;
                    reason: string | null;
                } | null;
            };
            [key: string]: unknown;
        };
    }
}
