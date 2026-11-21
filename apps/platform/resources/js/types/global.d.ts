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
            [key: string]: unknown;
        };
    }
}
