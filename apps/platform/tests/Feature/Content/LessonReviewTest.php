<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Content\Builder\LessonReview;
use App\Content\Builder\SourceRole;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\QuestionDispute;
use App\Models\QuizQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use App\Notifications\LessonSentBack;
use App\Notifications\QuestionDisputeResolved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config()->string('reos.content.disk'));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Dr. Rachel Hughes']);
    $this->course = Course::query()->create(['organisation_id' => $this->school->id, 'title' => 'Principles of Real Estate II', 'slug' => 'principles-ii', 'type' => 'qualifying', 'states' => ['TX'], 'hours' => 30]);
    $this->course->instructors()->attach($this->rachel->id);
    $this->module = CourseModule::query()->create(['course_id' => $this->course->id, 'title' => 'Agency in Texas', 'position' => 1]);
    $ledger = app(Ledger::class);
    $ledger->post('pool_funded', [[$ledger->platform('credits_sold'), -1000], [$ledger->organisation($this->school, 'credits'), 1000]], 'organisation', (string) $this->school->id);
});

/** Generates one AI lesson (simulators) from a script with the given text, and finishes its videos. */
function rvLesson(object $test, string $text): Lesson
{
    $test->module->sources()->create([
        'role' => SourceRole::Script, 'kind' => MediaKind::Document, 'path' => 'tenants/'.$test->school->id.'/s.pdf', 'original_name' => 'Agency script.pdf',
        'mime' => 'application/pdf', 'size' => 10, 'status' => MediaStatus::Ready, 'scanned_at' => now(), 'position' => 1, 'words' => str_word_count($text),
        'sections' => [['page' => 2, 'text' => $text]],
    ]);
    $test->actingAs($test->rachel)->post("/course-builder/modules/{$test->module->id}/generate")->assertSessionHasNoErrors();
    $test->artisan('reos:lessons:renders');

    return Lesson::query()->where('course_module_id', $test->module->id)->sole();
}

function rvApproveAll(object $test, Lesson $lesson): void
{
    $version = LessonVersion::query()->where('lesson_id', $lesson->id)->latest('version')->firstOrFail();
    foreach (array_keys($version->lines ?? []) as $n) {
        $test->post("/course-builder/lessons/{$lesson->id}/review/lines/{$n}", ['action' => 'approve'])->assertSessionHasNoErrors();
    }
    foreach (ReviewItem::query()->where('lesson_version_id', $version->id)->whereIn('kind', ['checkin', 'flashcard'])->get() as $item) {
        $test->post("/course-builder/lessons/{$lesson->id}/review/items/{$item->id}", ['decision' => 'approve'])->assertSessionHasNoErrors();
    }
}

describe('AI lesson review, screen 44 (the A8.1 done-when)', function () {
    it('publishes nothing until every line, check-in and flashcard is approved', function () {
        $lesson = rvLesson($this, 'Texas license holders disclose who they represent at the first contact. They give the IABS form at that contact. The form lists the types of representation a broker offers.');

        $this->actingAs($this->rachel)->get("/course-builder/lessons/{$lesson->id}/review")
            ->assertInertia(fn (Assert $page) => $page->component('builder/review')
                ->has('lines', 3)->where('lines.0.status', 'to_review')->where('lines.0.source', 'Agency script.pdf')->where('lines.0.page', 2)
                ->has('videos', 2)->where('version.review_state', 'in_review')
                ->where('blockers', fn ($b) => count($b) === 2));

        $this->post("/course-builder/lessons/{$lesson->id}/review/publish")->assertSessionHasErrors('lesson');
        expect($lesson->refresh()->review_status)->toBe(LessonReview::InReview);

        rvApproveAll($this, $lesson);
        $this->post("/course-builder/lessons/{$lesson->id}/review/publish")->assertRedirect("/course-builder/{$this->course->id}");

        expect($lesson->refresh()->review_status)->toBe(LessonReview::Approved)
            ->and(LessonVersion::query()->sole()->review_state)->toBe('approved')
            ->and(AuditEvent::query()->where('action', 'lesson.published')->where('actor_id', $this->rachel->id)->exists())->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'lesson.line_approved')->count())->toBe(3);

        // A published version can't be changed.
        $this->post("/course-builder/lessons/{$lesson->id}/review/lines/0", ['action' => 'edit', 'en' => 'Changed.', 'es' => 'Cambiado.'])->assertSessionHasErrors('lesson');
    });

    it('blocks a flagged line, suggests a compliant rewrite, and needs it recorded again', function () {
        $lesson = rvLesson($this, 'Every student is guaranteed to pass the Texas exam with this course. Agents disclose who they represent at the first contact.');
        $version = LessonVersion::query()->sole();
        expect($version->status)->toBe(LessonVersion::FLAGGED);

        $this->actingAs($this->rachel)->post("/course-builder/lessons/{$lesson->id}/review/lines/0", ['action' => 'approve'])->assertSessionHasErrors('line');
        $this->get("/course-builder/lessons/{$lesson->id}/review")->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.flags.0.rule', 'guarantee')->where('lines.0.flags.0.source', '22 TAC §535.155 (advertising)'));

        $this->post("/course-builder/lessons/{$lesson->id}/review/lines/0/suggest")->assertSessionHasNoErrors();
        $suggestion = LessonVersion::query()->sole()->review(0)['suggestion'];
        expect($suggestion['en'])->toContain('help you prepare for')->not->toContain('guaranteed');

        $this->post("/course-builder/lessons/{$lesson->id}/review/lines/0", ['action' => 'use_suggestion'])->assertSessionHasNoErrors();
        $version->refresh();
        expect($version->flags)->toBe([])
            ->and($version->review(0)['rerecord'])->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'lesson.suggestion_used')->exists())->toBeTrue();

        // Recorded again: a new version, the earlier one kept, with the reviewed script.
        $this->post("/course-builder/lessons/{$lesson->id}/review/render")->assertSessionHasNoErrors();
        $this->artisan('reos:lessons:renders');
        $latest = LessonVersion::query()->where('lesson_id', $lesson->id)->orderByDesc('version')->first();
        expect(LessonVersion::query()->count())->toBe(2)
            ->and($latest->status)->toBe(LessonVersion::READY)
            ->and($latest->lines[0]['en'])->toContain('help you prepare for')
            ->and($latest->review(0)['rerecord'])->toBeFalse();

        rvApproveAll($this, $lesson);
        $this->post("/course-builder/lessons/{$lesson->id}/review/publish")->assertSessionHasNoErrors();
        expect($lesson->refresh()->review_status)->toBe(LessonReview::Approved);
    });

    it('re-checks an edited line, keeps comments, and sends back to the author with a note', function () {
        Notification::fake();
        $lesson = rvLesson($this, 'Agents disclose who they represent at the first contact. The IABS form explains it.');

        $this->actingAs($this->paul)->post("/course-builder/lessons/{$lesson->id}/review/lines/1", ['action' => 'edit', 'en' => 'We are the best school in Texas.', 'es' => 'Somos la mejor escuela.'])->assertSessionHasNoErrors();
        expect(collect(LessonVersion::query()->sole()->flags)->pluck('rule')->unique()->all())->toBe(['superlative']);

        $this->post("/course-builder/lessons/{$lesson->id}/review/lines/0/comments", ['body' => 'Cite the TREC rule here.'])->assertSessionHasNoErrors();
        expect(LessonVersion::query()->sole()->review(0)['comments'][0])->toMatchArray(['name' => 'Paul Diaz', 'body' => 'Cite the TREC rule here.']);

        $this->post("/course-builder/lessons/{$lesson->id}/review/send-back", ['note' => 'Please rework line 2 without the superlative.'])->assertSessionHasNoErrors();
        expect($lesson->refresh()->review_status)->toBe(LessonReview::Draft)
            ->and(LessonVersion::query()->sole()->review_state)->toBe('sent_back');
        Notification::assertSentTo($this->rachel, LessonSentBack::class);
    });

    it('lets only the school\'s admins and the course\'s instructors review', function () {
        $lesson = rvLesson($this, 'Agents disclose who they represent at the first contact. The IABS form explains it.');
        $marcus = User::factory()->withRole(Role::Instructor, $this->school)->create();
        $other = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, Organisation::factory()->ofType(OrganisationType::School)->create())->create();

        $this->actingAs($marcus)->get("/course-builder/lessons/{$lesson->id}/review")->assertNotFound();
        $this->actingAs($other)->post("/course-builder/lessons/{$lesson->id}/review/publish")->assertNotFound();
        $this->actingAs($this->rachel)->get('/ai-lessons')->assertInertia(fn (Assert $page) => $page->component('builder/ai-lessons')->has('lessons', 1)->where('lessons.0.status', 'in_review'));
        $this->actingAs($marcus)->get('/ai-lessons')->assertInertia(fn (Assert $page) => $page->has('lessons', 0));
    });
});

describe('question bank, screen 45', function () {
    it('approves a question with its source and outline into the school\'s bank, and edits it', function () {
        rvLesson($this, 'Agents disclose who they represent at the first contact. The IABS form explains it. Brokers supervise sales agents.');
        $item = ReviewItem::query()->where('kind', 'question')->firstOrFail();

        $this->actingAs($this->rachel)->get('/question-bank')->assertInertia(fn (Assert $page) => $page->component('builder/questions')
            ->where('tab', 'to_review')->where('counts.to_review', ReviewItem::query()->where('kind', 'question')->count())
            ->where('questions.0.source', 'Agency script.pdf, p. 2')->where('questions.0.portion', 'state'));

        $this->post("/question-bank/{$item->id}/decision", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $question = QuizQuestion::query()->where('review_item_id', $item->id)->sole();
        expect($question->organisation_id)->toBe($this->school->id)
            ->and($question->course_id)->toBe($this->course->id)
            ->and($question->source)->toBe('Agency script.pdf, p. 2')
            ->and($question->portion)->toBe('state')
            ->and($question->in('es')['question'])->toStartWith('[ES]');

        $edit = ['en' => ['prompt' => 'When is the IABS form given?', 'options' => ['At first contact', 'At closing'], 'explanation' => 'At the first substantive contact.'],
            'es' => ['prompt' => '¿Cuándo se entrega el formulario IABS?', 'options' => ['En el primer contacto', 'Al cierre'], 'explanation' => 'En el primer contacto sustancial.'],
            'correct' => 0, 'topic' => 'agency', 'difficulty' => 'easy', 'portion' => 'state'];
        $this->put("/question-bank/{$item->id}", $edit)->assertSessionHasNoErrors();
        expect($question->refresh()->in('en')['question'])->toBe('When is the IABS form given?')
            ->and($question->difficulty)->toBe('easy')
            ->and(AuditEvent::query()->whereIn('action', ['question.approved', 'question.edited'])->count())->toBe(2);
    });

    it('needs a source and an outline mapping before approval', function () {
        rvLesson($this, 'Agents disclose who they represent at the first contact. The IABS form explains it.');
        $item = ReviewItem::query()->where('kind', 'question')->firstOrFail();
        $item->forceFill(['portion' => null])->save();

        $this->actingAs($this->rachel)->post("/question-bank/{$item->id}/decision", ['decision' => 'approve'])->assertSessionHasErrors('item');
        expect(QuizQuestion::query()->where('review_item_id', $item->id)->exists())->toBeFalse();
    });

    it('takes a learner\'s report, and tells them when it\'s resolved', function () {
        Notification::fake();
        rvLesson($this, 'Agents disclose who they represent at the first contact. The IABS form explains it.');
        $item = ReviewItem::query()->where('kind', 'question')->firstOrFail();
        $this->actingAs($this->rachel)->post("/question-bank/{$item->id}/decision", ['decision' => 'approve']);
        $question = QuizQuestion::query()->where('review_item_id', $item->id)->sole();
        $maria = User::factory()->withRole(Role::Candidate)->create();

        $this->actingAs($maria)->postJson("/api/v1/questions/{$question->id}/report", ['reason' => 'wrong_answer', 'details' => 'The form is given at first contact, not closing.'])->assertCreated();
        $this->postJson("/api/v1/questions/{$question->id}/report", ['reason' => 'wrong_answer'])->assertOk();
        expect(QuestionDispute::query()->count())->toBe(1);

        $this->actingAs($this->rachel)->get('/question-bank?tab=disputed')->assertInertia(fn (Assert $page) => $page->has('disputes', 1)->where('disputes.0.reason', 'wrong_answer'));
        $dispute = QuestionDispute::query()->sole();
        $this->post("/question-bank/disputes/{$dispute->id}", ['outcome' => 'keep', 'resolution' => 'The answer matches 22 TAC §531.20.'])->assertSessionHasNoErrors();

        expect($dispute->refresh()->status)->toBe('kept');
        Notification::assertSentTo($maria, QuestionDisputeResolved::class);
        $this->actingAs(User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, Organisation::factory()->ofType(OrganisationType::School)->create())->create())
            ->post("/question-bank/disputes/{$dispute->id}", ['outcome' => 'keep', 'resolution' => 'Not my school.'])->assertNotFound();
    });
});
