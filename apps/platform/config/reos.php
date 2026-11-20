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

];
