<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Licences\Regulators\RegulatorImports;
use App\Models\AuditEvent;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\ProviderLicence;
use App\Models\RegulatorImport;
use App\Models\RegulatorRecord;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-24 05:30:00');
});

function regulatorLive(string $integration): void
{
    IntegrationSetting::query()->updateOrCreate(['integration_id' => $integration], ['mode' => IntegrationMode::Live]);
}

/** TREC's file as the Open Data Portal exports it: a header row, statuses as codes or words. */
function trecCsv(int $extra = 0): string
{
    $rows = [
        'License Type,License Number,Full Name,Suffix,License Status,Original License Date,License Expiration Date,Education Status,MCE Status,Designated Supervisor Flag,Related License Type,Related License Number,Related License Full Name',
        'SALE,0798765,James Miller,,20,04/01/2023,03/31/2027,2,0,0,BRK,9012345,Linda Park',
        'Individual Broker,9012345,Linda Park,,Current and Active,03/05/2008,03/31/2027,2,2,1,,,',
        'SALE,0797777,Sam Ortiz,,21,09/01/2026,08/31/2028,0,0,0,,,',
    ];

    for ($i = 0; $i < $extra; $i++) {
        $rows[] = sprintf('SALE,07%05d,Agent %d,,20,01/01/2020,01/31/2027,2,0,0,,,', $i, $i);
    }

    return implode("\n", $rows)."\n";
}

describe("regulators' public data (B10.3, decision 8)", function () {
    it('is imported nightly before the morning checks', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'reos:regulators:import'));

        expect($event?->expression)->toBe('30 5 * * *')->and($event?->timezone)->toBe('America/Chicago');
    });

    it("reads TREC's own tab layout and the portal's CSVs on the Simulator", function () {
        $this->artisan('reos:regulators:import')->assertSuccessful();

        $james = RegulatorRecord::query()->where('authority', 'TREC')->where('number', '0798765')->sole();
        expect($james)->name->toBe('James Miller')->licence_type->toBe('SALE')->active->toBeTrue()->sponsor_name->toBe('Linda Park')
            ->sponsor_number->toBe('9012345')->education_status->toBe(2)->number_digits->toBe('798765')
            ->and($james->licensed_on?->toDateString())->toBe('2023-04-01')->and($james->expires_on?->toDateString())->toBe('2027-03-31')
            ->and(RegulatorRecord::query()->where('number', '9012345')->sole()->designated_supervisor)->toBeTrue()
            ->and(RegulatorRecord::query()->where('number', '0797777')->sole()->active)->toBeFalse()
            ->and(RegulatorRecord::query()->where('kind', 'inspectors')->pluck('licence_type')->sort()->values()->all())->toBe(['APIN', 'PRIN'])
            ->and(RegulatorRecord::query()->where('authority', 'TDI')->where('number', '1234567')->sole())
            ->name->toBe('Ava Thompson')->npn->toBe('17654321')->qualification->toBe('Property; Casualty')->active->toBeTrue()
            ->and(RegulatorRecord::query()->where('authority', 'TDI')->where('number', '3456789')->sole()->active)->toBeFalse()
            ->and(RegulatorImport::query()->where('integration', 'trec-licence')->sole())->rows->toBe(8)->source->toBe('simulator')->error->toBeNull();
    });

    it('downloads the live files, replaces the last import, and keeps it when a file comes back short', function () {
        regulatorLive('trec-licence');
        Http::fake([
            'data.texas.gov/api/views/s7ft-44qi/*' => Http::response(trecCsv(10)),
            'data.texas.gov/api/views/pkr9-9isv/*' => Http::response("License Type,License Number,Full Name,License Status\nPRIN,0024567,Pat Rivera,20\n"),
        ]);
        expect(app(RegulatorImports::class)->run(app(Catalogue::class)->find('trec-licence')))->rows->toBe(14)->source->toBe('live')
            ->and(RegulatorRecord::query()->where('number', '9012345')->sole())->licence_type->toBe('BRK')->active->toBeTrue();

        // A truncated download: yesterday's records stay.
        Http::swap(new Factory);
        Http::fake(['data.texas.gov/api/views/s7ft-44qi/*' => Http::response(trecCsv()), 'data.texas.gov/api/views/pkr9-9isv/*' => Http::response("License Type,License Number,Full Name,License Status\n")]);
        $this->travel(1)->day();
        $short = app(RegulatorImports::class)->run(app(Catalogue::class)->find('trec-licence'));
        expect($short->error)->toContain('keeping the last import')->and(RegulatorRecord::query()->where('authority', 'TREC')->count())->toBe(14);

        // A normal next import drops licences that left the file.
        Http::swap(new Factory);
        Http::fake(['data.texas.gov/api/views/s7ft-44qi/*' => Http::response(trecCsv(9)), 'data.texas.gov/api/views/pkr9-9isv/*' => Http::response("License Type,License Number,Full Name,License Status\nPRIN,0024567,Pat Rivera,20\n")]);
        $this->travel(1)->day();
        $next = app(RegulatorImports::class)->run(app(Catalogue::class)->find('trec-licence'));
        expect($next->error)->toBeNull()->and($next->removed)->toBe(1)
            ->and(RegulatorRecord::query()->where('authority', 'TREC')->count())->toBe(13);
    });

    it('verifies a licence number in seconds from TREC\'s data once live', function () {
        $this->artisan('reos:regulators:import')->assertSuccessful();
        regulatorLive('trec-licence');
        $james = User::factory()->create(['name' => 'James Miller']);

        $started = microtime(true);
        $response = $this->actingAs($james)->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '798765'])->assertCreated();

        expect(microtime(true) - $started)->toBeLessThan(2.0)
            ->and($response->json('check'))->toBe('verified')
            ->and($response->json('source'))->toBe("Checked with the Texas Real Estate Commission's public license data.")
            ->and($response->json('data'))->toMatchArray(['type' => 'sales_agent', 'status' => 'active', 'sponsor_name' => 'Linda Park', 'verified_by' => 'trec-licence', 'expires_on' => '2027-03-31']);

        // Not sponsored yet: TREC's status 21 reads as inactive.
        $sam = User::factory()->create(['name' => 'Sam Ortiz']);
        $this->actingAs($sam)->postJson('/api/v1/me/licences', ['state' => 'TX', 'number' => '0797777'])->assertCreated()->assertJsonPath('data.status', 'inactive');
    });
});

describe("providers' professional licences (B10.3)", function () {
    beforeEach(function () {
        $this->artisan('reos:regulators:import')->assertSuccessful();
        $this->provider = fn (string $name): User => User::factory()->withRole(Role::Provider, Organisation::platform())->create(['name' => $name]);
    });

    it('verifies TREC inspectors and TDI licensees in their name, and sends the rest to a reviewer', function () {
        $pat = ($this->provider)('Pat Rivera');
        $this->actingAs($pat)->postJson('/api/v1/provider/licences', ['kind' => 'trec_inspector', 'number' => '24567'])->assertOk()
            ->assertJsonPath('data.status', 'verified')->assertJsonPath('data.checked_via', 'trec-licence')->assertJsonPath('data.licence_type', 'PRIN');

        $ava = ($this->provider)('Ava Thompson');
        // By NPN as well as the licence number.
        $this->actingAs($ava)->postJson('/api/v1/provider/licences', ['kind' => 'tdi', 'number' => '17654321'])->assertOk()
            ->assertJsonPath('data.status', 'verified')->assertJsonPath('data.name_on_record', 'Ava Thompson')->assertJsonPath('data.expires_on', '2027-03-31');

        $lena = ($this->provider)('Lena Brooks');
        $this->actingAs($lena)->postJson('/api/v1/provider/licences', ['kind' => 'tdi', 'number' => '3456789'])->assertJsonPath('data.status', 'inactive');

        $other = ($this->provider)('Someone Else');
        $this->actingAs($other)->postJson('/api/v1/provider/licences', ['kind' => 'tdi', 'number' => '1234567'])
            ->assertJsonPath('data.status', 'review')->assertJsonPath('data.review_note', 'The license is under another name. A reviewer will check it.');
        $this->postJson('/api/v1/provider/licences', ['kind' => 'trec_inspector', 'number' => '0099999'])
            ->assertJsonPath('data.review_note', 'No TREC record with this number yet. A reviewer will check it.');
    });

    it('sends NMLS IDs to a reviewer with the Consumer Access link, who approves or rejects (audited)', function () {
        $max = ($this->provider)('Max Chen');
        $this->actingAs($max)->postJson('/api/v1/provider/licences', ['kind' => 'nmls', 'number' => '123456'])->assertOk()
            ->assertJsonPath('data.status', 'review')->assertJsonPath('data.consumer_url', 'https://www.nmlsconsumeraccess.org/EntityDetails.aspx/INDIVIDUAL/123456');

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])->get('/admin/people')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('providerLicenceReviews.0.kind', 'nmls')->where('providerLicenceReviews.0.consumer_url', 'https://www.nmlsconsumeraccess.org/EntityDetails.aspx/INDIVIDUAL/123456'));

        $licence = ProviderLicence::query()->sole();
        $this->post("/admin/people/provider-licences/{$licence->id}/approve")->assertRedirect();
        expect($licence->refresh())->status->toBe('verified')->checked_via->toBe('reviewer')->reviewed_by->toBe($admin->id)
            ->and(AuditEvent::query()->where('action', 'provider_licence.approved')->exists())->toBeTrue();
        $this->post("/admin/people/provider-licences/{$licence->id}/approve")->assertStatus(409);

        $this->actingAs($max)->postJson('/api/v1/provider/licences', ['kind' => 'nmls', 'number' => '654321'])->assertJsonPath('data.status', 'review');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])->post("/admin/people/provider-licences/{$licence->id}/reject", ['note' => 'Not found on NMLS Consumer Access.'])->assertRedirect();
        expect($licence->refresh())->status->toBe('rejected')->review_note->toBe('Not found on NMLS Consumer Access.');
    });

    it('is for providers only, and shows on their profile settings', function () {
        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->postJson('/api/v1/provider/licences', ['kind' => 'nmls', 'number' => '123456'])->assertNotFound();
        $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('providerLicences', null));

        $pat = ($this->provider)('Pat Rivera');
        $this->actingAs($pat)->post('/settings/provider-licences', ['kind' => 'trec_inspector', 'number' => '0024567'], ['Accept' => 'application/json'])->assertOk();
        $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('providerLicences.0.status', 'verified'));
        $this->postJson('/api/v1/provider/licences', ['kind' => 'dea', 'number' => '1'])->assertUnprocessable()->assertJsonValidationErrors(['kind', 'number']);
    });
});
