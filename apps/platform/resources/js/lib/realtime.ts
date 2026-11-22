import { configureEcho, echoIsConfigured } from '@laravel/echo-react';

/**
 * Live updates through Laravel Reverb (B6.1, ADR 0023). Set up once when
 * the app starts, if this build has a Reverb key; elsewhere (tests,
 * Storybook, builds without Reverb) pages refresh on navigation instead.
 */
export function configureRealtime(): void {
    if (import.meta.env.VITE_REVERB_APP_KEY) {
        configureEcho({ broadcaster: 'reverb' });
    }
}

/** Whether live updates are on in this page. */
export const realtimeEnabled = (): boolean => echoIsConfigured();
