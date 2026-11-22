<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\SponsorCodes;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\SponsorCode;
use App\Models\User;
use App\Notifications\MembershipLink;
use Database\Seeders\Demo\DemoBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->ops = User::factory()->superAdmin()->create();
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School', 'slug' => 'texas-realty-school']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes', 'email' => 'sofia@example.com']);
    app(DemoBilling::class)->seed();
    $this->complete = Product::query()->where('key', 'texas-pre-licence-complete')->sole();
});

describe('the Membership screen (candidate screen 6)', function () {
    it('shows what a plan includes, never a price, and emails the link by default', function () {
        $membership = $this->actingAs($this->sofia)->getJson('/api/v1/me/membership')->assertOk()->json();

        expect($membership)->toMatchArray(['active' => false, 'ios_buy_link' => 'email', 'buy_url' => null])
            ->and($membership['includes'])->toContain('All 6 qualifying courses (180 hours), TREC approved')
            ->and(json_encode($membership))->not->toContain('499')->not->toContain('amount');

        $this->postJson('/api/v1/me/membership/email-link')->assertOk()->assertJson(['sent' => true]);
        Notification::assertSentTo($this->sofia, MembershipLink::class);
    });

    it('links to reos.com once the super admin switches it on, without an app release', function () {
        $this->actingAs($this->ops)->put('/admin/plans/ios-buy-link', ['mode' => 'link'])->assertRedirect();
        expect(AuditEvent::query()->where('action', 'platform.setting_changed')->sole()->properties)->toBe(['from' => 'email', 'to' => 'link']);

        $this->actingAs($this->sofia)->getJson('/api/v1/me/membership')->assertJson(['ios_buy_link' => 'link', 'buy_url' => url('/settings/billing')]);
    });
});

describe('sponsor codes', function () {
    it('lets REOS issue a code on the tenant Plan tab, and shows who used it', function () {
        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => time()])->post("/admin/tenants/{$this->school->id}/codes", ['product_id' => $this->complete->id, 'state' => 'TX', 'seats' => 2, 'access_months' => 12])->assertRedirect();
        $code = SponsorCode::query()->sole();
        expect($code->code)->toStartWith('TEX-')->and(strlen($code->code))->toBe(10);

        app(SponsorCodes::class)->redeem($this->sofia, strtolower($code->code));

        $this->actingAs($this->ops)->get("/admin/tenants/{$this->school->id}/plan")->assertInertia(fn (Assert $page) => $page
            ->where('sponsor.codes.0.used', 1)
            ->where('sponsor.codes.0.people', ['Sofia Reyes']));
    });

    it('unlocks at once in the app, once per person, while seats last', function () {
        $code = app(SponsorCodes::class)->create($this->school, $this->complete, 'TX', 1, 12, null, $this->ops);

        $this->actingAs($this->sofia)->postJson('/api/v1/me/codes', ['code' => " {$code->code} "])->assertOk()
            ->assertJson(['sponsor' => 'Texas Realty School', 'product' => 'Texas Pre-licence Complete', 'features' => ['learn.qualifying', 'learn.test_prep']]);
        $this->getJson('/api/v1/me/entitlements')->assertJson(['active' => true]);
        $this->getJson('/api/v1/me/membership')->assertJson(['active' => true]);

        $this->postJson('/api/v1/me/codes', ['code' => $code->code])->assertUnprocessable()->assertJsonValidationErrors(['code' => "You've already used this code."]);

        $other = User::factory()->withRole(Role::Candidate, $this->school)->create();
        $this->actingAs($other)->postJson('/api/v1/me/codes', ['code' => $code->code])->assertJsonValidationErrors(['code' => 'Every seat on this code is taken. Ask whoever gave it to you for another.']);
    });

    it('refuses unknown, stopped and expired codes', function () {
        $expired = app(SponsorCodes::class)->create($this->school, $this->complete, 'TX', 5, null, now()->addDay(), $this->ops);
        $stopped = app(SponsorCodes::class)->create($this->school, $this->complete, 'TX', 5, null, null, $this->ops);
        app(SponsorCodes::class)->deactivate($stopped, $this->ops);
        $this->travel(2)->days();

        $this->actingAs($this->sofia)->postJson('/api/v1/me/codes', ['code' => 'NOPE-123456'])->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/me/codes', ['code' => $stopped->code])->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/me/codes', ['code' => $expired->code])->assertJsonValidationErrors(['code' => 'That code has expired.']);
    });

    it('works on the web too', function () {
        $code = app(SponsorCodes::class)->create($this->school, $this->complete, 'TX', 3, null, null, $this->ops);

        $this->actingAs($this->sofia)->post('/settings/billing/codes', ['code' => $code->code])->assertRedirect();
        $this->get('/settings/billing')->assertInertia(fn (Assert $page) => $page->where('access.0.source', 'sponsor'));
        expect(AuditEvent::query()->where('action', 'code.redeemed')->sole()->actor_id)->toBe($this->sofia->id);
    });
});
