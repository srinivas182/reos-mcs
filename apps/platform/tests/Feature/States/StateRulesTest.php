<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\BrokerRole;
use App\Models\AuditEvent;
use App\Models\CeCompletion;
use App\Models\Licence;
use App\Models\State;
use App\Models\StateRule;
use App\Models\User;
use App\Notifications\StateRulesChanged;
use App\Services\Licences\Licences;
use App\Services\Onboarding\States;
use App\Services\StateRules\CeRequirements;
use App\Services\StateRules\StateRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-22 10:00:00');
    $this->texas = State::query()->findOrFail('TX');
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->james = User::factory()->create(['name' => 'James Miller']);
    [$this->licence] = app(Licences::class)->add($this->james, $this->texas, '0798765', null, null);
});

function completed(User $user, string $title, ?string $code, float $hours, string $on, string $source = 'reos'): void
{
    CeCompletion::query()->create(['user_id' => $user->id, 'state_code' => 'TX', 'title' => $title, 'course_code' => $code, 'hours' => $hours, 'source' => $source, 'completed_on' => $on]);
}

function ce(Licence $licence, ?string $on = null): array
{
    return app(CeRequirements::class)->for($licence->refresh(), $on === null ? null : CarbonImmutable::parse($on));
}

describe('Texas, seeded from TREC (A5.2)', function () {
    it('has the CE rules with their sources and effective dates', function () {
        $rules = app(StateRules::class)->inForce('TX', CarbonImmutable::today());

        expect($rules['ce.hours']->value)->toBe(['number' => 18])
            ->and($rules['ce.hours']->citation)->toBe('22 TAC §535.92(a)')
            ->and($rules['ce.hours']->effective_from->toDateString())->toBe('2023-08-29')
            ->and(collect($rules['ce.mandatory_courses']->value['courses'])->pluck('hours', 'code')->all())->toBe(['legal_update_1' => 4, 'legal_update_2' => 4, 'contracts' => 3])
            ->and($rules['ce.broker_responsibility']->value['roles'])->toBe(['sponsoring_broker', 'designated_broker', 'delegated_supervisor'])
            ->and($rules['ce.deferral']->value)->toBe(['fee_usd' => 200, 'days' => 60])
            ->and($rules['licensing.qualifying_hours']->value)->toBe(['number' => 180])
            ->and($rules['advertising.broker_name_size']->citation)->toBe('22 TAC §535.155(a)(2)');
    });

    it('ticks "Regulator rules" on the launch checklist itself once licensing, exam and CE rules are published', function () {
        expect(States::checklist($this->texas)['rules'])->not->toBeNull()
            ->and(States::checklist(State::query()->findOrFail('LA'))['rules'])->toBeNull();
    });
});

describe("a licence's CE requirement comes from the rules (A5.2 done when)", function () {
    it('counts completed courses against 18 hours: Legal Update I and II, contracts and electives', function () {
        completed($this->james, 'Legal Update I: Laws, Rules and Forms', 'legal_update_1', 4, '2026-02-10');
        completed($this->james, 'Real estate contracts', 'contracts', 3, '2026-05-18');
        completed($this->james, 'Negotiating offers', null, 2, '2026-09-03');
        // Before this period: doesn't count.
        completed($this->james, 'Old elective', null, 5, '2024-12-01');

        $ce = ce($this->licence);

        expect($ce)->toMatchArray([
            'due_on' => '2027-03-31', 'period_start' => '2025-03-31', 'days_left' => 129,
            'hours_required' => 18.0, 'hours_done' => 9.0, 'hours_left' => 9.0, 'complete' => false, 'first_renewal' => false,
            'deferral' => ['fee_usd' => 200.0, 'days' => 60],
        ])
            ->and(collect($ce['parts'])->map(fn ($p) => [$p['code'], $p['hours'], $p['hours_done'], $p['done']])->all())->toBe([
                ['legal_update_1', 4.0, 4.0, true],
                ['legal_update_2', 4.0, 0.0, false],
                ['contracts', 3.0, 3.0, true],
                ['electives', 7.0, 2.0, false],
            ])
            ->and(collect($ce['sources'])->pluck('key')->all())->not->toContain('ce.broker_responsibility');
    });

    it('adds Broker Responsibility for sponsoring brokers, designated brokers and delegated supervisors', function () {
        app(Licences::class)->setRole($this->licence, BrokerRole::DelegatedSupervisor, $this->james);

        $ce = ce($this->licence);

        expect(collect($ce['parts'])->pluck('hours', 'code')->all())->toBe(['legal_update_1' => 4.0, 'legal_update_2' => 4.0, 'contracts' => 3.0, 'broker_responsibility' => 6.0, 'electives' => 1.0])
            ->and($ce['hours_required'])->toBe(18.0)
            ->and(collect($ce['sources'])->pluck('citation')->all())->toContain('22 TAC §535.92(a)(4)');
    });

    it('counts a course taken twice once, and caps commission meeting hours', function () {
        completed($this->james, 'Negotiating offers', null, 3, '2026-01-10');
        completed($this->james, 'Negotiating offers', null, 3, '2026-06-10');
        completed($this->james, 'TREC meeting, May', null, 3, '2026-05-01', 'commission_meeting');
        completed($this->james, 'TREC meeting, August', null, 3, '2026-08-01', 'commission_meeting');

        expect(collect(ce($this->licence)['parts'])->firstWhere('code', 'electives')['hours_done'])->toBe(7.0)
            ->and(ce($this->licence)['hours_done'])->toBe(7.0);
    });

    it('uses the SAE hours and Legal Update I and II on a first renewal', function () {
        $this->licence->forceFill(['first_renewal' => true])->save();
        completed($this->james, 'SAE: Real Estate Brokerage', 'sae', 30, '2026-03-01');

        $ce = ce($this->licence);

        expect($ce['first_renewal'])->toBeTrue()
            ->and(collect($ce['parts'])->map(fn ($p) => [$p['code'], $p['hours'], $p['hours_done']])->all())->toBe([
                ['sae', 90.0, 30.0], ['legal_update_1', 4.0, 0.0], ['legal_update_2', 4.0, 0.0],
            ])
            ->and($ce['hours_required'])->toBe(98.0);
    });

    it('is complete when every part is done', function () {
        completed($this->james, 'LU I', 'legal_update_1', 4, '2026-01-01');
        completed($this->james, 'LU II', 'legal_update_2', 4, '2026-01-02');
        completed($this->james, 'Contracts', 'contracts', 3, '2026-01-03');
        completed($this->james, 'Elective A', null, 4, '2026-01-04');
        completed($this->james, 'Elective B', null, 3, '2026-01-05');

        expect(ce($this->licence))->toMatchArray(['complete' => true, 'hours_left' => 0.0, 'hours_done' => 18.0]);
    });

    it('changes the requirement from a new version\'s effective date only', function () {
        app(StateRules::class)->draft($this->texas, 'ce.hours', [
            'value' => ['number' => '20'], 'citation' => '22 TAC §535.92(a) (amended)', 'source_url' => 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-92', 'effective_from' => '2027-01-01', 'note' => 'Test change',
        ], $this->ops);

        // A draft changes nothing.
        expect(ce($this->licence)['hours_required'])->toBe(18.0);

        app(StateRules::class)->publish($this->texas, $this->ops);

        expect(ce($this->licence)['hours_required'])->toBe(18.0)
            ->and(ce($this->licence, '2027-01-01')['hours_required'])->toBe(20.0)
            ->and(StateRule::query()->where('key', 'ce.hours')->orderBy('version')->pluck('version')->all())->toBe([1, 2]);

        Notification::assertSentTo($this->james, StateRulesChanged::class, fn (StateRulesChanged $n) => $n->keys === ['ce.hours'] && $n->from === '2027-01-01');
    });

    it('serves the requirement to the phone and inside each licence', function () {
        completed($this->james, 'LU I', 'legal_update_1', 4, '2026-01-01');
        $this->actingAs($this->james);

        $this->getJson("/api/v1/me/licences/{$this->licence->id}/ce")->assertOk()->assertJsonPath('data.hours_done', 4)->assertJsonPath('data.parts.0.done', true);
        $this->getJson('/api/v1/me/licences')->assertJsonPath('data.0.ce.hours_required', 18);
        $this->getJson('/api/v1/me/licences/999/ce')->assertNotFound();
    });

    it('shows CE hours on the agent dashboard for the state in use', function () {
        $agent = User::factory()->withRole(Role::Agent)->create(['name' => 'Rosa Lima']);
        app(Licences::class)->add($agent, $this->texas, '0712345', null, null);

        $this->actingAs($agent)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('ce.hours_required', 18)
            ->where('ce.state_name', 'Texas'));
    });

    it('has no requirement while a licence waits for review, or where a state has no CE rules', function () {
        [$review] = app(Licences::class)->add($this->james, State::query()->findOrFail('LA'), '1', null, null);

        expect(app(CeRequirements::class)->for($review))->toBeNull();
        $this->actingAs($this->james)->getJson('/api/v1/me/licences')->assertJsonPath('data.1.ce', null);
    });
});

describe('State rules screen (web journey screen 62)', function () {
    it('shows each rule with where it is used, its source and versions', function () {
        $this->actingAs($this->ops)->get('/admin/states/TX/rules')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/states/rules')
            ->where('state.name', 'Texas')
            ->where('topics', ['licensing', 'exam', 'ce', 'advertising', 'brokerage', 'forms', 'courses', 'agents'])
            ->where('liveFeatures', ['ce_card', 'course_approvals', 'test_prep', 'licence_journey', 'live_classes', 'agent_modes', 'ce_wallet', 'virtual_broker'])
            ->where('rules', fn ($rules) => ($hours = collect($rules)->firstWhere('key', 'ce.hours'))
                && $hours['current']['value'] === ['number' => 18]
                && $hours['current']['citation'] === '22 TAC §535.92(a)'
                && $hours['used_by'] === ['ce_card', 'broker_compliance', 'course_catalogue']
                && count($hours['versions']) === 1
                && $hours['draft'] === null));
    });

    it('saves drafts (checked against the rule type), discards them and publishes together, audited', function () {
        $this->actingAs($this->ops);
        $body = ['value' => ['fee_usd' => 250, 'days' => 60], 'citation' => 'TREC renewal requirements', 'source_url' => 'https://trec.texas.gov/', 'effective_from' => '2027-02-01'];

        $this->put('/admin/states/TX/rules/ce.deferral', ['value' => ['fee_usd' => 'lots'], 'citation' => 'x', 'effective_from' => '2027-02-01'])
            ->assertSessionHasErrors(['value.fee_usd', 'value.days']);
        $this->put('/admin/states/TX/rules/ce.nonsense', $body)->assertSessionHasErrors('key');
        $this->put('/admin/states/TX/rules/ce.deferral', [...$body, 'source_url' => 'http://insecure.example'])->assertSessionHasErrors('source_url');

        $this->put('/admin/states/TX/rules/ce.deferral', $body)->assertRedirect();
        $this->put('/admin/states/TX/rules/ce.deferral', [...$body, 'value' => ['fee_usd' => 225, 'days' => 60]])->assertRedirect();
        $draft = StateRule::query()->where('status', 'draft')->sole();
        expect($draft->version)->toBe(2)->and($draft->value)->toBe(['fee_usd' => 225, 'days' => 60]);

        $this->get('/admin/states/TX/rules')->assertInertia(fn (Assert $page) => $page->where('rules', fn ($rules) => collect($rules)->firstWhere('key', 'ce.deferral')['draft']['value'] === ['fee_usd' => 225, 'days' => 60]));

        $this->delete("/admin/states/TX/rules/drafts/{$draft->id}")->assertRedirect();
        expect(StateRule::query()->where('status', 'draft')->count())->toBe(0);

        $this->put('/admin/states/TX/rules/ce.deferral', $body);
        $this->post('/admin/states/TX/rules/publish')->assertRedirect();

        expect(StateRule::query()->where('key', 'ce.deferral')->where('status', 'published')->count())->toBe(2)
            ->and(AuditEvent::query()->where('action', 'state_rule.published')->sole()->properties)->toBe([
                'before' => ['ce.deferral' => ['fee_usd' => 200, 'days' => 60]],
                'after' => ['ce.deferral' => ['fee_usd' => 250, 'days' => 60]],
            ]);
        Notification::assertSentTo($this->james, StateRulesChanged::class);
    });

    it("doesn't tell licence holders about non-CE changes", function () {
        $rules = app(StateRules::class);
        $rules->draft($this->texas, 'exam.vendor', ['value' => ['text' => 'Pearson VUE'], 'citation' => 'TREC', 'effective_from' => '2026-12-01'], $this->ops);
        $rules->publish($this->texas, $this->ops);

        Notification::assertNothingSent();
    });

    it('is for super admins only', function () {
        $this->actingAs($this->james)->get('/admin/states/TX/rules')->assertForbidden();
        $this->actingAs($this->james)->post('/admin/states/TX/rules/publish')->assertForbidden();
    });
});
