<?php

declare(strict_types=1);

use App\Models\AuditEvent;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Categories drive provider onboarding (B15.1, ADR 0073).
 */

beforeEach(function () {
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->dana = User::factory()->create(['name' => 'Dana Brooks']);
});

describe('categories drive onboarding (B15.1)', function () {
    it('turns the seeded categories into typed fields with a licence step', function () {
        $loan = ProviderCategory::query()->where('key', 'loan-officer')->sole();
        expect($loan->licence_kind)->toBe('nmls')
            ->and($loan->profile_fields[0])->toBe(['key' => 'nmls_id', 'label' => 'NMLS ID', 'type' => 'text', 'options' => []])
            ->and(ProviderCategory::query()->where('key', 'home-inspector')->value('licence_kind'))->toBe('trec_inspector')
            ->and(ProviderCategory::query()->where('key', 'photographer')->value('licence_kind'))->toBeNull();
    });

    it('lets the admin add a category without code, and a provider join it (the done-when)', function () {
        $this->actingAs($this->ops)->post('/admin/provider-categories', [
            'name' => 'Home stager', 'licence_check' => 'ID check', 'verification' => 'Photo ID and business details', 'status' => 'live',
            'profile_fields' => [
                ['label' => 'Rooms per month', 'type' => 'text'],
                ['label' => 'Styles', 'type' => 'list', 'options' => ['Modern', 'Farmhouse', 'Traditional']],
                ['label' => 'Owns furniture inventory', 'type' => 'yes_no'],
            ],
            'prices' => [['state' => 'TX', 'tier' => 'basic', 'monthly' => '29'], ['state' => 'TX', 'tier' => 'pro', 'monthly' => '79']],
        ])->assertSessionHasNoErrors();

        $grid = $this->actingAs($this->dana)->getJson('/api/v1/provider/categories?state=TX')->assertOk()->json('data');
        $stager = collect($grid)->firstWhere('key', 'home-stager');
        expect($stager)->toMatchArray(['name' => 'Home stager', 'licence' => ['kind' => 'business', 'label' => 'Photo ID and business details'], 'prices' => ['basic' => 2900, 'pro' => 7900, 'premium' => null]])
            ->and(array_column($stager['fields'], 'type'))->toBe(['text', 'list', 'yes_no'])
            ->and(collect($grid)->pluck('key'))->not->toContain('solar-installer');

        $joined = $this->putJson('/api/v1/provider/profile/category', ['category' => 'home-stager'])->assertOk()->json('data');
        expect($joined['category']['key'])->toBe('home-stager')->and($joined['missing'])->toBe(['rooms_per_month', 'styles', 'owns_furniture_inventory']);

        $this->putJson('/api/v1/provider/profile/fields', ['fields' => ['styles' => ['Victorian'], 'owns_furniture_inventory' => 'yes', 'rooms_per_month' => '']])
            ->assertUnprocessable()->assertJsonValidationErrors(['fields.styles', 'fields.owns_furniture_inventory', 'fields.rooms_per_month']);
        $done = $this->putJson('/api/v1/provider/profile/fields', ['fields' => ['rooms_per_month' => '12', 'styles' => ['Modern', 'Farmhouse'], 'owns_furniture_inventory' => true, 'unknown' => 'x']])
            ->assertOk()->json('data');
        expect($done['missing'])->toBe([])
            ->and(ProviderProfile::query()->sole()->fields)->toBe(['rooms_per_month' => '12', 'styles' => ['Modern', 'Farmhouse'], 'owns_furniture_inventory' => true]);

        $this->get('/provider/join')->assertOk()->assertInertia(fn (Assert $page) => $page->component('provider/join')->where('onboarding.joining.category.key', 'home-stager'));
        $this->actingAs($this->ops)->get('/admin/provider-categories')->assertInertia(fn (Assert $page) => $page->where('categories', fn ($rows) => collect($rows)->firstWhere('key', 'home-stager')['providers'] === 1));
    });

    it('keeps answers only for fields the category still has', function () {
        $this->actingAs($this->dana)->putJson('/api/v1/provider/profile/category', ['category' => 'photographer'])->assertOk();
        $this->putJson('/api/v1/provider/profile/fields', ['fields' => ['services' => 'Listing photos and drone']])->assertOk()->assertJsonPath('data.fields.services', 'Listing photos and drone');
        $this->putJson('/api/v1/provider/profile/category', ['category' => 'loan-officer'])->assertOk()->assertJsonPath('data.fields', []);
        $this->putJson('/api/v1/provider/profile/category', ['category' => 'solar-installer'])->assertUnprocessable()->assertJsonValidationErrors('category');
    });

    it('needs a category before fields', function () {
        $this->actingAs($this->dana)->putJson('/api/v1/provider/profile/fields', ['fields' => ['x' => 'y']])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->getJson('/api/v1/provider/profile')->assertOk()->assertJsonPath('data', ['category' => null, 'fields' => [], 'missing' => []]);
    });
});

describe('requesting a category (B15.1)', function () {
    it('puts a request in the admin\'s queue, counted once per person', function () {
        $sent = $this->actingAs($this->dana)->postJson('/api/v1/provider/categories/requests', ['name' => 'Pool inspector', 'note' => 'I inspect pools and spas in Katy.'])->assertOk()->json('data');
        expect($sent)->toBe(['key' => 'pool-inspector', 'name' => 'Pool inspector', 'status' => 'requested', 'requests' => 1]);
        $this->postJson('/api/v1/provider/categories/requests', ['name' => 'pool inspector'])->assertOk()->assertJsonPath('data.requests', 1);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/provider/categories/requests', ['name' => 'Solar installer'])->assertOk()->assertJsonPath('data.requests', 15);

        $this->actingAs(User::factory()->create())->postJson('/api/v1/provider/categories/requests', ['name' => 'Loan officer'])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->actingAs($this->ops)->get('/admin/provider-categories')->assertInertia(fn (Assert $page) => $page->where('categories', function ($rows) {
            $pool = collect($rows)->firstWhere('key', 'pool-inspector');

            return $pool['status'] === 'requested' && $pool['requests'] === 1 && $pool['request_notes'] === ['I inspect pools and spas in Katy.'];
        }));
        expect(AuditEvent::query()->where('action', 'provider_category.requested')->count())->toBe(3);
    });
});
