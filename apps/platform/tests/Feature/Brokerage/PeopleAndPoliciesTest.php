<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\PolicyPassage;
use App\Models\User;
use App\VirtualBroker\PolicyKnowledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * People and permissions, and brokerage rules checked against TREC (A15.2, ADR 0071).
 */

beforeEach(function () {
    $this->travelTo('2026-11-28 09:00:00');
    Notification::fake();
    Storage::fake(config()->string('reos.content.disk'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    // Linda set the brokerage up: its designated broker.
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->ana = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Ana Office']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
});

describe('brokerage rules (A15.2)', function () {
    it('flags a conflicting rule with its TREC source (the done-when)', function () {
        $check = $this->actingAs($this->linda)->postJson('/api/v1/organisation/policies/check', ['text' => 'Agents may accept referral fees directly from title companies.'])->assertOk()->json('data.conflict');
        expect($check)->toMatchArray(['rule' => 'brokerage.compensation', 'citation' => '22 TAC §535.3'])->and($check['url'])->toStartWith('https://');

        $rule = $this->postJson('/api/v1/organisation/policies/rules', ['topic' => 'Referral fees', 'text' => 'Agents may accept referral fees directly from title companies.'])->assertOk()->json('data');
        expect($rule['conflict'])->toMatchArray(['rule' => 'brokerage.compensation', 'citation' => '22 TAC §535.3'])
            ->and($rule['conflict']['excerpt'])->toContain('directly');

        // Fixed: the conflict goes.
        $fixed = $this->putJson("/api/v1/organisation/policies/rules/{$rule['id']}", ['topic' => 'Referral fees', 'text' => 'Referral fees are paid to the brokerage, which pays the agent.'])->assertOk()->json('data');
        expect($fixed['conflict'])->toBeNull();

        $ok = $this->postJson('/api/v1/organisation/policies/rules', ['topic' => 'Listings', 'text' => 'Send every new listing to the office before it goes live.'])->json('data');
        expect($ok['conflict'])->toBeNull();
        $this->getJson('/api/v1/organisation/policies')->assertOk()->assertJsonCount(2, 'data.rules')->assertJsonPath('data.can_manage', true);
        $this->get('/organisation/policies')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/policies')->has('policies.rules', 2));

        // Removed, then put back (Undo).
        $this->deleteJson("/api/v1/organisation/policies/rules/{$ok['id']}")->assertNoContent();
        $this->postJson("/api/v1/organisation/policies/rules/{$ok['id']}/restore")->assertNoContent();
        expect(AuditEvent::query()->where('action', 'like', 'policy_rule.%')->pluck('action')->sort()->values()->all())
            ->toBe(['policy_rule.added', 'policy_rule.added', 'policy_rule.changed', 'policy_rule.removed', 'policy_rule.restored']);
    });

    it('answers agents from brokerage rules, with TREC first when a rule conflicts', function () {
        $this->actingAs($this->linda)->postJson('/api/v1/organisation/policies/rules', ['topic' => 'Listing photos', 'text' => 'Listing photos are taken by Bayou Lens; book them through the office.'])->assertOk();
        $this->postJson('/api/v1/organisation/policies/rules', ['topic' => 'Earnest money deposits', 'text' => 'Earnest money is deposited within five business days.'])->assertOk();

        $photos = $this->actingAs($this->james)->postJson('/api/v1/virtual-broker/questions', ['question' => 'Who takes listing photos?'])->assertCreated()->json('data');
        expect($photos['sources'][0])->toMatchArray(['kind' => 'faq', 'title' => 'Listing photos']);

        $money = $this->postJson('/api/v1/virtual-broker/questions', ['question' => 'How many days do I have to deposit the earnest money?'])->assertCreated()->json('data');
        expect($money['sources'][0])->toMatchArray(['kind' => 'rule', 'citation' => '22 TAC §535.146']);
    });

    it('checks an FAQ answer against TREC too', function () {
        $passage = app(PolicyKnowledge::class)->addFaq($this->lone, 'Can I take my commission directly?', 'Yes, agents can be paid directly by the title company.', null);
        expect($passage->conflict['rule'] ?? null)->toBe('brokerage.compensation');
    });
});

describe('people and permissions (A15.2)', function () {
    it('lets only the designated broker give duties, and holds others to theirs', function () {
        $staff = $this->actingAs($this->linda)->getJson('/api/v1/organisation/staff')->assertOk()->json('data');
        expect($staff['designated'])->toBeTrue()
            ->and(collect($staff['people'])->pluck('title', 'name')->all())->toBe(['Linda Park' => 'designated_broker', 'Ana Office' => 'office_admin'])
            ->and(collect($staff['people'])->firstWhere('name', 'Ana Office')['duties'])->toBe([]);

        // Ana has no duties yet: no policies, no inviting, no seats, no posts.
        $this->actingAs($this->ana)->postJson('/api/v1/organisation/policies/rules', ['topic' => 'Listings', 'text' => 'Send listings to the office.'])->assertForbidden();
        $this->post('/organisation/virtual-broker/manual', ['file' => UploadedFile::fake()->createWithContent('manual.txt', "# 1. Listings\nSend listings to the office.")])->assertForbidden();
        $this->put('/organisation/advertising', ['approval_new_agents' => true, 'approval_all' => false, 'banned' => []])->assertForbidden();
        $this->postJson('/api/v1/organisation/roster/invite')->assertForbidden();
        $this->putJson('/api/v1/organisation/seats', ['payer' => 'member', 'credits_pooled' => true])->assertForbidden();
        $this->putJson("/api/v1/organisation/staff/{$this->ana->id}", ['duties' => ['manage_policies']])->assertForbidden();
        $this->getJson('/api/v1/organisation/policies')->assertJsonPath('data.can_manage', false);

        // Linda turns on "manage policies" for Ana.
        $this->actingAs($this->linda)->putJson("/api/v1/organisation/staff/{$this->ana->id}", ['duties' => ['manage_policies', 'not_a_duty']])->assertUnprocessable();
        $this->putJson("/api/v1/organisation/staff/{$this->ana->id}", ['duties' => ['manage_policies']])->assertOk();
        $this->putJson("/api/v1/organisation/staff/{$this->linda->id}", ['duties' => []])->assertUnprocessable();
        expect(AuditEvent::query()->where('action', 'brokerage.duties_changed')->count())->toBe(1);

        $this->actingAs($this->ana)->postJson('/api/v1/organisation/policies/rules', ['topic' => 'Listings', 'text' => 'Send every new listing to the office first.'])->assertOk();
        // Still not seats or roles: those are the designated broker's.
        $this->putJson('/api/v1/organisation/seats', ['payer' => 'member', 'credits_pooled' => true])->assertForbidden();
        expect(PolicyPassage::query()->where('kind', 'policy')->count())->toBe(1);
        $this->get('/organisation/settings')->assertInertia(fn (Assert $page) => $page->where('staff.designated', false)->has('staff.people', 2));
    });

    it('keeps people and policies to the brokerage\'s own brokers', function () {
        $this->actingAs($this->james)->getJson('/api/v1/organisation/staff')->assertNotFound();
        $this->getJson('/api/v1/organisation/policies')->assertNotFound();
    });
});
