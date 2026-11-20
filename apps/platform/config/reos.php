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

];
