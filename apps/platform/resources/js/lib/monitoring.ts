/**
 * Browser error tracking (Sentry) and product analytics (PostHog), switched
 * on and off by the super admin in Settings › Integrations (Sprint 2.4c).
 *
 * The server shares only public values (DSN, PostHog project key). Each SDK
 * is loaded on demand, so when a service is off the browser downloads none
 * of its code. Privacy defaults: no personal data in error reports, text and
 * inputs masked, no session recording, people identified only by their REOS
 * id (never name or email).
 */
export type MonitoringConfig = {
    environment: string;
    release: string;
    sentry: { dsn: string } | null;
    posthog: { key: string; host: string } | null;
};

type Analytics = {
    capture: (event: string, properties?: Record<string, unknown>) => void;
    identify: (id: string) => void;
    reset: () => void;
};

let started = '';
let analytics: Analytics | null = null;
let identified: string | null = null;

/** Starts whatever is switched on. Safe to call on every page; runs once per config. */
export async function startMonitoring(
    config: MonitoringConfig | undefined,
): Promise<void> {
    const key = JSON.stringify(config ?? null);

    if (config === undefined || key === started) {
        return;
    }

    started = key;

    if (config.sentry) {
        const Sentry = await import('@sentry/react');
        Sentry.init({
            dsn: config.sentry.dsn,
            environment: config.environment,
            release: config.release,
            // No personal data: no user info, cookies, headers, bodies or query strings.
            dataCollection: {
                userInfo: false,
                cookies: false,
                httpHeaders: false,
                httpBodies: [],
                urlQueryParams: false,
            },
            tracesSampleRate: 0,
        });
    }

    if (config.posthog) {
        const { default: posthog } = await import('posthog-js');
        posthog.init(config.posthog.key, {
            api_host: config.posthog.host,
            person_profiles: 'identified_only',
            capture_pageview: false,
            disable_session_recording: true,
            mask_all_text: true,
            mask_all_element_attributes: true,
            respect_dnt: true,
        });
        analytics = posthog;
    }
}

/** One page view per Inertia visit (the URL path only, no query string). */
export function trackPageView(path: string): void {
    analytics?.capture('$pageview', {
        $current_url: `${window.location.origin}${path.split('?')[0]}`,
    });
}

/** Links analytics to the signed-in person by REOS id only, or forgets them. */
export function identify(userId: number | null): void {
    if (analytics === null) {
        return;
    }

    const id = userId === null ? null : String(userId);

    if (id === identified) {
        return;
    }

    if (id === null) {
        analytics.reset();
    } else {
        analytics.identify(id);
    }

    identified = id;
}

/** For tests. */
export function resetMonitoring(): void {
    started = '';
    analytics = null;
    identified = null;
}
