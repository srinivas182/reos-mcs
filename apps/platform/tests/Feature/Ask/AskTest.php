<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Content\CourseStatus;
use App\Enums\OrganisationType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\IntegrationSetting;
use App\Models\Lesson;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use App\Voice\UnderstandingFactory;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    it('never charges a candidate: beyond the course with no sponsor it says so and offers the instructor', function () {
        askCourse($this->ops);
        // The course has something on taxes, but not this: Claude says it isn't covered.
        bedrockOn()->append(new Result(['output' => ['message' => ['content' => [['toolUse' => ['name' => 'answer_learner', 'input' => ['answer' => 'Your sources don\'t cover Louisiana.', 'sources' => [], 'covered' => false]]]]]]]));

        $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'How are Louisiana property taxes assessed?'])->assertCreated()
            ->assertJsonPath('data.messages.1.tier', 'limited')
            ->assertJsonPath('data.messages.1.sponsor', null);
        $this->getJson('/api/v1/ask/package')->assertOk()->assertJsonPath('data', ['candidate' => true, 'beyond_course' => false, 'sponsor' => null, 'credits_left' => null]);
        expect(DB::table('ai_credit_uses')->sum('credits'))->toBe(0)
            ->and(DB::table('ledger_entries')->where('reason', 'ai_use')->exists())->toBeFalse();
    });

    it('answers beyond the course when a sponsor pays, from the sponsor\'s pool and never the candidate', function () {
        askCourse($this->ops);
        $sponsor = sponsorWith(10);
        // A candidate the school sponsors.
        $this->candidate = User::factory()->withRole(Role::Candidate, $sponsor)->create(['name' => 'Luis Ruiz']);
        $mock = bedrockOn();
        $sent = null;
        $mock->append(new Result(['output' => ['message' => ['content' => [['toolUse' => ['name' => 'answer_learner', 'input' => ['answer' => 'Not covered.', 'sources' => [], 'covered' => false]]]]]]]));
        $mock->append(function ($command) use (&$sent) {
            $sent = $command->toArray();

            return new Result(['output' => ['message' => ['content' => [['toolUse' => ['name' => 'answer_learner', 'input' => ['answer' => 'Louisiana assesses at 10% of fair market value. That goes beyond your Texas course.', 'sources' => [], 'covered' => true]]]]]]]);
        });

        $this->actingAs($this->candidate)->postJson('/api/v1/ask', ['question' => 'How are Louisiana property taxes assessed?'])->assertCreated()
            ->assertJsonPath('data.messages.1.tier', 'sponsored')
            ->assertJsonPath('data.messages.1.sponsor', 'Lone Star School of Real Estate');

        expect($sent['system'][0]['text'])->toContain('goes beyond their course')
            ->and(app(Ledger::class)->balance(app(Ledger::class)->organisation($sponsor, 'credits')))->toBe(9);
        $this->getJson('/api/v1/ask/package')->assertJsonPath('data.beyond_course', true)->assertJsonPath('data.sponsor', 'Lone Star School of Real Estate');
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
