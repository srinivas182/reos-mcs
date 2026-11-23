<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\SponsorCodes;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\Demo\DemoBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ops = User::factory()->superAdmin()->create();
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School', 'slug' => 'texas-realty-school']);
    app(DemoBilling::class)->seed();
    $this->complete = Product::query()->where('key', 'texas-pre-licence-complete')->sole();
});

describe('packages on reos.com (A8.3b)', function () {
    it('lists the candidate packages for Texas with what they include and the sponsor discounts, to anyone', function () {
        $this->get('/packages')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/packages')
            ->where('state.code', 'TX')
            ->where('signedIn', false)
            ->has('packages', 3)
            ->where('packages.2.product', 'Texas Pre-licence Complete')
            ->where('packages.2.amount', 49900)
            ->where('packages.2.charge', null)
            ->where('packages.2.discounts.0', ['sponsor' => 'Texas Realty School', 'amount' => 10000]));

        // Agents' plans aren't candidate packages; a state without packages isn't a page.
        $this->get('/packages/TX')->assertInertia(fn (Assert $page) => $page->where('packages', fn ($list) => collect($list)->doesntContain('product', 'Agent Pro')));
        $this->get('/packages/LA')->assertNotFound();
    });

    it('shows a signed-in candidate their own price', function () {
        $sofia = User::factory()->withRole(Role::Candidate, $this->school)->create();

        $this->actingAs($sofia)->get('/packages')->assertInertia(fn (Assert $page) => $page
            ->where('signedIn', true)
            ->where('packages.2.charge', 39900)
            ->where('packages.2.sponsor', 'Texas Realty School'));
    });

    it('says what a code covers before it is used, and why a code won\'t work', function () {
        $code = app(SponsorCodes::class)->create($this->school, $this->complete, 'TX', 1, 12, null, $this->ops);

        $this->postJson('/packages/code', ['code' => strtolower($code->code)])->assertOk()
            ->assertJsonPath('data.sponsor', 'Texas Realty School')
            ->assertJsonPath('data.product', 'Texas Pre-licence Complete')
            ->assertJsonPath('data.months', 12);
        expect($code->refresh()->used)->toBe(0);

        $this->postJson('/packages/code', ['code' => 'NOPE-0000'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $code->forceFill(['used' => 1])->save();
        $this->postJson('/packages/code', ['code' => $code->code])->assertUnprocessable()->assertJsonValidationErrors(['code' => __('billing.codes.errors.full')]);
    });
});
