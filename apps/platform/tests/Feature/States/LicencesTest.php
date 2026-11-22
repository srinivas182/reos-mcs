<?php

declare(strict_types=1);

use App\Enums\LicenceStatus;
use App\Enums\StateStatus;
use App\Licences\LicenceLookups;
use App\Models\AuditEvent;
use App\Models\CatalogueSetting;
use App\Models\Licence;
use App\Models\State;
use App\Models\User;
use App\Services\Licences\Licences;
use App\Services\Privacy\DataExport;
use App\Services\States\PersonStates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00:00');
    $this->james = User::factory()->create(['name' => 'James Miller']);
});

/** Opens a state for the test (only Texas is live in the seed). */
function openState(string $code): State
{
    $state = State::query()->findOrFail($code);
    $state->forceFill(['status' => StateStatus::Live])->save();

    return $state;
}

describe('adding a licence (agent journey screen 149)', function () {
    it('checks the number with the regulator and counts it when the name matches', function () {
        $this->actingAs($this->james);

        $response = $this->postJson('/api/v1/me/licences', ['state' => 'tx', 'number' => '0798765'])->assertCreated();

        expect($response->json('check'))->toBe('verified')
            ->and($response->json('source'))->toBe('Checked with REOS demo license records (Simulator).')
            ->and($response->json('data'))->toMatchArray([
                'state' => ['code' => 'TX', 'name' => 'Texas', 'live' => true],
                'type' => 'sales_agent',
                'number' => '0798765',
                'status' => 'active',
                'holder_name' => 'James Miller',
                'expires_on' => '2027-03-31',
                'sponsor_name' => 'Lone Star Realty',
                'verified_by' => 'simulator',
                'tracked_only' => false,
            ])
            ->and(AuditEvent::query()->where('action', 'licence.added')->sole()->properties)->toBe(['state' => 'TX', 'type' => 'sales_agent', 'check' => 'verified']);
    });

    it('sends a licence under another name to a reviewer', function () {
        $this->actingAs($this->james);

        $response = $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798888'])->assertCreated();

        expect($response->json('check'))->toBe('name_mismatch')
            ->and($response->json('data.status'))->toBe('pending_review')
            ->and($response->json('data.review_note'))->toBe("The regulator's record is under another name: Pat Jordan.")
            ->and($response->json('data.verified_by'))->toBeNull();
    });

    it('refuses a number the regulator has, unless the person asks for a review', function () {
        $this->actingAs($this->james);

        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0790000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['number' => "Texas's records have no license with this number."]);
        expect(Licence::query()->count())->toBe(0);

        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0790000', 'review' => true])
            ->assertCreated()->assertJsonPath('check', 'not_found')->assertJsonPath('data.status', 'pending_review');
    });

    it('sends licences in states with no regulator check to a reviewer, tracked for renewal only while not live', function () {
        $this->actingAs($this->james);

        $this->postJson('/api/v1/me/licences', ['state' => 'LA', 'number' => '0995-21837', 'type' => 'sales_agent'])
            ->assertCreated()
            ->assertJsonPath('check', 'no_lookup')
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.tracked_only', true);
    });

    it('reads broker licences and who they supervise, and refuses sponsoring on a sales agent licence', function () {
        $this->actingAs($this->james);

        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '9123456', 'broker_role' => 'sponsoring_broker'])
            ->assertCreated()->assertJsonPath('data.type', 'broker')->assertJsonPath('data.broker_role', 'sponsoring_broker');

        $this->james->licences()->delete();
        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798765', 'broker_role' => 'designated_broker'])
            ->assertUnprocessable()->assertJsonValidationErrors('broker_role');
        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798765', 'broker_role' => 'delegated_supervisor'])
            ->assertCreated()->assertJsonPath('data.broker_role', 'delegated_supervisor');
    });

    it('keeps one licence per state, and validates the number', function () {
        $this->actingAs($this->james);
        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798765'])->assertCreated();

        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798766'])->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->postJson('/api/v1/me/licences', ['state' => 'ZZ', 'number' => '1'])->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '<script>'])->assertUnprocessable()->assertJsonValidationErrors('number');
    });

    it('matches names regardless of order, case, accents and middle names', function () {
        expect(Licences::sameName('MILLER, JAMES R', 'James Miller'))->toBeTrue()
            ->and(Licences::sameName('José Martínez', 'Jose Martinez'))->toBeTrue()
            ->and(Licences::sameName('Pat Jordan', 'James Miller'))->toBeFalse()
            ->and(Licences::sameName('Jane Miller', 'James Miller'))->toBeTrue()
            ->and(Licences::sameName('Miller', null))->toBeFalse();
    });

    it('uses no demo records in production: licences go to a reviewer', function () {
        app()->detectEnvironment(fn () => 'production');

        expect(app(LicenceLookups::class)->for(State::query()->findOrFail('TX')))->toBeNull();
    });
});

describe('my licences (agent journey screen 148)', function () {
    it('lists live states first, removes with Undo and is mine only', function () {
        $this->actingAs($this->james);
        $this->postJson('/api/v1/me/licences', ['state' => 'LA', 'number' => '0995-21837']);
        $tx = $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798765'])->json('data.id');

        expect(collect($this->getJson('/api/v1/me/licences')->assertOk()->json('data'))->pluck('state.code')->all())->toBe(['TX', 'LA']);

        $this->deleteJson("/api/v1/me/licences/{$tx}")->assertNoContent();
        expect(collect($this->getJson('/api/v1/me/licences')->json('data'))->pluck('state.code')->all())->toBe(['LA']);
        $this->postJson("/api/v1/me/licences/{$tx}/restore")->assertOk()->assertJsonPath('data.status', 'active');

        $this->actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/me/licences/{$tx}")->assertNotFound();
        $this->patchJson("/api/v1/me/licences/{$tx}", ['broker_role' => null])->assertNotFound();
    });

    it('re-adds a removed state licence by checking it again', function () {
        $this->actingAs($this->james);
        $id = $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798765'])->json('data.id');
        $this->deleteJson("/api/v1/me/licences/{$id}");

        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0799999'])
            ->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'expired');
    });

    it('adds and removes licences on the web (screen 23)', function () {
        $this->actingAs($this->james)->post('/settings/licences', ['state' => 'TX', 'number' => '0798765'])->assertRedirect();
        $licence = Licence::query()->sole();

        $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
            ->where('licences.0.number', '0798765')
            ->where('licenceStates.0', ['code' => 'TX', 'name' => 'Texas', 'live' => true]));

        $this->patch("/settings/licences/{$licence->id}", ['broker_role' => 'delegated_supervisor'])->assertRedirect();
        expect($licence->refresh()->broker_role?->value)->toBe('delegated_supervisor');
        $this->delete("/settings/licences/{$licence->id}")->assertRedirect();
        expect(Licence::query()->count())->toBe(0);
        $this->post("/settings/licences/{$licence->id}/restore")->assertRedirect();
        expect(Licence::query()->count())->toBe(1);
    });
});

describe('licence reviews (People)', function () {
    it('lists licences waiting and lets a super admin approve or reject them, audited', function () {
        [$mismatch] = app(Licences::class)->add($this->james, State::query()->findOrFail('TX'), '0798888', null, null);
        $maria = User::factory()->create(['name' => 'Maria Lopez']);
        [$unknown] = app(Licences::class)->add($maria, State::query()->findOrFail('TX'), '0790000', null, null, askReview: true);
        $ops = User::factory()->superAdmin()->create();
        $this->actingAs($ops)->withSession(['auth.password_confirmed_at' => time()]);

        $this->get('/admin/people')->assertInertia(fn (Assert $page) => $page
            ->has('licenceReviews', 2)
            ->where('licenceReviews.0.person.name', 'James Miller')
            ->where('licenceReviews.0.note', "The regulator's record is under another name: Pat Jordan."));
        $this->get("/admin/people/{$this->james->id}")->assertInertia(fn (Assert $page) => $page->where('licences.0.status', 'pending_review'));

        $this->post("/admin/people/licences/{$mismatch->id}/approve")->assertRedirect();
        $this->post("/admin/people/licences/{$unknown->id}/reject", ['note' => 'No such Texas licence.'])->assertRedirect();
        $this->post("/admin/people/licences/{$unknown->id}/approve")->assertStatus(409);

        expect($mismatch->refresh())->status->toBe(LicenceStatus::Active)->verified_by->toBe('reviewer')->reviewed_by->toBe($ops->id)
            ->and($unknown->refresh())->status->toBe(LicenceStatus::Rejected)->review_note->toBe('No such Texas licence.')
            ->and(AuditEvent::query()->whereIn('action', ['licence.approved', 'licence.rejected'])->pluck('actor_id')->unique()->all())->toBe([$ops->id]);
    });

    it('is for super admins only', function () {
        [$licence] = app(Licences::class)->add($this->james, State::query()->findOrFail('TX'), '0798888', null, null);

        $this->actingAs($this->james)->post("/admin/people/licences/{$licence->id}/approve")->assertForbidden();
    });
});

describe('the state switcher and what each state sees (A5.1 done when)', function () {
    it('works in Texas when nothing else is known', function () {
        $this->actingAs($this->james);

        $this->getJson('/api/v1/me/states')->assertOk()->assertExactJson([
            'current' => ['code' => 'TX', 'name' => 'Texas'],
            'options' => [['code' => 'TX', 'name' => 'Texas']],
        ]);
        $this->getJson('/api/v1/me')->assertJsonPath('states.current.code', 'TX');
    });

    it('switches between live licence states; licences elsewhere and those in review do not count', function () {
        openState('FL');
        $this->actingAs($this->james);
        $this->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0798765']);
        $this->postJson('/api/v1/me/licences', ['state' => 'LA', 'number' => '1']);
        // Florida has no regulator check yet: it counts only once approved.
        [$florida] = app(Licences::class)->add($this->james, State::query()->findOrFail('FL'), '3456789', null, null);

        expect(app(PersonStates::class)->codes($this->james))->toBe(['TX']);
        $this->putJson('/api/v1/me/states/current', ['state' => 'FL'])->assertUnprocessable()->assertJsonValidationErrors('state');

        app(Licences::class)->approve($florida, User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/me/states')->assertJson([
            'current' => ['code' => 'FL', 'name' => 'Florida'],
            'options' => [['code' => 'FL', 'name' => 'Florida'], ['code' => 'TX', 'name' => 'Texas']],
        ]);
        $this->putJson('/api/v1/me/states/current', ['state' => 'tx'])->assertOk()->assertJsonPath('current.code', 'TX');
        expect($this->james->refresh()->current_state)->toBe('TX');

        // Removing the Texas licence moves them back to Florida.
        app(Licences::class)->remove(Licence::query()->where('state_code', 'TX')->sole(), $this->james);
        expect(app(PersonStates::class)->current($this->james->refresh()))->toBe('FL');
    });

    it('shows the switcher in the web top bar', function () {
        openState('FL');
        app(Licences::class)->add($this->james, State::query()->findOrFail('TX'), '0798765', null, null);
        app(Licences::class)->approve(app(Licences::class)->add($this->james, State::query()->findOrFail('FL'), '3456789', null, null)[0], User::factory()->superAdmin()->create());

        $this->actingAs($this->james)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
            ->where('region.code', 'FL')
            ->where('region.options', [['code' => 'FL', 'name' => 'Florida'], ['code' => 'TX', 'name' => 'Texas']]));

        $this->put('/settings/current-state', ['state' => 'TX'])->assertRedirect();
        $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('region.code', 'TX')->where('region.name', 'Texas'));
    });

    it('shows each person only content tagged for their state', function () {
        openState('FL');
        CatalogueSetting::query()->create(['integration_id' => 'zoom', 'states' => ['TX']]);
        CatalogueSetting::query()->create(['integration_id' => 'google-calendar', 'states' => ['FL']]);
        CatalogueSetting::query()->create(['integration_id' => 'outlook-calendar', 'states' => null]);

        $ids = static fn (array $codes): array => CatalogueSetting::query()->forStates($codes)->orderBy('integration_id')->pluck('integration_id')->all();

        expect($ids(['TX']))->toBe(['outlook-calendar', 'zoom'])
            ->and($ids(['FL']))->toBe(['google-calendar', 'outlook-calendar'])
            ->and($ids(['tx', 'fl']))->toBe(['google-calendar', 'outlook-calendar', 'zoom']);
    });

    it('exports licences with the person\'s data', function () {
        app(Licences::class)->add($this->james, State::query()->findOrFail('TX'), '0798765', null, null);

        $export = app(DataExport::class)->for($this->james);

        expect($export['licences'])->toHaveCount(1)->and($export['licences'][0]['number'])->toBe('0798765');
    });
});

describe('checking a number before adding it (agent journey screen 149)', function () {
    it('shows what the regulator holds without saving', function () {
        $this->actingAs($this->james);

        $this->postJson('/api/v1/me/licences/check', ['state' => 'tx', 'number' => '0798765'])->assertOk()->assertExactJson([
            'check' => 'verified',
            'record' => ['name' => 'James Miller', 'type' => 'sales_agent', 'status' => 'active', 'expires_on' => '2027-03-31'],
            'source' => 'Checked with REOS demo license records (Simulator).',
        ]);
        $this->postJson('/api/v1/me/licences/check', ['state' => 'TX', 'number' => '0798888'])->assertJsonPath('check', 'name_mismatch')->assertJsonPath('record.name', 'Pat Jordan');
        $this->postJson('/api/v1/me/licences/check', ['state' => 'TX', 'number' => '0790000'])->assertJsonPath('check', 'not_found')->assertJsonPath('record', null);
        $this->postJson('/api/v1/me/licences/check', ['state' => 'LA', 'number' => '1'])->assertJsonPath('check', 'no_lookup')->assertJsonPath('source', null);

        expect(Licence::query()->count())->toBe(0);
    });
});
