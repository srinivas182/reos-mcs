<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\AuditEvent;
use App\Models\ProviderCategory;
use App\Models\ProviderLicence;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * The provider profile and the agent's view of it (B15.3, ADR 0075).
 */

beforeEach(function () {
    Notification::fake();
    Storage::fake(config()->string('reos.content.disk'));
    $this->travelTo('2026-11-30 15:00:00');
    // Maya Chen: a live loan officer (B15.2), her NMLS ID approved by a reviewer this morning.
    $this->maya = User::factory()->withRole(Role::Provider)->create(['name' => 'Maya Chen']);
    $this->profile = ProviderProfile::query()->create([
        'user_id' => $this->maya->id, 'provider_category_id' => ProviderCategory::query()->where('key', 'loan-officer')->value('id'),
        'status' => ProviderProfile::LIVE, 'live_at' => now(), 'tier' => 'pro', 'business_name' => 'Chen Home Lending', 'welcomed_at' => now(),
    ]);
    $this->profile->areas()->create(['kind' => 'city', 'city' => 'Katy']);
    $this->profile->areas()->create(['kind' => 'radius', 'postal_code' => '77494', 'radius_miles' => 15]);
    ProviderLicence::query()->create(['user_id' => $this->maya->id, 'kind' => 'nmls', 'number' => '1234567', 'status' => ProviderLicence::VERIFIED, 'checked_via' => 'reviewer', 'reviewed_at' => now()->subHours(3)]);
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->profileBody = [
        'bio' => 'I help first-time buyers in Katy find the right loan and close on time.',
        'bio_es' => 'Ayudo a compradores de primera vivienda en Katy a encontrar el préstamo adecuado y cerrar a tiempo.',
        'services' => ['FHA loans', 'VA loans', 'Conventional loans'], 'specialities' => ['First-time buyers'], 'response_hours' => 2,
        'resources' => [['title' => 'Closing cost checklist', 'url' => 'https://chenlending.example/checklist.pdf']],
        'sessions' => [['title' => 'Credit basics for agents', 'minutes' => 30]],
    ];
});

describe('the provider profile (B15.3)', function () {
    it('publishes a checked profile that an agent sees verified (the done-when)', function () {
        $saved = $this->actingAs($this->maya)->putJson('/api/v1/provider/profile/builder', $this->profileBody)->assertOk()->json('data');
        expect($saved['issues'])->toBe([])->and($saved['published_at'])->not->toBeNull()
            ->and($saved['preview']['services'])->toBe(['FHA loans', 'VA loans', 'Conventional loans']);

        $found = $this->actingAs($this->james)->getJson('/api/v1/providers?category=loan-officer&place=Katy')->assertOk()->json('data');
        expect($found)->toHaveCount(1)->and($found[0])->toMatchArray([
            'name' => 'Chen Home Lending', 'category' => 'Loan officer', 'response_hours' => 2, 'tier' => 'pro', 'live' => true,
            'areas' => ['Katy', '15 miles around 77494'],
            'licence' => ['kind' => 'nmls', 'number' => '1234567', 'verified' => true, 'checked_at' => '2026-11-30T12:00:00+00:00', 'via' => 'reviewer'],
        ]);

        $this->getJson("/api/v1/providers/{$this->profile->id}")->assertOk()->assertJsonPath('data.bio', $this->profileBody['bio'])
            ->assertJsonPath('data.sessions.0', ['title' => 'Credit basics for agents', 'minutes' => 30]);
        $this->withHeader('Accept-Language', 'es')->getJson("/api/v1/providers/{$this->profile->id}")->assertJsonPath('data.bio', $this->profileBody['bio_es']);
        $this->get("/providers/{$this->profile->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('providers/show')->where('provider.licence.verified', true));
        $this->get('/providers?place=77494')->assertOk()->assertInertia(fn (Assert $page) => $page->component('providers/index')->has('providers', 1));
        $this->getJson('/api/v1/providers?place=Houston')->assertOk()->assertJsonPath('data', []);
    });

    it('refuses phone numbers, emails and web addresses, and non-https resources', function () {
        $this->actingAs($this->maya)->putJson('/api/v1/provider/profile/builder', [...$this->profileBody, 'bio' => 'Call me at (281) 555-0142 any time.'])->assertUnprocessable()->assertJsonValidationErrors('bio');
        $this->putJson('/api/v1/provider/profile/builder', [...$this->profileBody, 'services' => ['Email maya@chenlending.com']])->assertJsonValidationErrors('services');
        $this->putJson('/api/v1/provider/profile/builder', [...$this->profileBody, 'bio_es' => 'Visite www.chenlending.com'])->assertJsonValidationErrors('bio_es');
        $this->putJson('/api/v1/provider/profile/builder', [...$this->profileBody, 'resources' => [['title' => 'Checklist', 'url' => 'http://chenlending.example/a.pdf']]])->assertJsonValidationErrors('resources.0.url');
        expect($this->profile->refresh()->published)->toBeNull();
    });

    it('keeps blocked wording as a draft and shows agents the last version that passed', function () {
        $this->actingAs($this->maya)->putJson('/api/v1/provider/profile/builder', $this->profileBody)->assertOk();
        $blocked = $this->putJson('/api/v1/provider/profile/builder', [...$this->profileBody, 'bio' => 'Approval guaranteed for every buyer in Katy.'])->assertOk()->json('data');
        expect(array_column($blocked['issues'], 'code'))->toContain('claims')->and($blocked['draft']['bio'])->toBe('Approval guaranteed for every buyer in Katy.');

        $this->actingAs($this->james)->getJson("/api/v1/providers/{$this->profile->id}")->assertJsonPath('data.bio', $this->profileBody['bio']);
        expect(AuditEvent::query()->where('action', 'provider.profile_blocked')->count())->toBe(1);
    });

    it('writes a bio in English and Spanish', function () {
        $this->profile->forceFill(['services' => ['FHA loans', 'VA loans'], 'response_hours' => 2])->save();
        $draft = $this->actingAs($this->maya)->postJson('/api/v1/provider/profile/bio')->assertOk()->json('data');
        expect($draft['engine'])->toBe('simulator')
            ->and($draft['bio'])->toContain('Chen Home Lending')->toContain('Katy')->toContain('FHA loans')
            ->and($draft['bio_es'])->toContain('Atiende');
    });

    it('shows the photo through a signed link', function () {
        $this->actingAs($this->maya)->post('/api/v1/provider/profile/photo', ['kind' => 'photo', 'image' => UploadedFile::fake()->image('maya.jpg', 400, 400)], ['Accept' => 'application/json'])->assertOk();
        $this->putJson('/api/v1/provider/profile/builder', $this->profileBody)->assertOk();
        $url = $this->actingAs($this->james)->getJson("/api/v1/providers/{$this->profile->id}")->json('data.photo_url');
        expect($url)->toContain('/providers/'.$this->profile->id.'/photo/photo');
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk();
        $this->get('/providers/'.$this->profile->id.'/photo/photo')->assertForbidden();
    });

    it('shows agents only live providers, and the provider their own preview', function () {
        $this->actingAs($this->maya)->putJson('/api/v1/provider/profile/builder', $this->profileBody)->assertOk();
        $this->profile->forceFill(['status' => ProviderProfile::HIDDEN, 'hidden_reason' => 'licence_expired'])->save();

        $this->actingAs($this->james)->getJson("/api/v1/providers/{$this->profile->id}")->assertNotFound();
        $this->getJson('/api/v1/providers')->assertJsonPath('data', []);
        $this->actingAs($this->maya)->getJson("/api/v1/providers/{$this->profile->id}")->assertOk()->assertJsonPath('data.live', false);
        $this->get('/provider/profile')->assertOk()->assertInertia(fn (Assert $page) => $page->component('provider/profile')->where('builder.preview.name', 'Chen Home Lending'));
    });
});
