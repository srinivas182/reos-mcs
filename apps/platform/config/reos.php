<?php

declare(strict_types=1);

return [
    /*
    | Platform version reported by /api/v1/health and shown in the apps.
    | Set by the release pipeline; defaults to the development build.
    */
    'version' => env('REOS_VERSION', '0.1.0-dev'),
];
