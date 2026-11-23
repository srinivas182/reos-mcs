<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Models\AuditEvent;
use App\Models\BrokerOffer;
use App\Models\Licence;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\LicenceFound;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->ana = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ana Ruiz']);
});

/** The demo regulator records (A5.1): ...7777 is inactive, ...8888 is someone else's, others are active in the account holder's name. */
describe('checking the licence (A10.3c, f03)', function () {
    it('finds an active licence in the candidate\'s name, with its sponsor', function () {
        $this->actingAs($this->ana)->postJson('/api/v1/licence-journey/licence/check', ['number' => '0798765'])->assertOk()
            ->assertJsonPath('data.check', 'verified')->assertJsonPath('data.can_switch', true)->assertJsonPath('data.watching', false)
            ->assertJsonPath('data.record.name', 'Ana Ruiz')->assertJsonPath('data.record.status', 'active')->assertJsonPath('data.record.sponsor', 'Lone Star Realty');
    });

    it('keeps checking an inactive licence daily, and tells the candidate once it is active', function () {
        $this->actingAs($this->ana)->postJson('/api/v1/licence-journey/licence/check', ['number' => '0797777'])->assertOk()
            ->assertJsonPath('data.check', 'inactive')->assertJsonPath('data.watching', true)->assertJsonPath('data.can_switch', false);
        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'alone'])->assertUnprocessable()->assertJsonValidationErrors('number');

        $this->artisan('reos:licence:watch')->assertSuccessful();
        Notification::assertNothingSent();

        // TREC's record changes (the broker sponsored her): the next morning's check finds it active.
        LicenceJourney::query()->update(['licence_number' => '0791234']);
        $this->artisan('reos:licence:watch')->assertSuccessful();
        $this->artisan('reos:licence:watch')->assertSuccessful();
        Notification::assertSentToTimes($this->ana, LicenceFound::class, 1);
        $this->getJson('/api/v1/licence-journey/licence')->assertJsonPath('data.check', 'verified')->assertJsonPath('data.watching', false);
    });

    it('won\'t switch on a licence in someone else\'s name', function () {
        $this->actingAs($this->ana)->postJson('/api/v1/licence-journey/licence/check', ['number' => '0798888'])->assertOk()
            ->assertJsonPath('data.check', 'name_mismatch')->assertJsonPath('data.can_switch', false)->assertJsonPath('data.watching', false);
        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'alone'])->assertUnprocessable();
    });
});

describe('switching to agent (A10.3c, screen 20: the done-when)', function () {
    it('makes the candidate an agent on the same account, on their own', function () {
        $this->actingAs($this->ana)->postJson('/api/v1/licence-journey/licence/check', ['number' => '0798765'])->assertOk();
        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'alone'])->assertOk()
            ->assertJsonPath('data.switched', true)->assertJsonPath('data.can_switch', false)->assertJsonPath('organisation', Organisation::platform()->name);

        $memberships = app(Memberships::class);
        expect($memberships->find($this->ana, Role::Agent, Organisation::platform()->id))->not->toBeNull()
            // Same account: the candidate's history stays.
            ->and($memberships->find($this->ana, Role::Candidate, Organisation::platform()->id))->not->toBeNull()
            ->and(User::query()->count())->toBe(1)
            ->and(Licence::query()->sole())->number->toBe('0798765')->status->toBe(LicenceStatus::Active)->verified_by->toBe('simulator')
            ->and(LicenceJourney::query()->sole()->steps)->toHaveKey('active')
            ->and(AuditEvent::query()->where('action', 'licence.switched_to_agent')->sole()->properties)->toMatchArray(['path' => 'alone']);

        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'alone'])->assertUnprocessable();
    });

    it('joins the brokerage whose offer the candidate accepted, whose mentors they then see', function () {
        $flags = app(Flags::class);
        $flag = $flags->flag('video_rooms');
        $flag->forceFill(['stage' => 'everyone'])->save();
        $flags->changed($flag);
        $lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
        $maya = User::factory()->withRole(Role::Mentor, $lone)->create(['name' => 'Maya Chen']);
        $this->actingAs($maya)->putJson('/api/v1/bookings/offer', [
            'bio' => 'Twelve years in Katy.', 'languages' => ['en', 'es'], 'topics' => ['First listings'], 'hours' => ['mon' => [['13:00', '17:00']]],
            'timezone' => 'America/Chicago', 'price_30_cents' => 0, 'price_60_cents' => 0, 'active' => true,
        ])->assertOk();
        // A school's instructor isn't one of her mentors.
        $school = Organisation::factory()->ofType(OrganisationType::School)->create();
        $nelle = User::factory()->withRole(Role::Instructor, $school)->create();
        $this->actingAs($nelle)->putJson('/api/v1/bookings/offer', [
            'bio' => null, 'languages' => ['en'], 'topics' => [], 'hours' => ['mon' => [['13:00', '17:00']]],
            'timezone' => 'America/Chicago', 'price_30_cents' => 0, 'price_60_cents' => 0, 'active' => true,
        ])->assertOk();

        $this->actingAs($this->ana)->postJson('/api/v1/licence-journey/licence/check', ['number' => '0798765'])->assertOk()->assertJsonPath('data.broker', null);
        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'broker'])->assertUnprocessable()->assertJsonValidationErrors('path');

        BrokerOffer::query()->create(['uuid' => (string) Str::uuid(), 'brokerage_id' => $lone->id, 'candidate_id' => $this->ana->id, 'office_city' => 'Katy', 'split_percent' => 70,
            'monthly_fee_cents' => 0, 'mentoring' => true, 'status' => BrokerOffer::ACCEPTED, 'answered_at' => now()]);
        $this->getJson('/api/v1/licence-journey/licence')->assertJsonPath('data.broker.name', 'Lone Star Realty');
        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'broker'])->assertOk()->assertJsonPath('organisation', 'Lone Star Realty');

        expect(app(Memberships::class)->find($this->ana, Role::Agent, $lone->id))->not->toBeNull()
            ->and(LicenceJourney::query()->sole()->steps)->toHaveKeys(['sponsor', 'active']);

        $this->getJson('/api/v1/mentors')->assertOk()->assertJsonPath('data.brokerage', 'Lone Star Realty')
            ->assertJsonCount(1, 'data.mentors')->assertJsonPath('data.mentors.0.name', 'Maya Chen')->assertJsonPath('data.mentors.0.languages', ['en', 'es']);
        $this->get('/mentors')->assertOk()->assertInertia(fn (Assert $page) => $page->component('mentors/index')->where('brokerage', 'Lone Star Realty'));
    });

    it('takes an association\'s code, which has to be real', function () {
        $this->actingAs($this->ana)->postJson('/api/v1/licence-journey/licence/check', ['number' => '0798765'])->assertOk();
        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'association'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/licence-journey/licence/switch', ['path' => 'association', 'code' => 'NOPE-1234'])->assertUnprocessable()->assertJsonValidationErrors('code');
        expect(app(Memberships::class)->find($this->ana, Role::Agent, Organisation::platform()->id))->toBeNull();
    });

    it('serves the switch page', function () {
        $this->actingAs($this->ana)->get('/licence-journey/licence')->assertOk()->assertInertia(fn (Assert $page) => $page->component('licence/switch')->where('switch.number', null));
    });
});
