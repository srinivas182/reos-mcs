<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\TenantDomain;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Custom domains and Brokerage settings (A4.2b, web screens 41 and 57). */
function loneStar(): Organisation
{
    return Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'slug' => 'lone-star']);
}

function brokerOf(Organisation $brokerage): User
{
    return User::factory()->withRole(Role::Broker, $brokerage)->withTwoFactor()->create(['name' => 'Linda Park']);
}

function domainOf(Organisation $tenant): ?TenantDomain
{
    return app(Tenancy::class)->asPlatform(fn () => TenantDomain::query()->where('organisation_id', $tenant->id)->first());
}

describe('adding a domain', function () {
    it('adds it with the DNS records to set, audited', function () {
        $lone = loneStar();

        $this->actingAs(brokerOf($lone))->post('/organisation/domain', ['hostname' => 'https://Learn.LoneStarRealty.com/'])->assertRedirect();

        $domain = domainOf($lone);
        expect($domain?->hostname)->toBe('learn.lonestarrealty.com')
            ->and($domain?->live())->toBeFalse()
            ->and($domain?->records)->toHaveCount(2)
            ->and($domain?->records[0])->toMatchArray(['type' => 'CNAME', 'name' => 'learn.lonestarrealty.com', 'value' => 'tenants.reos.com'])
            ->and($domain?->records[1]['name'])->toBe('_reos-verify.learn.lonestarrealty.com');
        expect(AuditEvent::query()->where('action', 'domain.added')->sole()->organisation_id)->toBe($lone->id);
    });

    it('refuses addresses that aren’t domains, REOS addresses, a second domain and taken ones', function () {
        $lone = loneStar();
        $linda = brokerOf($lone);

        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'lonestar'])->assertSessionHasErrors('hostname');
        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'learn.reos.com'])->assertSessionHasErrors(['hostname' => __('domains.errors.reos')]);

        $other = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
        $this->actingAs(brokerOf($other))->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com']);
        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com'])->assertSessionHasErrors(['hostname' => __('domains.errors.taken')]);

        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'app.lonestarrealty.com'])->assertSessionHasNoErrors();
        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'www.lonestarrealty.com'])->assertSessionHasErrors(['hostname' => __('domains.errors.one')]);
    });

    it('is for the tenant’s admins only', function () {
        $lone = loneStar();
        $this->actingAs(User::factory()->withRole(Role::Agent, $lone)->create())->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com'])->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com'])->assertNotFound();
        expect(domainOf($lone))->toBeNull();
    });
});

describe('verifying and removing', function () {
    it('goes live on Verify DNS, audited once, and brands sign-in on that domain', function () {
        $lone = loneStar();
        $lone->forceFill(['brand' => ['primary' => '#0F766E']])->save();
        $linda = brokerOf($lone);
        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com']);

        auth()->logout();
        $this->get('http://learn.lonestarrealty.com/login')->assertInertia(fn (Assert $page) => $page->where('brand', null));

        $this->actingAs($linda)->post('/organisation/domain/verify')->assertRedirect();
        $this->actingAs($linda)->post('/organisation/domain/verify');

        expect(domainOf($lone)?->live())->toBeTrue()
            ->and(domainOf($lone)?->verified_at)->not->toBeNull()
            ->and(AuditEvent::query()->where('action', 'domain.verified')->count())->toBe(1);

        auth()->logout();
        $this->get('http://learn.lonestarrealty.com/login')->assertInertia(fn (Assert $page) => $page->where('brand.name', 'Lone Star Realty'));
    });

    it('removes it and stops branding that address', function () {
        $lone = loneStar();
        $lone->forceFill(['brand' => ['primary' => '#0F766E']])->save();
        $linda = brokerOf($lone);
        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com']);
        $this->actingAs($linda)->post('/organisation/domain/verify');

        $this->actingAs($linda)->delete('/organisation/domain')->assertRedirect();

        expect(domainOf($lone))->toBeNull()
            ->and(AuditEvent::query()->where('action', 'domain.removed')->sole()->organisation_id)->toBe($lone->id);
        auth()->logout();
        $this->get('http://learn.lonestarrealty.com/login')->assertInertia(fn (Assert $page) => $page->where('brand', null));
        $this->actingAs($linda)->delete('/organisation/domain')->assertNotFound();
    });

    it('checks waiting domains on a schedule', function () {
        $lone = loneStar();
        $this->actingAs(brokerOf($lone))->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com']);

        expect(Artisan::call('reos:domains:check'))->toBe(0)
            ->and(domainOf($lone)?->live())->toBeTrue();
    });
});

describe('Brokerage settings (screen 41)', function () {
    it('shows the domain, the people who run the brokerage, the plan and the brand', function () {
        $lone = loneStar();
        $lone->forceFill(['brand' => ['primary' => '#0F766E', 'app_label' => 'Lone Star Learning']])->save();
        $linda = brokerOf($lone);
        $team = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'North team', 'parent_id' => $lone->id]);
        User::factory()->withRole(Role::Broker, $team)->create(['name' => 'Marcus Lee']);
        User::factory()->withRole(Role::Agent, $lone)->create();
        $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com']);

        $this->actingAs($linda)->get('/organisation/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('organisation/settings')
            ->where('tenant.name', 'Lone Star Realty')
            ->where('tenant.address', 'lone-star.reos.com')
            ->where('domain.hostname', 'learn.lonestarrealty.com')
            ->where('domain.live', false)
            ->where('brand.app_label', 'Lone Star Learning')
            ->has('people', 2)
            ->where('people.0.name', 'Linda Park')
            ->where('people.0.scope', null)
            ->where('people.0.two_step', true)
            ->where('people.1.name', 'Marcus Lee')
            ->where('people.1.scope', 'North team')
            ->where('people.1.two_step', false));
    });

    it('is for the tenant’s admins only', function () {
        $lone = loneStar();
        $this->actingAs(User::factory()->withRole(Role::Agent, $lone)->create())->get('/organisation/settings')->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get('/organisation/settings')->assertNotFound();
    });
});

it('shows the live custom domain as the tenant’s address on Brokerage settings and the Tenants screens', function () {
    $lone = loneStar();
    $linda = brokerOf($lone);
    $this->actingAs($linda)->post('/organisation/domain', ['hostname' => 'learn.lonestarrealty.com']);
    $this->actingAs($linda)->get('/organisation/settings')->assertInertia(fn (Assert $page) => $page->where('tenant.live_address', 'lone-star.reos.com'));

    $this->actingAs($linda)->post('/organisation/domain/verify');
    $this->actingAs($linda)->get('/organisation/settings')->assertInertia(fn (Assert $page) => $page->where('tenant.live_address', 'learn.lonestarrealty.com'));
});
