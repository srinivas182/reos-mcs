<?php

namespace App\Providers;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialCipher;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\Credentials\DatabaseCredentialVault;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureIntegrations();
    }

    /**
     * Super-admin access to the Integration Hub (Sprint 3 swaps the flag for
     * the super-admin role), and `{integration}` route values resolved from
     * the register's catalogue.
     */
    protected function configureIntegrations(): void
    {
        Gate::define('manage-integrations', static fn (User $user): bool => $user->is_super_admin);

        Route::bind('integration', fn (string $id) => $this->app->make(Catalogue::class)->find($id)
            ?? throw new NotFoundHttpException);
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
