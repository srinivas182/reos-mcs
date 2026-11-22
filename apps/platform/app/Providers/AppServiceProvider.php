<?php

namespace App\Providers;

use App\Authorization\Permission;
use App\Connections\ConnectorRegistry;
use App\Content\Scanning\ClamAvScanner;
use App\Content\Scanning\FileScanner;
use App\Content\Scanning\Unavailable;
use App\Content\Scanning\UnscannedOutsideProduction;
use App\Events\IntegrationSettingsChanged;
use App\Features\Flags;
use App\Features\FlagUsage;
use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialCipher;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\Credentials\DatabaseCredentialVault;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use App\Models\PersonalAccessToken;
use App\Models\State;
use App\Models\User;
use App\Monitoring\MonitoringRuntime;
use App\Notifications\Centre\Deliveries;
use App\Services\Authorization\ActiveRole;
use App\Services\Connections\SyncEngine;
use App\Sso\TestIdentityProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Catalogue::class, static fn (): Catalogue => new Catalogue(
            config()->string('reos.integrations.catalogue'),
        ));

        $this->app->singleton(CredentialCipher::class, static function (): CredentialCipher {
            $key = config('reos.integrations.credentials_key');
            /** @var list<string> $previous */
            $previous = config()->array('reos.integrations.previous_credentials_keys');

            return new CredentialCipher(is_string($key) ? $key : null, $previous);
        });

        $this->app->bind(CredentialVault::class, DatabaseCredentialVault::class);

        // One per worker, so it remembers which settings it last applied.
        $this->app->singleton(MonitoringRuntime::class);

        // The role in use is worked out once per request.
        $this->app->scoped(ActiveRole::class);
        // Feature flags (A6.2): rollouts remembered for the request; usage counted per request.
        $this->app->scoped(Flags::class);
        $this->app->scoped(FlagUsage::class);
        // Loads its key pair once per process (outside production only).
        $this->app->singleton(TestIdentityProvider::class);

        // Lesson files are virus-scanned by ClamAV (A5.3). Without one, local
        // machines and CI accept files unscanned; production refuses them.
        $this->app->bind(FileScanner::class, function (): FileScanner {
            $host = config('reos.content.clamav.host');

            if (is_string($host) && $host !== '') {
                return new ClamAvScanner($host, config()->integer('reos.content.clamav.port'), config()->integer('reos.content.clamav.timeout'));
            }

            return $this->app->isProduction() ? new Unavailable : new UnscannedOutsideProduction;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureIntegrations();
        $this->configureIdentity();
        $this->configureRoles();
        Flags::define();
        // What each notification sent where (B6.2).
        Event::listen(NotificationSent::class, [Deliveries::class, 'sent']);
        Event::listen(NotificationFailed::class, [Deliveries::class, 'failed']);
    }

    /**
     * Roles and permissions (A3.2): a permission name passed to `can()` is
     * answered by the person's active role, two-step included. Other
     * abilities fall through to policies.
     */
    protected function configureRoles(): void
    {
        Route::model('person', User::class);
        // States are found by their code, in any case ("tx" or "TX").
        Route::bind('state', static fn (string $code): State => State::query()->findOrFail(strtoupper($code)));

        Gate::before(function (User $user, string $ability): ?bool {
            $permission = Permission::tryFrom($ability);

            return $permission === null ? null : $this->app->make(ActiveRole::class)->allows($user, $permission);
        });
    }

    /**
     * Accounts (A3.1): phone tokens expire after 30 days unused; sign-in and
     * phone codes are rate limited; sign-in failures answer in plain words.
     */
    protected function configureIdentity(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Sanctum::authenticateAccessTokensUsing(
            static fn (mixed $token, bool $isValid): bool => $isValid && $token instanceof PersonalAccessToken && ! $token->isIdle(),
        );

        // Connections engine (B3.1): fair sync allowances per owner and per tool, and the webhook inbox.
        RateLimiter::for('connection-owner', static function (object $job): Limit {
            $connection = $job instanceof SyncConnection ? Connection::query()->find($job->connectionId) : null;

            return Limit::perMinute(60)->by('owner:'.($connection?->ownerKey() ?? 'none'));
        });
        RateLimiter::for('connection-vendor', static function (object $job): Limit {
            $connection = $job instanceof SyncConnection ? Connection::query()->find($job->connectionId) : null;
            $allowance = $connection === null ? 60 : app(ConnectorRegistry::class)->requestsPerMinute($connection->integration_id, $connection->mode);

            // Each page is about one request; the job does a few pages per run.
            return Limit::perMinute(max(1, intdiv($allowance, SyncEngine::PAGES_PER_RUN)))->by('vendor:'.($connection->integration_id ?? 'none'));
        });
        RateLimiter::for('webhooks', static fn (Request $request): Limit => Limit::perMinute(600)->by($request->ip()));

        // Email verification links and resends (Fortify), six a minute per person.
        RateLimiter::for('verification', static fn (Request $request): Limit => Limit::perMinute(6)->by('verification:'.($request->user() instanceof User ? $request->user()->id : $request->ip())));

        RateLimiter::for('sign-in', static fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));

        // A code per number every minute (and 5 per hour), and 20 per hour per network: stops SMS pumping.
        RateLimiter::for('phone-code', static function (Request $request): array {
            $phone = hash('sha256', $request->string('phone')->toString());

            return [
                Limit::perMinute(1)->by("phone:{$phone}"),
                Limit::perHour(5)->by("phone-hour:{$phone}"),
                Limit::perHour(20)->by("ip:{$request->ip()}"),
            ];
        });
    }

    /**
     * `{integration}` route values resolved from the register's catalogue.
     */
    protected function configureIntegrations(): void
    {

        Route::bind('integration', fn (string $id) => $this->app->make(Catalogue::class)->find($id)
            ?? throw new NotFoundHttpException);

        // Monitoring (2.4c): forget cached settings when an integration
        // changes; queue workers apply them before each job, like requests do.
        Event::listen(IntegrationSettingsChanged::class, fn () => $this->app->make(MonitoringRuntime::class)->forget());
        Queue::before(fn () => $this->app->make(MonitoringRuntime::class)->apply());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
