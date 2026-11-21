<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\ResolveTenant;
use App\Models\Organisation;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Wires tenant isolation into the request, the database, commands and the
 * queue (ADR 0015).
 */
final class TenancyServiceProvider extends ServiceProvider
{
    /** API requests per minute for people without a tenant, per person (or network when signed out). */
    public const PERSONAL_RATE_LIMIT = 300;

    /** @var list<array{0: int|null, 1: bool}> States to restore after each job (the sync queue runs jobs inside a request). */
    private array $beforeJobs = [];

    public function register(): void
    {
        // Fresh (no tenant) for every request and job; middleware and the
        // queue listeners below choose the state.
        $this->app->scoped(Tenancy::class, static fn (): Tenancy => new Tenancy);
        // One instance per request, so terminate() sees what handle() saved.
        $this->app->scoped(ResolveTenant::class);
    }

    public function boot(): void
    {
        // A new database connection (first use, or after a reconnect) carries
        // the current state from the moment it opens; a session without it
        // sees no tenant rows.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $this->app->make(Tenancy::class)->applyTo($event->connection);
        });

        // Artisan commands (migrations, seeders, schedulers, workers) are platform work.
        Event::listen(CommandStarting::class, function (): void {
            $this->app->make(Tenancy::class)->enterPlatform();
        });

        // The API limit is per tenant, from its own settings (screen 60).
        RateLimiter::for('api', function (Request $request): Limit {
            $tenancy = $this->app->make(Tenancy::class);
            $tenant = $tenancy->tenant();

            if ($tenancy->isPlatform()) {
                return Limit::none();
            }

            if ($tenant !== null) {
                $organisation = Organisation::query()->find($tenant);

                return Limit::perMinute($organisation?->tenantLimits()->rateLimit ?? 600)->by('tenant:'.$tenant);
            }

            $user = $request->user('sanctum');

            return Limit::perMinute(self::PERSONAL_RATE_LIMIT)->by($user instanceof User ? 'user:'.$user->id : 'ip:'.$request->ip());
        });

        // Jobs run as whoever queued them.
        Queue::createPayloadUsing(fn (): array => ['reos_tenancy' => $this->app->make(Tenancy::class)->forJob()]);

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $tenancy = $this->app->make(Tenancy::class);
            $this->beforeJobs[] = $tenancy->snapshot();
            $recorded = $event->job->payload()['reos_tenancy'] ?? null;
            $tenancy->enterJob(is_string($recorded) ? $recorded : null);
        });

        $after = function (): void {
            $state = array_pop($this->beforeJobs);

            if ($state !== null) {
                $this->app->make(Tenancy::class)->restore($state);
            }
        };

        Event::listen(JobProcessed::class, $after);
        Event::listen(JobExceptionOccurred::class, $after);
    }
}
