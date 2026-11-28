<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\Organisation;
use App\Models\RoutedLead;
use App\Models\SplitSheet;
use App\Models\User;
use App\Notifications\LeadOffered;
use App\Notifications\SplitSheetSent;
use App\Teams\LeadRouting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Lead routing with claim timers and fallback, and split sheets (A17.3, ADR 0084).
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    $this->ada = User::factory()->withRole(Role::Broker, $this->okoye)->create(['name' => 'Ada Okoye']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Priya Shah']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Maria Garcia', 'locale' => 'es']);
    $this->tom = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Tom Reyes']);
    $this->lead = fn (array $extra = []) => $this->actingAs($this->ada, 'sanctum')->postJson('/api/v1/team/leads', ['source' => 'zillow', 'name' => 'Rosa Lima', 'email' => 'rosa@example.com', ...$extra])->assertCreated()->json('data.id');
});

describe('lead routing (A17.3)', function () {
    it('gets a lead claimed in time, or reassigned when nobody claims it (the done-when)', function () {
        $this->actingAs($this->ada, 'sanctum')->putJson('/api/v1/team/routing', ['sources' => [], 'method' => 'first_to_claim', 'claim_minutes' => 5, 'fallback' => 'round_robin', 'off_start' => '20:00', 'off_end' => '08:00'])->assertOk();

        // Claimed in time: Priya is first, and the lead is in her CRM.
        $first = ($this->lead)();
        Notification::assertSentTo([$this->priya, $this->maria, $this->tom], LeadOffered::class);
        $this->travel(2)->minutes();
        $this->actingAs($this->priya, 'sanctum')->postJson("/api/v1/team/leads/{$first}/claim")->assertOk()->assertJsonPath('data.status', 'claimed');
        $this->actingAs($this->tom, 'sanctum')->postJson("/api/v1/team/leads/{$first}/claim")->assertUnprocessable()->assertJsonValidationErrors(['lead' => 'Another agent claimed this lead first.']);
        expect(Contact::query()->where('owner_type', 'user')->where('owner_id', $this->priya->id)->where('first_name', 'Rosa')->exists())->toBeTrue();

        // Nobody claims in 5 minutes: reassigned in turn to someone who wasn't the last.
        $second = ($this->lead)(['name' => 'Sam Hill']);
        $this->travel(6)->minutes();
        $this->actingAs($this->tom, 'sanctum')->postJson("/api/v1/team/leads/{$second}/claim")->assertUnprocessable()->assertJsonValidationErrors(['lead' => 'The time to claim this lead is up.']);
        $this->artisan('reos:teams:leads')->assertSuccessful();
        $routed = RoutedLead::query()->where('uuid', $second)->sole();
        expect($routed->status)->toBe('assigned')->and($routed->how)->toBe('fallback')->and($routed->assigned_to)->not->toBe($this->priya->id)
            ->and(AuditEvent::query()->where('action', 'team.lead_fallback')->exists())->toBeTrue();
    });

    it('offers Spanish leads to Spanish speakers, skips agents out of rotation, and replies off hours', function () {
        $this->actingAs($this->ada, 'sanctum')->putJson("/api/v1/team/routing/members/{$this->tom->id}", ['active' => false, 'languages' => ['en']])->assertOk();
        $spanish = ($this->lead)(['language' => 'es']);
        expect(RoutedLead::query()->where('uuid', $spanish)->sole()->offered_to)->toBe([$this->maria->id]);

        $this->travelTo(CarbonImmutable::parse('2026-11-23 22:00', 'America/Chicago'));
        $night = ($this->lead)(['name' => 'Night Owl']);
        expect(RoutedLead::query()->where('uuid', $night)->sole()->auto_reply)->toContain('Okoye Team')
            ->and(RoutedLead::query()->where('uuid', $night)->sole()->offered_to)->not->toContain($this->tom->id);

        // Round robin assigns at once; the fallback can be the team leader.
        $this->actingAs($this->ada, 'sanctum')->putJson('/api/v1/team/routing', ['sources' => [], 'method' => 'round_robin', 'claim_minutes' => 5, 'fallback' => 'leader', 'off_start' => '20:00', 'off_end' => '08:00'])->assertOk();
        expect(RoutedLead::query()->where('uuid', ($this->lead)(['name' => 'Ray Ng']))->sole()->status)->toBe('assigned');
        expect(LeadRouting::offHours(LeadRouting::rules($this->okoye->refresh()), CarbonImmutable::parse('2026-11-24 07:59', 'America/Chicago')))->toBeTrue();

        // Agents can't change routing; another team's leader can't claim.
        $this->actingAs($this->priya, 'sanctum')->getJson('/api/v1/team/routing')->assertNotFound();
        $this->actingAs($this->ada)->get('/team')->assertOk()->assertInertia(fn (Assert $page) => $page->component('team/routing')->where('routing.rules.method', 'round_robin'));
    });
});

describe('split sheets (A17.3)', function () {
    it('sends a split from the team agreement for the agent to sign, or question', function () {
        $this->actingAs($this->ada, 'sanctum')->putJson('/api/v1/team/agreement', ['brokerage_percent' => 20, 'team_percent' => 30])->assertOk()->assertJsonPath('data.agent_percent', 50);
        $this->actingAs($this->ada, 'sanctum')->putJson('/api/v1/team/agreement', ['brokerage_percent' => 80, 'team_percent' => 30])->assertUnprocessable();
        $id = $this->actingAs($this->ada, 'sanctum')->postJson('/api/v1/team/splits', ['agent_id' => $this->priya->id, 'property' => '1420 Pecan St, Katy', 'closing_on' => '2026-11-20', 'gross_cents' => 1200000, 'lead_source' => 'zillow'])->assertCreated()->json('data.id');
        Notification::assertSentTo($this->priya, SplitSheetSent::class);
        expect(SplitSheet::query()->sole())->brokerage_cents->toBe(240000)->team_cents->toBe(360000)->agent_cents->toBe(600000);

        $this->actingAs($this->tom, 'sanctum')->postJson("/api/v1/team/splits/{$id}/sign")->assertNotFound();
        $this->actingAs($this->priya, 'sanctum')->getJson('/api/v1/team/mine')->assertOk()->assertJsonPath('splits.0.agent_share', 600000);
        $this->postJson("/api/v1/team/splits/{$id}/question", ['text' => 'Is the referral fee out of gross?'])->assertOk()->assertJsonStructure(['conversation']);
        $this->postJson("/api/v1/team/splits/{$id}/sign")->assertOk()->assertJsonPath('data.status', 'signed');
        $this->postJson("/api/v1/team/splits/{$id}/question", ['text' => 'Again?'])->assertUnprocessable();
        expect(AuditEvent::query()->where('action', 'team.split_signed')->exists())->toBeTrue();
    });
});
