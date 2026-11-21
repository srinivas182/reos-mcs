<?php

namespace App\Providers;

use App\Authorization\Permission;
use App\Events\IntegrationSettingsChanged;
use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialCipher;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\Credentials\DatabaseCredentialVault;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Monitoring\MonitoringRuntime;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }

    /**
     * Roles and permissions (A3.2): a permission name passed to `can()` is
     * answered by the person's active role, two-step included. Other
     * abilities fall through to policies.
     */
    protected function configureRoles(): void
    {
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
