<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\CeCompletion;
use App\Models\CeReport;
use App\Models\CeReportLine;
use App\Models\Course;
use App\Models\IntegrationSetting;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-24 15:00:00');
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->course = Course::query()->create(['organisation_id' => $this->school->id, 'title' => 'Legal Update I', 'slug' => 'legal-update-1', 'type' => 'ce', 'states' => ['TX'], 'hours' => 4]);
    $this->course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => '40123', 'status' => 'approved']);
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    Licence::query()->create(['user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active, 'expires_on' => '2027-01-15']);
    $this->dana = User::factory()->withRole(Role::Agent)->create(['name' => 'Dana Brooks']);
});

function schoolCompletion(User $user, Course $course, string $on): CeCompletion
{
    return CeCompletion::query()->forceCreate(['user_id' => $user->id, 'state_code' => 'TX', 'title' => $course->title, 'hours' => 4, 'completed_on' => $on, 'source' => 'reos', 'course_id' => $course->id, 'provider' => 'Texas Realty School']);
}

describe('CE reporting to TREC (A11.3)', function () {
    it('lists Ready completions with what would stop one going', function () {
        schoolCompletion($this->james, $this->course, '2026-11-10');
        schoolCompletion($this->dana, $this->course, '2026-11-11');
        $other = Course::query()->create(['organisation_id' => Organisation::factory()->ofType(OrganisationType::School)->create()->id, 'title' => 'Elsewhere', 'slug' => 'elsewhere', 'type' => 'ce', 'states' => ['TX'], 'hours' => 3]);
        schoolCompletion($this->james, $other, '2026-11-12');

        $this->actingAs($this->paul)->withSession(['auth.password_confirmed_at' => now()->timestamp])->get('/school/ce-reports')->assertInertia(fn (Assert $page) => $page->component('school/ce-reports')
            ->has('reporting.ready', 2)->where('reporting.ready.0.student', 'James Miller')->where('reporting.ready.0.course_number', '40123')->where('reporting.ready.0.problem', null)
            ->where('reporting.ready.1.problem', 'no_licence')->where('reporting.reos', 'simulated'));
    });

    it('builds TREC\'s file, then moves the completion to Reported with TREC\'s reference', function () {
        $done = schoolCompletion($this->james, $this->course, '2026-11-10');
        schoolCompletion($this->dana, $this->course, '2026-11-11');
        $this->actingAs($this->paul)->withSession(['auth.password_confirmed_at' => now()->timestamp]);

        $this->postJson('/school/ce-reports', ['from_on' => '2026-12-01', 'to_on' => '2026-11-24', 'method' => 'file'])->assertUnprocessable()->assertJsonValidationErrors('to_on');
        $this->postJson('/school/ce-reports', ['from_on' => '2026-10-01', 'to_on' => '2026-10-31', 'method' => 'file'])->assertUnprocessable()->assertJsonValidationErrors('from_on');
        $id = $this->postJson('/school/ce-reports', ['from_on' => '2026-11-01', 'to_on' => '2026-11-24', 'method' => 'file'])->assertCreated()
            ->assertJsonPath('data.status', 'pending_upload')->assertJsonPath('data.overview.ready.0.problem', 'no_licence')->assertJsonCount(1, 'data.overview.reports')->json('data.id');

        $csv = $this->get("/school/ce-reports/{$id}/file")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        expect(explode("\n", trim((string) $csv)))->toBe([
            '"License Type","License Number","Course Number","Delivery Method","Start Date","Completion Date"',
            'SALE,0798765,40123,"Distance Learning",11102026,11102026',
        ]);

        $this->putJson("/school/ce-reports/{$id}/reference", ['reference' => 'CE-2026-118844'])->assertOk()->assertJsonPath('data.reports.0.status', 'reported')->assertJsonPath('data.reports.0.reference', 'CE-2026-118844');
        expect(CeReportLine::query()->where('ce_completion_id', $done->id)->sole()->status)->toBe('reported')
            ->and(AuditEvent::query()->whereIn('action', ['ce_report.created', 'ce_report.reported'])->count())->toBe(2);
    });

    it('lets REOS submit with the school\'s authority, and returns a rejected completion to Ready', function () {
        $done = schoolCompletion($this->james, $this->course, '2026-11-10');
        $this->actingAs($this->paul)->withSession(['auth.password_confirmed_at' => now()->timestamp]);

        $this->postJson('/school/ce-reports', ['from_on' => '2026-11-01', 'to_on' => '2026-11-24', 'method' => 'reos'])->assertUnprocessable()->assertJsonValidationErrors('authorised');
        $report = $this->postJson('/school/ce-reports', ['from_on' => '2026-11-01', 'to_on' => '2026-11-24', 'method' => 'reos', 'authorised' => true])->assertCreated()
            ->assertJsonPath('data.status', 'reported')->json('data');
        expect($report['reference'])->toStartWith('SIM-');

        $this->putJson("/school/ce-reports/{$report['id']}/lines/{$done->id}", ['rejected' => true])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->putJson("/school/ce-reports/{$report['id']}/lines/{$done->id}", ['rejected' => true, 'reason' => 'License number not found'])->assertOk()
            ->assertJsonPath('data.rejected.0.rejected', 'License number not found')->assertJsonPath('data.reports.0.lines.0.status', 'rejected');
        $this->putJson("/school/ce-reports/{$report['id']}/lines/{$done->id}", ['rejected' => false])->assertOk()->assertJsonPath('data.reports.0.lines.0.status', 'reported')->assertJsonPath('data.rejected', []);

        IntegrationSetting::query()->updateOrCreate(['integration_id' => 'trec-ce-reporting'], ['mode' => IntegrationMode::Off]);
        $this->putJson("/school/ce-reports/{$report['id']}/lines/{$done->id}", ['rejected' => true, 'reason' => 'License number not found'])->assertOk();
        $this->postJson('/school/ce-reports', ['from_on' => '2026-11-01', 'to_on' => '2026-11-24', 'method' => 'reos', 'authorised' => true])->assertUnprocessable()->assertJsonValidationErrors('method');
    });

    it('shows the agent each step to TREC, and the certificate once reported (screens 103, 104)', function () {
        $done = schoolCompletion($this->james, $this->course, '2026-11-10');
        $steps = fn () => $this->actingAs($this->james)->getJson("/api/v1/me/ce/completions/{$done->id}")->assertOk()->json('data');

        expect($steps())->toMatchArray(['issued' => false, 'course_number' => '40123', 'licence' => '0798765'])
            ->and($steps()['reporting']['status'])->toBe('waiting')
            ->and(array_column($steps()['reporting']['steps'], 'state'))->toBe(['done', 'done', 'waiting', 'not_connected', 'waiting']);

        $id = $this->actingAs($this->paul)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson('/school/ce-reports', ['from_on' => '2026-11-01', 'to_on' => '2026-11-24', 'method' => 'file'])->json('data.id');
        expect($steps()['reporting']['status'])->toBe('pending_upload');

        $this->actingAs($this->paul)->putJson("/school/ce-reports/{$id}/reference", ['reference' => 'CE-2026-118844'])->assertOk();
        expect($steps())->toMatchArray(['issued' => true])
            ->and($steps()['reporting'])->toMatchArray(['status' => 'reported', 'reference' => 'CE-2026-118844'])
            ->and($this->getJson('/api/v1/me/ce')->json('data.0.completions.0.reporting.status'))->toBe('reported');

        $this->get("/ce/completions/{$done->id}/certificate")->assertInertia(fn (Assert $page) => $page->component('ce/certificate')->where('certificate.issued', true));
        $this->actingAs($this->dana)->getJson("/api/v1/me/ce/completions/{$done->id}")->assertNotFound();
    });

    it('keeps each school to its own reports', function () {
        schoolCompletion($this->james, $this->course, '2026-11-10');
        $this->actingAs($this->paul)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $id = $this->postJson('/school/ce-reports', ['from_on' => '2026-11-01', 'to_on' => '2026-11-24', 'method' => 'file'])->json('data.id');

        $other = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, Organisation::factory()->ofType(OrganisationType::School)->create())->create();
        $this->actingAs($other)->get("/school/ce-reports/{$id}/file")->assertNotFound();
        $this->actingAs($this->james)->get('/school/ce-reports')->assertForbidden();
        expect(CeReport::query()->count())->toBe(1);
    });
});
