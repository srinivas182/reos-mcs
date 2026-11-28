<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Billing\Ledger\SponsorPools;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Messaging\Relationships;
use App\Models\AuditEvent;
use App\Models\CandidateSponsorship;
use App\Models\Licence;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Recruiting\SponsoredHelp;
use App\Services\Ai\AiCredits;
use App\Services\Authorization\Memberships;
use App\Services\Privacy\Consents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Sponsored AI help in offers, and licensed agents open to a move (A17.2, ADR 0083).
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->linda = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park']);
    $this->ana = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ana Ruiz']);
});

function poolOf(Organisation $org): int
{
    $ledger = app(Ledger::class);
    $account = $ledger->find("organisation:{$org->id}:credits");

    return $account === null ? 0 : $ledger->balance($account);
}

describe('sponsored AI help (A17.2)', function () {
    it('lets a candidate accept an offer with sponsored help, paid from the brokerage\'s pool (the done-when)', function () {
        app(SponsorPools::class)->fund($this->lone, 1000, 0, 'test', $this->linda);
        $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/offers/opt-in', ['on' => true, 'city' => 'Katy'])->assertOk();
        $this->actingAs($this->linda)->put("/recruiting/candidates/{$this->ana->id}/offer", ['office_city' => 'Katy', 'split_percent' => 70, 'monthly_fee_cents' => 0, 'mentoring' => true, 'ai_credits' => 200])->assertSessionHasNoErrors();

        // The candidate sees that help is sponsored, never the numbers.
        $offer = $this->actingAs($this->ana)->getJson('/api/v1/licence-journey/offers')->assertOk()->json('data.offers.0');
        expect($offer['ai_help'])->toBeTrue()->and($offer['ai_credits'])->toBeNull();
        $this->postJson("/api/v1/licence-journey/offers/{$offer['id']}/accept")->assertOk();

        $sponsorship = CandidateSponsorship::query()->sole();
        expect($sponsorship)->cap->toBe(200)->used->toBe(0);

        // Beyond this month's free credits, her answers draw on Lone Star's pool.
        $credits = app(AiCredits::class);
        $ledger = app(Ledger::class);
        $credits->ensureMonth($this->ana);
        $monthly = $ledger->person($this->ana, 'monthly');
        $ledger->post('test_spend', [[$monthly, -$ledger->balance($monthly)], [$ledger->platform('ai_used'), $ledger->balance($monthly)]]);
        expect($credits->candidatePayer($this->ana, 5))->toBe('offer');
        $credits->chargeCandidate($this->ana, 'offer', 'ask', 'test', 5);
        expect(poolOf($this->lone))->toBe(995)->and($sponsorship->refresh()->used)->toBe(5);

        // The broker sees use and can change the cap; past it, nothing more is sponsored.
        $this->actingAs($this->linda)->get('/recruiting')->assertInertia(fn (Assert $page) => $page
            ->where('sponsored.pool', 995)->where('sponsored.candidates.0.used', 5)->where('sponsored.candidates.0.candidate', 'Ana R.'));
        $this->put("/recruiting/sponsorships/{$sponsorship->uuid}", ['cap' => 5])->assertSessionHasNoErrors();
        expect($credits->candidatePayer($this->ana, 5))->not->toBe('offer');

        // Once she passes, the sponsorship ends.
        $this->put("/recruiting/sponsorships/{$sponsorship->uuid}", ['cap' => 500])->assertSessionHasNoErrors();
        LicenceJourney::query()->where('user_id', $this->ana->id)->update(['passed_on' => '2026-11-23']);
        expect($credits->candidatePayer($this->ana, 5))->not->toBe('offer')
            ->and($sponsorship->refresh()->end_reason)->toBe('passed')
            ->and(AuditEvent::query()->where('action', 'recruiting.sponsorship_ended')->exists())->toBeTrue();
    });

    it('ends when the candidate joins another brokerage', function () {
        app(SponsorPools::class)->fund($this->lone, 1000, 0, 'test', $this->linda);
        CandidateSponsorship::query()->create(['uuid' => (string) Str::uuid7(), 'brokerage_id' => $this->lone->id, 'candidate_id' => $this->ana->id, 'cap' => 100]);
        $gulf = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Gulf Coast Homes']);
        app(Memberships::class)->attach($this->ana, Role::Agent, $gulf);

        expect(app(SponsoredHelp::class)->payerFor($this->ana, 1))->toBeNull()
            ->and(CandidateSponsorship::query()->sole()->end_reason)->toBe('other_sponsor');
    });
});

describe('licensed agents open to a move (A17.2)', function () {
    beforeEach(function () {
        $this->gulf = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Gulf Coast Homes', 'states' => ['TX']]);
        $this->gary = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->gulf)->create(['name' => 'Gary Moss']);
        $this->omar = User::factory()->withRole(Role::Agent, $this->gulf)->create(['name' => 'Omar Haddad']);
        Licence::query()->create(['user_id' => $this->omar->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0701234', 'status' => LicenceStatus::Active,
            'licensed_on' => '2019-03-01', 'expires_on' => '2027-12-31', 'holder_name' => 'Omar Haddad', 'verified_by' => 'simulator', 'verified_at' => now()]);
    });

    it('shows only agents who opted in, lets the broker invite them to talk, and never tells their broker', function () {
        $this->actingAs($this->linda)->get('/recruiting')->assertInertia(fn (Assert $page) => $page->where('agents', []));

        app(Consents::class)->grant($this->omar, ConsentPurpose::Recruiting, ConsentMethod::Web);
        $this->actingAs($this->linda)->get('/recruiting')->assertInertia(fn (Assert $page) => $page->where('agents.0.name', 'Omar H.'));
        // His own brokerage never sees him in the list.
        $this->actingAs($this->gary, 'sanctum')->getJson('/api/v1/organisation/recruiting/agents')->assertOk()->assertJsonPath('data', []);

        $this->actingAs($this->linda)->post("/recruiting/agents/{$this->omar->id}/invite", ['message' => 'Coffee next week?'])->assertRedirect();
        expect(app(Relationships::class)->canMessage($this->linda, $this->omar))->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'recruiting.agent_invited')->sole()->organisation_id)->toBe($this->lone->id);
        Notification::assertNothingSentTo($this->gary);

        // Turned off: he's gone from the list, and can't be invited.
        app(Consents::class)->withdraw($this->omar, ConsentPurpose::Recruiting, ConsentMethod::Web);
        $this->actingAs($this->linda, 'sanctum')->postJson("/api/v1/organisation/recruiting/agents/{$this->omar->id}/invite", [])->assertNotFound();
    });
});
