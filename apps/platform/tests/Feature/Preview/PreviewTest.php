<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** A CSV file of pilot users. */
function pilotCsv(string $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'pilots');
    file_put_contents($path, "participant,name,email,role,language\n".$rows);

    return $path;
}

describe('the preview (P7.0)', function () {
    it('is off unless switched on, and never in production', function () {
        expect(config('reos.preview'))->toBeFalse();
        $this->getJson('/api/v1/app-config')->assertJsonPath('preview', null);

        config(['reos.preview' => true]);
        $this->getJson('/api/v1/app-config')->assertJsonPath('preview', ['code' => '123456']);

        app()->detectEnvironment(fn () => 'production');
        $this->getJson('/api/v1/app-config')->assertJsonPath('preview', null);
    });

    it('shows the banner and uses one known code for phone verification', function () {
        config(['reos.preview' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/notifications')->assertInertia(fn (Assert $page) => $page->where('preview.code', '123456'));

        $this->actingAs($user)->post('/settings/notifications/phone', ['phone' => '+15125550100'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/settings/notifications/phone/verify', ['phone' => '+15125550100', 'code' => '123456'])->assertSessionHasNoErrors();
        expect($user->fresh()?->phone_verified_at)->not->toBeNull();
    });

    it('creates pilot accounts next to the journey personas, with their own passwords', function () {
        config(['reos.preview' => true]);
        $this->seed(DemoSeeder::class);
        $csv = pilotCsv("P01,Maria Lopez,maria@pilot.test,candidate,es\nP02,Tom Reed,TOM@pilot.test,broker,en\n");

        $this->artisan('reos:preview:pilots', ['file' => $csv])
            ->expectsOutput('participant,email,password')
            ->assertSuccessful();

        $maria = User::query()->where('email', 'maria@pilot.test')->firstOrFail();
        $tom = User::query()->where('email', 'tom@pilot.test')->firstOrFail();
        $school = Organisation::query()->where('name', 'Texas Realty School')->firstOrFail();
        $loneStar = Organisation::query()->where('name', 'Lone Star Realty')->firstOrFail();
        $roles = fn (User $u) => collect(app(Memberships::class)->of($u))->map(fn ($m) => [$m->role->value, $m->organisation->id])->all();

        expect($maria->locale->value)->toBe('es')
            ->and($maria->email_verified_at)->not->toBeNull()
            ->and(Hash::check(config('reos.demo_password'), $maria->password))->toBeFalse()
            ->and($roles($maria))->toBe([[Role::Candidate->value, $school->id]])
            ->and($roles($tom))->toEqualCanonicalizing([[Role::Broker->value, $loneStar->id], [Role::Agent->value, $loneStar->id]]);
    });

    it('refuses outside the preview, and checks every row first', function () {
        $this->seed(DemoSeeder::class);
        $this->artisan('reos:preview:pilots', ['file' => pilotCsv("P01,Maria Lopez,maria@pilot.test,candidate,es\n")])->assertFailed();

        config(['reos.preview' => true]);
        $this->artisan('reos:preview:pilots', ['file' => pilotCsv("P01,Maria Lopez,maria@pilot.test,candidate,es\nP02,Root,root@pilot.test,super_admin,en\n")])->assertFailed();
        $this->artisan('reos:preview:pilots', ['file' => pilotCsv("P03,Ana,not-an-email,agent,en\n")])->assertFailed();
        expect(User::query()->where('email', 'maria@pilot.test')->exists())->toBeFalse();
        Cache::flush();
    });
});
