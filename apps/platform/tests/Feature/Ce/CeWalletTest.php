<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\EntitlementSource;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\CeCompletion;
use App\Models\CeReminder;
use App\Models\Licence;
use App\Models\LicenceRenewal;
use App\Models\RegulatorRecord;
use App\Models\User;
use App\Notifications\CeRenewalDue;
use Database\Seeders\Demo\DemoBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-24 15:00:00');
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->licence = Licence::query()->create([
        'user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active,
        'holder_name' => 'James Miller', 'licensed_on' => '2021-01-15', 'expires_on' => '2027-01-15', 'sponsor_name' => 'Linda Park', 'verified_by' => 'trec-licence', 'verified_at' => now(),
    ]);
});

function ceDone(User $user, string $title, float $hours, string $on, string $source = 'reos', ?string $code = null): CeCompletion
{
    return CeCompletion::query()->forceCreate(['user_id' => $user->id, 'state_code' => 'TX', 'title' => $title, 'hours' => $hours, 'completed_on' => $on, 'source' => $source, 'course_code' => $code, 'provider' => $source === 'reos' ? 'REOS' : 'Champions School']);
}

/** @param array<string, mixed> $values */
function trecRow(array $values = []): RegulatorRecord
{
    return RegulatorRecord::query()->create([
        'authority' => 'TREC', 'kind' => 'agents', 'licence_type' => 'SALE', 'number' => '0798765', 'number_digits' => '798765', 'name' => 'James Miller', 'status' => '20', 'active' => true,
        'licensed_on' => '2021-01-15', 'expires_on' => '2027-01-15', 'education_status' => 0, 'mce_status' => 1, 'sponsor_number' => '9012345', 'sponsor_name' => 'Linda Park', 'imported_at' => now(),
        ...$values,
    ]);
}

describe('the CE wallet (A11.2)', function () {
    it('shows hours against the renewal with REOS-reported and self-reported completions', function () {
        app(DemoBilling::class)->seed();
        ceDone($this->james, 'Legal Update I', 4, '2026-03-01', 'reos', 'legal_update_1');
        ceDone($this->james, 'Negotiation essentials', 3, '2026-10-02', 'other_provider');
        ceDone($this->james, 'Old course', 6, '2024-12-01');
        trecRow();

        $wallet = $this->actingAs($this->james)->getJson('/api/v1/me/ce')->assertOk()->json('data.0');

        expect($wallet['licence'])->toMatchArray(['state_code' => 'TX', 'number' => '0798765', 'expires_on' => '2027-01-15'])
            ->and($wallet['ce'])->toMatchArray(['due_on' => '2027-01-15', 'hours_required' => 18, 'hours_done' => 7, 'complete' => false])
            ->and(array_column($wallet['completions'], 'status'))->toBe(['self_reported', 'reos_reported'])
            ->and($wallet['trec'])->toMatchArray(['mce' => 'outstanding', 'education' => 'none'])
            ->and(array_column($wallet['renewal']['steps'], 'code'))->toBe(['ce_reported', 'sponsor', 'renew', 'confirm'])
            ->and(array_column($wallet['renewal']['steps'], 'done'))->toBe([false, true, false, false])
            ->and($wallet['renewal']['steps'][2]['url'])->toBe('https://aca-prod.accela.com/TREC/Default.aspx')
            ->and($wallet['unlocked'])->toBeFalse()
            ->and($wallet['buy'])->toMatchArray(['product' => 'Agent Core', 'amount' => 2900]);

        app(Entitlements::class)->grant($this->james, ['learn.ce'], EntitlementSource::Admin, null, 'TX');
        $this->getJson('/api/v1/me/ce')->assertJsonPath('data.0.unlocked', true)->assertJsonPath('data.0.buy', null);
    });

    it('counts an outside certificate as self-reported until TREC\'s record confirms the period', function () {
        $outside = ceDone($this->james, 'Negotiation essentials', 3, '2026-10-02', 'other_provider');
        ceDone($this->james, 'Next year', 3, '2026-11-25', 'other_provider');
        trecRow(['mce_status' => 1]);

        $this->artisan('reos:ce:confirm')->expectsOutput('Confirmed 0 completions and 0 renewals.')->assertSuccessful();
        expect($outside->refresh()->confirmed_at)->toBeNull();

        RegulatorRecord::query()->update(['mce_status' => 2]);
        $this->artisan('reos:ce:confirm')->expectsOutput('Confirmed 1 completions and 0 renewals.');
        expect($outside->refresh())->confirmed_by->toBe('trec-licence')
            ->and($this->actingAs($this->james)->getJson('/api/v1/me/ce')->json('data.0.completions.1.status'))->toBe('on_record')
            ->and($this->getJson('/api/v1/me/ce')->json('data.0.renewal.steps.0.done'))->toBeTrue();
    });

    it('walks the renewal: renewed with TREC, then confirmed when the new expiry appears', function () {
        trecRow(['mce_status' => 2]);
        $this->actingAs($this->james)->putJson("/api/v1/me/licences/{$this->licence->id}/renewal", ['renewed' => true])->assertOk()
            ->assertJsonPath('data.renewal.steps.2.done', true)->assertJsonPath('data.renewal.steps.3.done', false);
        $this->putJson("/api/v1/me/licences/{$this->licence->id}/renewal", ['renewed' => false])->assertJsonPath('data.renewal.renewed_at', null);
        $this->putJson("/api/v1/me/licences/{$this->licence->id}/renewal", ['renewed' => true])->assertOk();

        RegulatorRecord::query()->update(['expires_on' => '2029-01-15', 'mce_status' => 1]);
        $this->artisan('reos:ce:confirm')->expectsOutput('Confirmed 0 completions and 1 renewals.');

        expect($this->licence->refresh()->expires_on?->toDateString())->toBe('2029-01-15')
            ->and(LicenceRenewal::query()->sole())->due_on->toStartWith('2027-01-15')->renewed_until->toStartWith('2029-01-15');
        $this->getJson('/api/v1/me/ce')->assertJsonPath('data.0.ce.due_on', '2029-01-15');

        $other = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($other)->putJson("/api/v1/me/licences/{$this->licence->id}/renewal", ['renewed' => true])->assertNotFound();
    });

    it('reminds 60, 30 and 7 days before while hours are missing, then says the renewal is ready', function () {
        Notification::fake();

        // 52 days before: the 60-day reminder, once.
        $this->artisan('reos:ce:reminders')->expectsOutput('Sent 1 CE reminders.');
        $this->artisan('reos:ce:reminders')->expectsOutput('Sent 0 CE reminders.');
        Notification::assertSentTo($this->james, CeRenewalDue::class, fn (CeRenewalDue $n): bool => $n->kind === 'days_60'
            && $n->inbox($this->james)['title'] === 'CE due in 52 days' && $n->inbox($this->james)['body'] === '18 CE hours left before your license renews on January 15, 2027.');

        // Missed days: only the nearest goes.
        $this->travelTo('2027-01-10 15:00:00');
        $this->artisan('reos:ce:reminders')->expectsOutput('Sent 1 CE reminders.');
        expect(CeReminder::query()->orderBy('id')->pluck('kind')->all())->toBe(['days_60', 'days_7']);

        ceDone($this->james, 'Legal Update I', 4, '2027-01-10', 'reos', 'legal_update_1');
        ceDone($this->james, 'Legal Update II', 4, '2027-01-10', 'reos', 'legal_update_2');
        ceDone($this->james, 'Contracts', 3, '2027-01-10', 'reos', 'contracts');
        ceDone($this->james, 'Electives', 7, '2027-01-10');
        $this->artisan('reos:ce:reminders')->expectsOutput('Sent 1 CE reminders.');
        Notification::assertSentTo($this->james, CeRenewalDue::class, fn (CeRenewalDue $n): bool => $n->kind === 'ready' && $n->inbox($this->james)['title'] === 'Renewal ready');
    });

    it('shows the wallet on the web', function () {
        ceDone($this->james, 'Negotiation essentials', 3, '2026-10-02', 'other_provider');
        $this->actingAs($this->james)->get('/ce')->assertInertia(fn (Assert $page) => $page->component('ce/index')
            ->has('wallets', 1)->where('wallets.0.completions.0.status', 'self_reported'));
        $this->put("/ce/licences/{$this->licence->id}/renewal", ['renewed' => true])->assertOk()->assertJsonPath('data.renewal.steps.2.done', true);
    });
});
