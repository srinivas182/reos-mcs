<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An integration's keys, mode or pause changed. Anything that caches what
 * runs where (the monitoring runtime, later each integration's driver)
 * listens and forgets its copy.
 */
final readonly class IntegrationSettingsChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public string $integrationId) {}
}
