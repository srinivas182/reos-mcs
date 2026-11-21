<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\User;
use App\Tenancy\BrandColour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Branding (A4.2a, web screens 2 and 57). */
function texasRealtors(array $brand = []): Organisation
{
    return Organisation::factory()->ofType(OrganisationType::Association)->create(['name' => 'Texas REALTORS®', 'brand' => $brand ?: null]);
}

function associationAdmin(Organisation $association): User
{
    return User::factory()->withRole(Role::AssociationAdmin, $association)->withTwoFactor()->create(['name' => 'Karen Webb']);
}

describe('Branding and domain (screen 57)', function () {
    it('shows the brand, who sees it, and the sign-in address', function () {
        $tr = texasRealtors(['primary' => '#1E3A8A', 'app_label' => 'Texas REALTORS® Learning']);

        $this->actingAs(associationAdmin($tr))->get('/organisation/branding')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('organisation/branding')
            ->where('tenant.name', 'Texas REALTORS®')
            ->where('tenant.type', 'association')
            ->where('tenant.address', 'texas-realtors.reos.com')
            ->where('brand.primary', '#1E3A8A')
            ->where('brand.app_label', 'Texas REALTORS® Learning')
            ->where('inherited', null));
    });

    it('saves colour, app label and email sender, audited with before and after', function () {
        $tr = texasRealtors();
        $karen = associationAdmin($tr);

        $this->actingAs($karen)->put('/organisation/branding', ['primary' => '1e3a8a', 'app_label' => 'Texas REALTORS® Learning', 'email_from_name' => 'Texas REALTORS®', 'reply_to' => 'education@texasrealtors.example'])
            ->assertRedirect();

        expect($tr->fresh()?->brand)->toMatchArray(['primary' => '#1E3A8A', 'app_label' => 'Texas REALTORS® Learning', 'reply_to' => 'education@texasrealtors.example']);
        $event = AuditEvent::query()->where('action', 'brand.updated')->sole();
        expect($event->organisation_id)->toBe($tr->id)
            ->and($event->properties['changed'])->toBe(['primary', 'app_label', 'email_from_name', 'reply_to'])
            ->and($event->properties['after']['primary'])->toBe('#1E3A8A');
    });

    it('refuses a colour white text can’t be read on, and suggests one', function () {
        $tr = texasRealtors();

        $this->actingAs(associationAdmin($tr))->put('/organisation/branding', ['primary' => '#7FB3FF'])
            ->assertSessionHasErrors(['primary' => __('brand.errors.contrast', ['ratio' => '2.1', 'suggestion' => BrandColour::parse('#7FB3FF')?->suggestion()->hex])]);
        $this->actingAs(associationAdmin($tr))->put('/organisation/branding', ['primary' => 'blue'])->assertSessionHasErrors('primary');
        $this->actingAs(associationAdmin($tr))->put('/organisation/branding', ['reply_to' => 'not-an-email'])->assertSessionHasErrors('reply_to');
        expect($tr->fresh()?->brand)->toBeNull();
    });

    it('is for the organisation’s admins only', function () {
        $tr = texasRealtors();
        $this->actingAs(User::factory()->withRole(Role::Agent)->create())->get('/organisation/branding')->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get('/organisation/branding')->assertNotFound();
        $this->actingAs(User::factory()->withRole(Role::AssociationAdmin, $tr)->create())->get('/organisation/branding')->assertRedirect();
    });
});

describe('logos', function () {
    beforeEach(fn () => Storage::fake());

    it('re-draws an upload as a small PNG in the tenant’s own folder, and serves it publicly', function () {
        $tr = texasRealtors();
        $karen = associationAdmin($tr);

        $this->actingAs($karen)->post('/organisation/branding/logo', ['logo' => UploadedFile::fake()->image('logo.jpg', 1200, 600)])->assertRedirect();

        $path = $tr->fresh()?->brand['logo_path'];
        expect($path)->toStartWith("tenants/{$tr->id}/brand/logo-")->toEndWith('.png');
        [$width, $height, $type] = getimagesizefromstring((string) Storage::get($path));
        expect([$width, $height, $type])->toBe([512, 256, IMAGETYPE_PNG]);

        auth()->logout();
        $this->get('/brand/texas-realtors/logo')->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect(AuditEvent::query()->where('action', 'brand.logo_changed')->sole()->organisation_id)->toBe($tr->id);
    });

    it('refuses files that aren’t images, and tiny ones', function () {
        $tr = texasRealtors();
        $karen = associationAdmin($tr);

        $this->actingAs($karen)->post('/organisation/branding/logo', ['logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->actingAs($karen)->post('/organisation/branding/logo', ['logo' => UploadedFile::fake()->image('logo.png', 20, 20)])->assertSessionHasErrors('logo');
        expect($tr->fresh()?->brand)->toBeNull();
    });

    it('replaces and removes, deleting the old file', function () {
        $tr = texasRealtors();
        $karen = associationAdmin($tr);

        $this->actingAs($karen)->post('/organisation/branding/logo', ['logo' => UploadedFile::fake()->image('a.png', 200, 200)]);
        $first = $tr->fresh()?->brand['logo_path'];
        $this->actingAs($karen)->post('/organisation/branding/logo', ['logo' => UploadedFile::fake()->image('b.png', 200, 200)]);
        Storage::assertMissing($first);

        $this->actingAs($karen)->delete('/organisation/branding/logo')->assertRedirect();
        expect($tr->fresh()?->brand['logo_path'])->toBeNull();
        $this->get('/brand/texas-realtors/logo')->assertNotFound();
    });
});

describe('which brand people see', function () {
    it('shows a brokerage its own brand, and falls back to its association’s', function () {
        $tr = texasRealtors(['primary' => '#1E3A8A']);
        $lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'parent_id' => $tr->id]);
        $agent = User::factory()->withRole(Role::Agent, $lone)->create();

        $this->actingAs($agent)->get('/connections')->assertInertia(fn (Assert $page) => $page->where('brand.name', 'Texas REALTORS®')->where('brand.palette.action', '#1E3A8A'));

        $lone->forceFill(['brand' => ['primary' => '#0F766E']])->save();
        $this->actingAs($agent)->get('/connections')->assertInertia(fn (Assert $page) => $page->where('brand.name', 'Lone Star Realty')->where('brand.primary', '#0F766E'));
    });

    it('shows REOS to super admins and to people without a tenant brand', function () {
        $this->actingAs(User::factory()->superAdmin()->create())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('brand', null));
        $this->actingAs(User::factory()->withRole(Role::Agent)->create())->get('/connections')->assertInertia(fn (Assert $page) => $page->where('brand', null));
    });

    it('brands sign-in on the tenant’s address (journey screen 2)', function () {
        texasRealtors(['primary' => '#1E3A8A']);

        $this->get('http://texas-realtors.reos.com/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('brand.name', 'Texas REALTORS®')
            ->where('brand.type', 'association'));
        $this->get('http://nobody.reos.com/login')->assertInertia(fn (Assert $page) => $page->where('brand', null));
        $this->get('http://a.texas-realtors.reos.com/login')->assertInertia(fn (Assert $page) => $page->where('brand', null));
        $this->get('http://reos.com/login')->assertInertia(fn (Assert $page) => $page->where('brand', null));
    });
});

describe('brand colours', function () {
    it('measures contrast with white text the WCAG way', function () {
        expect(BrandColour::parse('#000000')?->contrastWithWhite())->toEqualWithDelta(21.0, 0.01)
            ->and(BrandColour::parse('#FFFFFF')?->contrastWithWhite())->toEqualWithDelta(1.0, 0.01)
            ->and(BrandColour::parse('#2952CC')?->readable())->toBeTrue()
            ->and(BrandColour::parse('#7FB3FF')?->readable())->toBeFalse()
            ->and(BrandColour::parse('#7FB3FF')?->suggestion()->readable())->toBeTrue()
            ->and(BrandColour::parse('nope'))->toBeNull();
    });
});
