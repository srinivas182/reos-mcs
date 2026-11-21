<?php

namespace Tests;

use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests set up data the way Artisan commands do: in platform scope.
     * Each HTTP request then resolves its own tenant (ADR 0015) and puts
     * platform scope back when it ends.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Tenancy::class)->enterPlatform();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
