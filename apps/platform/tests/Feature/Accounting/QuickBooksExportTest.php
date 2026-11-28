<?php

declare(strict_types=1);

use App\Accounting\IntuitQuickBooks;
use App\Accounting\JournalEntry;
use App\Accounting\QuickBooksExports;
use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\AccountingConnection;
use App\Models\AccountingExport;
use App\Models\AuditEvent;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\SplitSheet;
use App\Models\User;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * QuickBooks Online for brokerages (B17.3, ADR 0087): connect, map the
 * accounts once, and post signed split sheets and payouts once each.
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'America/Chicago'));
    IntegrationSetting::query()->updateOrCreate(['integration_id' => 'quickbooks-online'], ['mode' => IntegrationMode::Simulator]);
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->ada = User::factory()->withRole(Role::Broker, $this->okoye)->create(['name' => 'Ada Okoye']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Priya Shah']);
    $this->map = array_combine(QuickBooksExports::ROLES, ['35', '81', '33', '34', '60', '36']);
    $this->sheet = function (): string {
        $this->actingAs($this->ada, 'sanctum')->putJson('/api/v1/team/agreement', ['brokerage_percent' => 20, 'team_percent' => 30])->assertOk();

        return $this->actingAs($this->ada, 'sanctum')->postJson('/api/v1/team/splits', ['agent_id' => $this->priya->id, 'property' => '1302 Live Oak St', 'closing_on' => '2026-11-20', 'gross_cents' => 1275000])->assertCreated()->json('data.id');
    };
    $this->connect = function (): void {
        $url = $this->actingAs($this->linda, 'sanctum')->postJson('/api/v1/organisation/accounting/connect')->assertOk()->json('data.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->actingAs($this->linda)->get('/organisation/accounting/quickbooks/callback?'.http_build_query($q))->assertRedirect('/organisation/accounting');
    };
});

it('posts a split sheet to QuickBooks (the done-when)', function () {
    ($this->connect)();
    $this->actingAs($this->linda, 'sanctum')->getJson('/api/v1/organisation/accounting')->assertOk()
        ->assertJsonPath('data.connection.company', 'Sandbox Company (simulated)')->assertJsonPath('data.connection.mapped', false)->assertJsonCount(7, 'data.chart');
    $this->actingAs($this->linda, 'sanctum')->putJson('/api/v1/organisation/accounting/accounts', ['accounts' => $this->map])->assertOk();

    // Priya signs: the journal entry posts at once.
    $id = ($this->sheet)();
    $this->actingAs($this->priya, 'sanctum')->postJson("/api/v1/team/splits/{$id}/sign")->assertOk();

    $sheet = app(Tenancy::class)->asPlatform(fn () => SplitSheet::query()->where('uuid', $id)->sole());
    $export = app(Tenancy::class)->asPlatform(fn () => AccountingExport::query()->where('subject_id', $id)->sole());
    expect($export->status)->toBe('posted')->and($export->amount_cents)->toBe(1275000)->and($export->ref)->toStartWith('JE-')
        ->and($sheet->export_ref)->toBe($export->ref)->and($sheet->exported_at)->not->toBeNull();

    // The entry balances: the gross in, the brokerage's, team's and agent's shares out.
    $entry = QuickBooksExports::sheetEntry($sheet, 'Priya Shah', $this->map)->toQuickBooks();
    expect(array_column(array_column($entry['Line'], 'JournalEntryLineDetail'), 'PostingType'))->toBe(['Debit', 'Credit', 'Credit', 'Credit'])
        ->and(array_column($entry['Line'], 'Amount'))->toBe([12750.0, 2550.0, 3825.0, 6375.0]);

    // Export now doesn't post it twice.
    $this->actingAs($this->linda, 'sanctum')->postJson('/api/v1/organisation/accounting/export')->assertOk()->assertJsonPath('data.posted', 0);
    expect(app(Tenancy::class)->asPlatform(fn () => AccountingExport::query()->count()))->toBe(1)
        ->and(AuditEvent::query()->whereIn('action', ['accounting.connected', 'accounting.accounts_mapped', 'accounting.exported'])->where('organisation_id', $this->lone->id)->count())->toBe(3);
});

it('waits for the mapping, then exports what signed before', function () {
    $id = ($this->sheet)();
    $this->actingAs($this->priya, 'sanctum')->postJson("/api/v1/team/splits/{$id}/sign")->assertOk();
    $this->actingAs($this->linda, 'sanctum')->postJson('/api/v1/organisation/accounting/export')->assertUnprocessable()->assertJsonValidationErrors(['accounting' => 'Connect QuickBooks first.']);

    ($this->connect)();
    $this->actingAs($this->linda, 'sanctum')->postJson('/api/v1/organisation/accounting/export')->assertUnprocessable()->assertJsonValidationErrors(['accounting' => 'Map your accounts first.']);
    $this->actingAs($this->linda, 'sanctum')->putJson('/api/v1/organisation/accounting/accounts', ['accounts' => [...$this->map, 'clearing' => '999']])->assertUnprocessable()->assertJsonValidationErrors('accounts.clearing');
    $this->actingAs($this->linda, 'sanctum')->putJson('/api/v1/organisation/accounting/accounts', ['accounts' => $this->map])->assertOk();
    $this->actingAs($this->linda, 'sanctum')->getJson('/api/v1/organisation/accounting')->assertJsonPath('data.pending.split_sheets.0.id', $id);

    $this->artisan('reos:accounting:export')->assertSuccessful();
    $this->actingAs($this->linda, 'sanctum')->getJson('/api/v1/organisation/accounting')->assertJsonCount(0, 'data.pending.split_sheets')->assertJsonPath('data.exports.0.status', 'posted');
});

it('refuses a sign-in that someone else started, and keeps brokerages apart', function () {
    $url = $this->actingAs($this->linda, 'sanctum')->postJson('/api/v1/organisation/accounting/connect')->assertOk()->json('data.url');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    $other = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
    $omar = User::factory()->withRole(Role::Broker, $other)->withTwoFactor()->create();
    $this->actingAs($omar)->get('/organisation/accounting/quickbooks/callback?'.http_build_query($q))->assertSessionHasErrors('state');
    expect(app(Tenancy::class)->asPlatform(fn () => AccountingConnection::query()->count()))->toBe(0);

    // Agents can't reach it.
    $this->actingAs($this->priya, 'sanctum')->getJson('/api/v1/organisation/accounting')->assertNotFound();
});

it('posts a journal entry to Intuit with a fresh token', function () {
    Http::fake([
        IntuitQuickBooks::TOKENS => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600]),
        'sandbox-quickbooks.api.intuit.com/*' => Http::response(['JournalEntry' => ['Id' => '146']]),
    ]);
    $connection = new AccountingConnection(['realm_id' => '4620816365', 'refresh_token' => 'old-refresh', 'token_expires_at' => now()->subMinute()]);
    $entry = new JournalEntry(CarbonImmutable::parse('2026-11-20'), 'REOS-1', 'Split sheet', [
        ['account' => '35', 'posting' => 'Debit', 'cents' => 1000, 'description' => 'In'],
        ['account' => '81', 'posting' => 'Credit', 'cents' => 1000, 'description' => 'Out'],
    ]);

    expect((new IntuitQuickBooks('client', 'secret', true))->post($connection->forceFill(['id' => 1]), $entry))->toBe('146');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v3/company/4620816365/journalentry') && $r->hasHeader('Authorization', 'Bearer new-access') && $r['Line'][0]['JournalEntryLineDetail']['PostingType'] === 'Debit');
    expect(fn () => new JournalEntry(CarbonImmutable::now(), 'x', 'x', [['account' => '1', 'posting' => 'Debit', 'cents' => 5, 'description' => '']]))->toThrow(InvalidArgumentException::class);
});

it('shows QuickBooks on the web', function () {
    ($this->connect)();
    $this->actingAs($this->linda)->get('/organisation/accounting')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/accounting')->where('board.connection.mode', 'simulator'));
});
