<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\Avatars\AvatarConsents;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\AvatarConsent;
use App\Models\Course;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\AvatarConsentEnded;
use App\Privacy\ConsentPurpose;
use App\Services\Authorization\Memberships;
use App\Services\Content\LessonFiles;
use App\Services\Privacy\Consents;
use App\Services\Privacy\DataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Dr. Rachel Hughes']);
    $this->course = Course::query()->create(['organisation_id' => $this->school->id, 'title' => 'Principles of Real Estate II', 'slug' => 'principles-ii', 'type' => 'qualifying', 'states' => ['TX'], 'hours' => 30]);
    $this->course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451', 'status' => 'approved']);
});

function giveAvatar(object $test): void
{
    $test->actingAs($test->rachel)->post('/settings/avatar', [
        'agree' => '1',
        'sample' => UploadedFile::fake()->create('sample.mp4', 40_000, 'video/mp4'),
    ])->assertSessionHasNoErrors()->assertRedirect();
}

describe('instructors (screen 52)', function () {
    it('lists instructors with their courses, approvals and avatar consent, and invitations', function () {
        $this->actingAs($this->paul)->put("/school/instructors/{$this->rachel->id}/courses", ['courses' => [$this->course->id]])->assertRedirect();
        giveAvatar($this);

        $this->actingAs($this->paul)->get('/school/instructors')->assertInertia(fn (Assert $page) => $page
            ->component('school/instructors')
            ->where('instructors.0.name', 'Dr. Rachel Hughes')
            ->where('instructors.0.courses.0.title', 'Principles of Real Estate II')
            ->where('instructors.0.approvals', 1)
            ->where('instructors.0.avatar.status', 'given')
            ->where('invite.roles.0.value', 'instructor'));

        expect(AuditEvent::query()->where('action', 'instructor.courses_changed')->sole()->organisation_id)->toBe($this->school->id);
    });

    it('assigns only the school\'s own courses, to its own instructors', function () {
        $other = Course::query()->create(['title' => 'REOS course', 'slug' => 'reos', 'type' => 'elective']);
        $this->actingAs($this->paul)->put("/school/instructors/{$this->rachel->id}/courses", ['courses' => [$this->course->id, $other->id]])->assertRedirect();
        expect($this->course->instructors()->pluck('users.id')->all())->toBe([$this->rachel->id])
            ->and($other->instructors()->count())->toBe(0);

        $stranger = User::factory()->withRole(Role::Instructor)->create();
        $this->put("/school/instructors/{$stranger->id}/courses", ['courses' => [$this->course->id]])->assertSessionHasErrors('instructor');

        $this->actingAs($this->rachel)->get('/school/instructors')->assertForbidden();
    });

    it('revokes an instructor, ends their avatar, and Undo brings both back', function () {
        Notification::fake();
        giveAvatar($this);

        $this->actingAs($this->paul)->delete("/school/instructors/{$this->rachel->id}")->assertRedirect();
        expect(app(Memberships::class)->find($this->rachel, Role::Instructor, $this->school->id))->toBeNull()
            ->and(AvatarConsent::query()->sole()->status)->toBe(AvatarConsent::ENDED);
        Notification::assertSentTo($this->paul, AvatarConsentEnded::class);

        $this->post("/school/instructors/{$this->rachel->id}/restore")->assertRedirect();
        expect(app(Memberships::class)->find($this->rachel, Role::Instructor, $this->school->id))->not->toBeNull()
            ->and(AvatarConsent::query()->sole()->status)->toBe(AvatarConsent::GIVEN);
    });
});

describe('avatar consent', function () {
    it('records the agreement with its version and keeps the sample for the school', function () {
        giveAvatar($this);

        $consent = AvatarConsent::query()->sole();
        expect($consent->status)->toBe(AvatarConsent::GIVEN)
            ->and($consent->version)->toBe(ConsentPurpose::AvatarLikeness->version())
            ->and($consent->sample_path)->toStartWith("tenants/{$this->school->id}/avatars/")
            ->and(LessonFiles::disk()->exists((string) $consent->sample_path))->toBeTrue()
            ->and(app(Consents::class)->allows($this->rachel, ConsentPurpose::AvatarLikeness, "school:{$this->school->id}"))->toBeTrue()
            ->and(app(AvatarConsents::class)->usable($this->rachel, $this->school))->toBeTrue();

        $url = $this->actingAs($this->rachel)->get('/settings/avatar')->assertInertia(fn (Assert $page) => $page
            ->component('settings/avatar')->where('consent.status', 'given')->where('consent.current', true))->viewData('page')['props']['consent']['sample_url'];
        $this->get($url)->assertOk();
    });

    it('needs agreement and a video, and only instructors at a school can give it', function () {
        $this->actingAs($this->rachel)->post('/settings/avatar', ['sample' => UploadedFile::fake()->create('s.mp4', 100, 'video/mp4')])->assertSessionHasErrors('agree');
        $this->post('/settings/avatar', ['agree' => '1', 'sample' => UploadedFile::fake()->create('s.pdf', 100, 'application/pdf')])->assertSessionHasErrors('sample');

        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->get('/settings/avatar')->assertNotFound();
        expect(AvatarConsent::query()->count())->toBe(0);
    });

    it('withdrawn, deletes the sample at once and gives the school 30 days', function () {
        Notification::fake();
        giveAvatar($this);
        $path = (string) AvatarConsent::query()->sole()->sample_path;

        $this->actingAs($this->rachel)->delete('/settings/avatar')->assertRedirect();
        $consent = AvatarConsent::query()->sole();

        expect($consent->status)->toBe(AvatarConsent::WITHDRAWN)
            ->and($consent->sample_path)->toBeNull()
            ->and(LessonFiles::disk()->exists($path))->toBeFalse()
            ->and($consent->lessons_due_by?->toDateString())->toBe(now()->addDays(30)->toDateString())
            ->and(app(AvatarConsents::class)->usable($this->rachel, $this->school))->toBeFalse()
            ->and(AuditEvent::query()->where('action', 'avatar.consent_withdrawn')->count())->toBe(1);
        Notification::assertSentTo($this->paul, AvatarConsentEnded::class, fn (AvatarConsentEnded $n) => $n->consent->is($consent));
    });

    it('can be withdrawn from the privacy history too, and is in Download my data', function () {
        Notification::fake();
        giveAvatar($this);

        $this->actingAs($this->rachel)->put('/settings/privacy/consents/avatar_likeness', ['granted' => false, 'subject' => "school:{$this->school->id}"])->assertRedirect();
        expect(AvatarConsent::query()->sole()->status)->toBe(AvatarConsent::WITHDRAWN);

        $export = app(DataExport::class)->for($this->rachel);
        expect($export['avatar_consents'][0]['school'])->toBe('Texas Realty School')
            ->and($export['avatar_consents'][0]['status'])->toBe('withdrawn');
    });

    it('deletes the sample of a revoked instructor after a day', function () {
        giveAvatar($this);
        app(AvatarConsents::class)->endFor($this->rachel, $this->school, $this->paul);
        $path = (string) AvatarConsent::query()->sole()->sample_path;

        expect(app(AvatarConsents::class)->prune())->toBe(0);
        $this->travel(2)->days();
        expect(app(AvatarConsents::class)->prune())->toBe(1)
            ->and(LessonFiles::disk()->exists($path))->toBeFalse();
    });
});
