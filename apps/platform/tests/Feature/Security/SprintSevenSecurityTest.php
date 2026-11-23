<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Content\Builder\SourceRole;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\IntegrationSetting;
use App\Models\Lesson;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sprint 7's security definition of done, end to end (S07 spec section 4),
 * for what the sub-sprints' own tests don't already cover.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config()->string('reos.content.disk'));
});

/** @return array{0: Organisation, 1: User, 2: Course, 3: CourseModule} */
function sevenSchool(string $name, string $text): array
{
    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => $name]);
    $admin = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $school)->create();
    $course = Course::query()->create(['organisation_id' => $school->id, 'title' => $name.' course', 'slug' => str($name)->slug().'-course', 'type' => 'ce', 'states' => ['TX']]);
    $module = CourseModule::query()->create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
    $module->sources()->create([
        'role' => SourceRole::Script, 'kind' => MediaKind::Document, 'path' => "tenants/{$school->id}/courses/{$course->id}/sources/s.pdf", 'original_name' => $name.' script.pdf',
        'mime' => 'application/pdf', 'size' => 10, 'status' => MediaStatus::Ready, 'scanned_at' => now(), 'position' => 1, 'words' => str_word_count($text),
        'sections' => [['page' => 1, 'text' => $text]],
    ]);
    $ledger = app(Ledger::class);
    $ledger->post('pool_funded', [[$ledger->platform('credits_sold'), -500], [$ledger->organisation($school, 'credits'), 500]], 'organisation', (string) $school->id);

    return [$school, $admin, $course, $module];
}

it('keeps a school\'s course builder, AI drafts and sources to itself', function () {
    [, $paul, $course, $module] = sevenSchool('Texas Realty School', 'Texas license holders disclose who they represent at the first substantive contact with a party.');
    [, $other, $otherCourse, $otherModule] = sevenSchool('Gulf Coast Academy', 'Gulf Coast Academy private material about commission splits and team bonuses for agents.');

    $this->actingAs($paul)->post("/course-builder/modules/{$module->id}/generate")->assertSessionHasNoErrors();
    $lesson = Lesson::query()->where('course_id', $course->id)->sole();

    // The other school can't open, generate or regenerate any of it.
    $this->actingAs($other)->get("/course-builder/{$course->id}")->assertNotFound();
    $this->actingAs($other)->getJson("/course-builder/lessons/{$lesson->id}/draft")->assertNotFound();
    $this->actingAs($other)->post("/course-builder/modules/{$module->id}/generate")->assertNotFound();
    $this->actingAs($other)->post("/course-builder/lessons/{$lesson->id}/regenerate")->assertNotFound();

    // The draft was written only from this module's own sources.
    $sources = LessonVersion::query()->where('lesson_id', $lesson->id)->sole()->sources;
    expect(collect($sources)->pluck('source')->unique()->all())->toBe(['Texas Realty School script.pdf'])
        ->and(json_encode(LessonVersion::query()->where('lesson_id', $lesson->id)->sole()->lines))->not->toContain('commission splits');
});

it('stores a CRM key in the vault and never shows it again', function () {
    IntegrationSetting::query()->create(['integration_id' => 'follow-up-boss', 'mode' => IntegrationMode::Simulator]);
    $james = User::factory()->withRole(Role::Agent)->create();

    $this->actingAs($james)->postJson('/api/v1/connections', ['integration' => 'follow-up-boss', 'api_key' => 'fka_secret_key_123'])->assertOk();
    $listed = $this->getJson('/api/v1/connections')->assertOk()->getContent();

    expect($listed)->not->toContain('fka_secret_key_123')
        ->and(DB::table('connections')->value('access_token'))->not->toContain('fka_secret_key_123');
});
