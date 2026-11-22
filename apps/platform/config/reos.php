<?php

declare(strict_types=1);

return [

    /*
    | Deployed REOS version, shown by /api/v1/health and in the admin footer.
    | Set by the release pipeline.
    */
    'version' => env('REOS_VERSION', '0.1.0-dev'),

    /*
    | Password for every seeded demo persona (local and staging only; the demo
    | seeder refuses to run in production).
    */
    'demo_password' => env('REOS_DEMO_PASSWORD', 'password'),

    /*
    | Integration Hub (Sprint 2.4, ADR 0009). The catalogue is generated from
    | docs/integrations/register.yaml. Integration keys are encrypted with their
    | own key, never APP_KEY; previous keys stay readable during rotation.
    */
    'integrations' => [
        'catalogue' => resource_path('integrations/catalogue.json'),
        'credentials_key' => env('REOS_CREDENTIALS_KEY'),
        'previous_credentials_keys' => array_values(array_filter(explode(',', (string) env('REOS_CREDENTIALS_PREVIOUS_KEYS', '')))),
    ],

    /*
    | The phone app's identifiers (Apple sign-in tokens are issued to the
    | bundle id). Must match apps/mobile/app.json.
    */
    /*
    | The state REOS launches in (the top bar's state chip). A5.1 replaces this
    | with the person's own states and a state switcher.
    */
    'region' => [
        'code' => env('REOS_REGION_CODE', 'TX'),
        'name' => env('REOS_REGION_NAME', 'Texas'),
    ],

    /*
    | Tenancy (A4.1, ADR 0015): each tenant is reachable at <slug>.<base domain>
    | until it verifies its own domain (A4.2).
    */
    'tenancy' => [
        'base_domain' => env('REOS_TENANT_BASE_DOMAIN', 'reos.com'),
    ],

    'mobile' => [
        'bundle_id' => env('REOS_MOBILE_BUNDLE_ID', 'com.reos.app'),
        // Where browser sign-in returns to the app (scheme in apps/mobile/app.json).
        'redirect' => env('REOS_MOBILE_REDIRECT', 'reos://auth'),
    ],

    'ai' => [
        // AI credits each person may use a month (B4.3 stub; seat pools arrive in Sprint 6).
        'monthly_credits' => (int) env('REOS_AI_MONTHLY_CREDITS', 500),
    ],

    'privacy' => [
        // When docs/privacy/data-map.md was last checked against the database (screen 71).
        'data_map_reviewed' => '2026-11-21',
    ],

    /*
    | Content and the knowledge base (A5.3, ADR 0020). Lesson files upload
    | straight from the browser to this disk with short-lived signed links
    | (S3 in staging and production, SeaweedFS or the local disk elsewhere),
    | under platform/ (REOS content) or tenants/<id>/ (school content, Sprint 7).
    | Every file is virus-scanned by ClamAV before anyone can open it; with no
    | scanner set, uploads are refused in production.
    */
    'content' => [
        'disk' => env('REOS_CONTENT_DISK', env('FILESYSTEM_DISK', 'local')),
        'upload_minutes' => 30,
        'clamav' => [
            'host' => env('CLAMAV_HOST'),
            'port' => (int) env('CLAMAV_PORT', 3310),
            'timeout' => 120,
        ],
    ],

    // Payments (B5.1, B5.2; screen 66). Fees and the refund window are
    // Sprint 5 decisions; change them here, not in code.
    'billing' => [
        // REOS's share of school, instructor and mentor sales (Stripe Connect).
        'platform_fee_percent' => (int) env('REOS_PLATFORM_FEE_PERCENT', 10),
        // Full refund within this many days, if less than a quarter of a course is done.
        'refund_days' => (int) env('REOS_REFUND_DAYS', 7),
        'refund_max_progress_percent' => 25,
    ],

];
