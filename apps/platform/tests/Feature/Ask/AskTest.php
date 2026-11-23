<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Content\CourseStatus;
use App\Enums\OrganisationType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\Flashcard;
use App\Models\IntegrationSetting;
use App\Models\Lesson;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\InstructorAnswered;
use App\Notifications\StudentAsked;
use App\Services\Ai\AiSettings;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use App\Voice\UnderstandingFactory;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->ops = User::factory()->superAdmin()->create();
    $this->candidate = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ana Ruiz']);
});

/** A live Texas course whose lesson explains the IABS. */
function askCourse(User $ops): Lesson
{
    $course = app(Courses::class)->create(['title' => 'Texas Principles of Real Estate', 'type' => 'qualifying', 'states' => ['TX'], 'hours' => 30], $ops);
    $lesson = app(Lessons::class)->create($course, ['title' => 'Agency basics'], $ops);
    app(Lessons::class)->update($lesson, ['body' => "## The IABS form\nA license holder gives the Information About Brokerage Services (IABS) form at the first substantive contact with a prospective client.\n\n## Proration\nTexas property taxes are paid in arrears, so at closing the seller credits the buyer for the days the seller owned the home."], $ops);
    $course->forceFill(['status' => CourseStatus::Live])->save();

    return $lesson->refresh();
}

function bedrockOn(): MockHandler
{
    app(CredentialVault::class)->put('aws-bedrock-claude', CredentialEnvironment::Sandbox, ['access_key_id' => 'AKIA', 'secret_access_key' => 's', 'region' => 'us-east-2', 'model_id' => 'anthropic.claude'], null);
    IntegrationSetting::query()->create(['integration_id' => 'aws-bedrock-claude', 'mode' => IntegrationMode::Sandbox]);
    $mock = new MockHandler;
    app()->instance(UnderstandingFactory::HANDLER, $mock);

    return $mock;
}

function sponsorWith(int $credits): Organisation
{
    $sponsor = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Lone Star School of Real Estate']);
    $ledger = app(Ledger::class);
    $ledger->post('pool_funded', [[$ledger->platform('credits_sold'), -$credits], [$ledger->organisation($sponsor, 'credits'), $credits]], 'organisation', (string) $sponsor->id);

    return $sponsor;
}

describe('Ask, the study mate (A10.1a, the done-when)', function () {
    it('answers from the course content first with the lesson to watch, free, and keeps the conversation', function () {
        $lesson = askCourse($this->ops);

        $thread = $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'When is the IABS form given?'])->assertCreated()
            ->assertJsonPath('data.messages.0.role', 'question')
            ->assertJsonPath('data.messages.1.tier', 'content')
            ->assertJsonPath('data.messages.1.sources.0.lesson_id', $lesson->id)
            ->json('data');
        expect($thread['messages'][1]['body'])->toContain('first substantive contact');

        // Follow-ups work on the last answer; a math question offers a similar one in the math lab.
        $this->postJson('/api/v1/ask', ['mode' => 'quiz', 'thread' => $thread['uuid']])->assertCreated()->assertJsonPath('data.messages.2.quiz.correct', 0);
        $this->postJson('/api/v1/ask', ['question' => 'How do I prorate taxes of $3,650 at closing?', 'thread' => $thread['uuid']])->assertCreated()
            ->assertJsonPath('data.messages.4.math_kind', 'proration');

        $this->getJson('/api/v1/ask/threads')->assertOk()->assertJsonPath('data.0.uuid', $thread['uuid']);
        $this->getJson("/api/v1/ask/threads/{$thread['uuid']}")->assertOk()->assertJsonCount(5, 'data.messages');
        expect(DB::table('ai_credit_uses')->sum('credits'))->toBe(0);
    });

    it('answers beyond the course from the candidate\'s free monthly credits first', function () {
        askCourse($this->ops);
        $mock = bedrockOn();
        $mock->append(new Result(['output' => ['message' => ['content' => [['toolUse' => ['name' => 'answer_learner', 'input' => ['answer' => 'Not covered.', 'sources' => [], 'covered' => false]]]]]]]));
        $sent = null;
        $mock->append(function ($command) use (&$sent) {
            $sent = $command->toArray();

            return new Result(['output' => ['message' => ['content' => [['toolUse' => ['name' => 'answer_learner', 'input' => ['answer' => 'Louisiana assesses at 10% of fair market value. That goes beyond your Texas course.', 'sources' => [], 'covered' => true]]]]]]]);
        });

        $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'How are Louisiana property taxes assessed?'])->assertCreated()
            ->assertJsonPath('data.messages.1.tier', 'sponsored')
            ->assertJsonPath('data.messages.1.sponsor', null);

        expect($sent['system'][0]['text'])->toContain('goes beyond their course');
        $this->getJson('/api/v1/ask/package')->assertOk()->assertJsonPath('data', [
            'candidate' => true, 'beyond_course' => true, 'free_left' => 998, 'free_monthly' => 1000, 'sponsor' => null, 'credits_left' => 0, 'cost' => 2,
        ]);
    });

    it('then uses the sponsor\'s pool, then credits the candidate bought, then points to the instructor', function () {
        askCourse($this->ops);
        app(AiSettings::class)->save(['candidate_monthly_credits' => 0] + app(AiSettings::class)->current(), $this->ops);
        $sponsor = sponsorWith(2);
        // A candidate the school sponsors.
        $this->candidate = User::factory()->withRole(Role::Candidate, $sponsor)->create(['name' => 'Luis Ruiz']);
        $ledger = app(Ledger::class);

        // Demo data (no Bedrock): the course doesn't cover it, so the sponsor pays for a general answer.
        $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'Louisiana homestead exemption rules'])->assertCreated()
            ->assertJsonPath('data.messages.1.tier', 'sponsored')
            ->assertJsonPath('data.messages.1.sponsor', 'Lone Star School of Real Estate');
        expect($ledger->balance($ledger->organisation($sponsor, 'credits')))->toBe(0);

        // Credits the candidate bought come next.
        $ledger->post('topup', [[$ledger->platform('credits_sold'), -2], [$ledger->person($this->candidate, 'topup'), 2]], 'order', 'test-order');
        $this->postJson('/api/v1/ask', ['question' => 'Louisiana homestead exemption rules again'])->assertCreated()
            ->assertJsonPath('data.messages.1.tier', 'sponsored')->assertJsonPath('data.messages.1.sponsor', null);
        expect($ledger->balance($ledger->person($this->candidate, 'topup')))->toBe(0);

        // Nothing left: the instructor.
        $this->postJson('/api/v1/ask', ['question' => 'Louisiana homestead exemption one more time'])->assertCreated()
            ->assertJsonPath('data.messages.1.tier', 'limited')
            ->assertJsonPath('data.messages.1.body', __('ask.limitedNoCredits', ['state' => 'TX']));
        $this->getJson('/api/v1/ask/package')->assertJsonPath('data.beyond_course', false);
    });

    it('keeps conversations private and only for learners', function () {
        askCourse($this->ops);
        $uuid = $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'IABS form?'])->json('data.uuid');
        $other = User::factory()->withRole(Role::Candidate, Organisation::platform())->create();
        $provider = User::factory()->withRole(Role::Provider)->create();

        $this->actingAs($other)->getJson("/api/v1/ask/threads/{$uuid}")->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/ask', ['mode' => 'simpler', 'thread' => $uuid])->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/ask', ['mode' => 'simpler'])->assertUnprocessable();
        $this->actingAs($provider)->postJson('/api/v1/ask', ['question' => 'IABS form?'])->assertForbidden();
        $this->actingAs($this->candidate)->get('/ask?thread='.$uuid)->assertOk();
    });
});

describe('Instructor questions (A10.1b, screens i09 and i10)', function () {
    it('sends what the content couldn\'t answer to the course\'s instructor, whose answer joins the conversation and Ask\'s knowledge', function () {
        Notification::fake();
        $lesson = askCourse($this->ops);
        $nelle = User::factory()->withRole(Role::Instructor, Organisation::factory()->ofType(OrganisationType::School)->create())->create(['name' => 'Nelle Hart']);
        $lesson->course->instructors()->attach($nelle->id);

        $uuid = $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'IABS form at an open house?'])->json('data.uuid');
        $sent = $this->postJson("/api/v1/ask/threads/{$uuid}/instructor")->assertCreated()
            ->assertJsonPath('data.status', 'waiting')->assertJsonPath('data.course', 'Texas Principles of Real Estate')->json('data.uuid');
        // Sending again doesn't make a second question.
        $this->postJson("/api/v1/ask/threads/{$uuid}/instructor")->assertCreated()->assertJsonPath('data.uuid', $sent);
        $this->getJson("/api/v1/ask/threads/{$uuid}")->assertJsonPath('data.instructor', 'waiting');
        Notification::assertSentTo($nelle, StudentAsked::class);

        // Only the course's instructors see it.
        $this->getJson('/api/v1/instructor/questions')->assertOk()->assertJsonPath('data', []);
        $this->actingAs($this->candidate)->getJson("/api/v1/instructor/questions/{$sent}")->assertNotFound();

        $this->actingAs($nelle)->getJson('/api/v1/instructor/questions')->assertOk()
            ->assertJsonPath('counts.waiting', 1)->assertJsonPath('counts.from_content', 1)
            ->assertJsonPath('data.0.student', 'Ana Ruiz')->assertJsonPath('data.0.question', 'IABS form at an open house?');
        $this->postJson("/api/v1/instructor/questions/{$sent}/answer", ['answer' => 'At an open house, give the IABS once a conversation turns to a specific property or representation.', 'add_to_knowledge' => true, 'flashcard' => true])->assertOk()
            ->assertJsonPath('data.status', 'answered')->assertJsonPath('data.added_to_knowledge', true);
        $this->postJson("/api/v1/instructor/questions/{$sent}/answer", ['answer' => 'Again.'])->assertUnprocessable();
        Notification::assertSentTo($this->candidate, InstructorAnswered::class);

        $this->actingAs($this->candidate)->getJson("/api/v1/ask/threads/{$uuid}")->assertJsonPath('data.instructor', 'answered')
            ->assertJsonPath('data.messages.2.role', 'instructor')->assertJsonPath('data.messages.2.sponsor', 'Nelle Hart');
        expect(Flashcard::query()->where('user_id', $this->candidate->id)->count())->toBe(1);

        // The next student's question is answered from it, and re-indexing the lesson keeps it.
        app(Lessons::class)->update($lesson->refresh(), ['body' => "## The IABS form\nA license holder gives the IABS form at the first substantive contact.\n\nOpen house visitors are prospects."], $this->ops);
        $next = User::factory()->withRole(Role::Candidate, Organisation::platform())->create();
        $this->actingAs($next)->postJson('/api/v1/ask', ['question' => 'open house conversation representation'])->assertCreated()
            ->assertJsonPath('data.messages.1.tier', 'content')
            ->assertJsonPath('data.messages.1.body', fn (string $b) => str_contains($b, 'specific property'));
    });

    it('needs a course with an instructor', function () {
        askCourse($this->ops);
        $uuid = $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'IABS form?'])->json('data.uuid');

        $this->postJson("/api/v1/ask/threads/{$uuid}/instructor")->assertUnprocessable()->assertJsonValidationErrors('thread');
    });
});
