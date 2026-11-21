<?php

declare(strict_types=1);

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\DemoTenants;
use Database\Seeders\Demo\Personas;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds one verified login for every journey persona', function () {
    $this->seed(DatabaseSeeder::class);

    $personas = Personas::all();

    expect(User::query()->count())->toBe(count($personas));

    foreach ($personas as $persona) {
        $user = User::query()->where('email', $persona->email)->sole();

        expect($user->name)->toBe($persona->name)
            ->and($user->hasVerifiedEmail())->toBeTrue();
    }
});

it('covers every role with a unique e-mail on the reserved .test domain', function () {
    $personas = collect(Personas::all());

    expect($personas->pluck('role')->duplicates())->toBeEmpty()
        ->and($personas->pluck('email')->duplicates())->toBeEmpty()
        ->and($personas->pluck('role')->all())->toContain(
            'candidate', 'agent', 'broker', 'mentor', 'instructor', 'school_admin', 'provider', 'association_admin', 'super_admin',
        );

    $personas->each(fn ($persona) => expect($persona->email)->toEndWith('@demo.reos.test'));
});

it('is safe to run more than once', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(User::query()->count())->toBe(count(Personas::all()));
});

it('lets a seeded persona sign in with the demo password', function () {
    $this->seed(DemoSeeder::class);

    $this->post(route('login.store'), [
        'email' => 'sofia.reyes@demo.reos.test',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('refuses to seed demo logins in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // Call the seeder itself: `db:seed` would stop at its production prompt first.
    expect(fn () => app(DemoSeeder::class)->setContainer(app())->__invoke())
        ->toThrow(RuntimeException::class, 'never be seeded in production');

    expect(User::query()->count())->toBe(0);
});

it('skips demo logins when the full seeder runs in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(User::query()->count())->toBe(0);
});

it('makes only the operations persona a super admin', function () {
    $this->seed(DatabaseSeeder::class);

    $admins = app(Memberships::class)->holders(Role::SuperAdmin)->pluck('email')->all();

    expect($admins)->toBe(['ops@demo.reos.test']);
});

it('gives each persona their roles in their organisation, and can run again', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $roles = fn (string $email): array => array_map(
        static fn (Membership $m): string => "{$m->role->value} @ {$m->organisation->name}",
        app(Memberships::class)->of(User::query()->where('email', $email)->sole()),
    );

    expect($roles('sofia.reyes@demo.reos.test'))->toBe(['candidate @ Texas Realty School'])
        ->and($roles('linda.park@demo.reos.test'))->toBe(['broker @ Lone Star Realty', 'agent @ Lone Star Realty'])
        ->and($roles('karen.webb@demo.reos.test'))->toBe(['association_admin @ Texas REALTORS®'])
        ->and($roles('ops@demo.reos.test'))->toBe(['super_admin @ REOS platform'])
        ->and(Organisation::query()->count())->toBe(count(Personas::organisations()) + count(DemoTenants::EXTRA));

    foreach (Personas::all() as $persona) {
        expect($roles($persona->email))->not->toBeEmpty();
    }
});
