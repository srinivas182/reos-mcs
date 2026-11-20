import { beforeEach, describe, expect, it, vi } from 'vitest';

const sentryInit = vi.fn();
const posthog = {
    init: vi.fn(),
    capture: vi.fn(),
    identify: vi.fn(),
    reset: vi.fn(),
};

vi.mock('@sentry/react', () => ({ init: sentryInit }));
vi.mock('posthog-js', () => ({ default: posthog }));

const { identify, resetMonitoring, startMonitoring, trackPageView } =
    await import('@/lib/monitoring');

const off = {
    environment: 'production',
    release: '1.0.0',
    sentry: null,
    posthog: null,
};

describe('monitoring', () => {
    beforeEach(() => {
        resetMonitoring();
        vi.clearAllMocks();
    });

    it('loads nothing when everything is switched off', async () => {
        await startMonitoring(off);
        trackPageView('/dashboard');

        expect(sentryInit).not.toHaveBeenCalled();
        expect(posthog.init).not.toHaveBeenCalled();
    });

    it('starts Sentry without personal data', async () => {
        await startMonitoring({
            ...off,
            sentry: { dsn: 'https://key@o1.ingest.sentry.io/2' },
        });

        expect(sentryInit).toHaveBeenCalledWith(
            expect.objectContaining({
                dsn: 'https://key@o1.ingest.sentry.io/2',
                environment: 'production',
                release: '1.0.0',
                dataCollection: expect.objectContaining({
                    userInfo: false,
                    cookies: false,
                    urlQueryParams: false,
                }),
            }),
        );
    });

    it('starts PostHog privately and tracks page views without query strings', async () => {
        await startMonitoring({
            ...off,
            posthog: { key: 'phc_key', host: 'https://us.i.posthog.com' },
        });

        expect(posthog.init).toHaveBeenCalledWith(
            'phc_key',
            expect.objectContaining({
                api_host: 'https://us.i.posthog.com',
                disable_session_recording: true,
                mask_all_text: true,
                person_profiles: 'identified_only',
            }),
        );

        trackPageView('/contacts?search=Maria');
        expect(posthog.capture).toHaveBeenCalledWith('$pageview', {
            $current_url: `${window.location.origin}/contacts`,
        });
    });

    it('identifies people by REOS id only, and forgets them on sign-out', async () => {
        await startMonitoring({
            ...off,
            posthog: { key: 'phc_key', host: 'https://us.i.posthog.com' },
        });

        identify(42);
        identify(42);
        identify(null);

        expect(posthog.identify).toHaveBeenCalledTimes(1);
        expect(posthog.identify).toHaveBeenCalledWith('42');
        expect(posthog.reset).toHaveBeenCalledTimes(1);
    });

    it('starts once per configuration', async () => {
        const config = { ...off, sentry: { dsn: 'https://k@s.io/1' } };

        await startMonitoring(config);
        await startMonitoring(config);

        expect(sentryInit).toHaveBeenCalledTimes(1);
    });
});
