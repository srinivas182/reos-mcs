<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\RolePlay;
use App\Models\User;
use App\RolePlay\RolePlayScorer;
use App\RolePlay\Scenarios;
use App\Voice\UnderstandingFactory;
use Aws\MockHandler;
use Aws\Result;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Role-play and the skills assessment (A14.1a, ADR 0060; agent screen 7):
 * a persona that pushes back, a score out of 100 and three fixes; five
 * rounds that seed the 90-day plan; private to the agent.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00', 'America/Chicago'));
    $this->org = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->org)->create(['name' => 'Maria Garcia']);
});

/** A strong listing answer set: introduction, proof, questions, acknowledging, and an ask at the end. */
const STRONG = [
    'Hi Mark, thanks for having me. My name is Maria and I sold 14 homes in Cinco Ranch this year, most in under 20 days.',
    'I understand, that number sounds great. What matters most to you, the price or the timing? The comparable sales say 485,000 and I can show you the data.',
    'My marketing plan has professional photos, a video tour and an open house the first weekend. How soon would you like to move?',
    'That makes sense. Can we meet Thursday at 4 to go over the listing agreement and next steps?',
];

/** Claude on Bedrock with test keys; its answers come from `$mock`. */
function rolePlayClaude(MockHandler $mock): void
{
    app(CredentialVault::class)->put('aws-bedrock-claude', CredentialEnvironment::Sandbox, [
        'access_key_id' => 'AKIATESTTESTTEST', 'secret_access_key' => 'test-secret', 'region' => 'us-east-2', 'model_id' => 'us.anthropic.claude-test',
    ], null);
    IntegrationSetting::query()->create(['integration_id' => 'aws-bedrock-claude', 'mode' => IntegrationMode::Sandbox]);
    app()->instance(UnderstandingFactory::HANDLER, $mock);
}

/** @param array<string, mixed> $input */
function rolePlayTool(string $name, array $input): Result
{
    return new Result(['output' => ['message' => ['role' => 'assistant', 'content' => [['toolUse' => ['toolUseId' => 'tooluse_1', 'name' => $name, 'input' => $input]]]]], 'stopReason' => 'tool_use']);
}

describe('role-play (A14.1a)', function () {
    it('gives a score and three fixes after a practice (the done-when)', function () {
        $this->actingAs($this->maria)->getJson('/api/v1/role-play')->assertOk()
            ->assertJsonCount(5, 'data.types')
            ->assertJsonPath('data.types.0', ['type' => 'listing', 'personas' => ['interviewing_seller', 'downsizing_couple'], 'best' => null])
            ->assertJsonPath('data.recent', [])
            ->assertJsonPath('data.assessment', null);

        $session = $this->postJson('/api/v1/role-play', ['type' => 'listing', 'persona' => 'interviewing_seller'])->assertOk()->json('data');
        expect($session)->toMatchArray(['type' => 'listing', 'kind' => 'practice', 'status' => 'active', 'turns_left' => 4, 'score' => null, 'fixes' => [], 'outcome' => null])
            ->and($session['turns'])->toBe([['role' => 'persona', 'text' => Scenarios::line('interviewing_seller', 0, 'en')]]);

        foreach (array_slice(STRONG, 0, 3) as $i => $answer) {
            $reply = $this->postJson("/api/v1/role-play/{$session['id']}/turns", ['text' => $answer])->assertOk()->json('data');
            // The persona pushes back with the next scripted line.
            expect($reply['turns'][count($reply['turns']) - 1])->toBe(['role' => 'persona', 'text' => Scenarios::line('interviewing_seller', $i + 1, 'en')])
                ->and($reply['turns_left'])->toBe(3 - $i);
        }

        $done = $this->postJson("/api/v1/role-play/{$session['id']}/turns", ['text' => STRONG[3]])->assertOk()->json('data');
        expect($done)->toMatchArray(['status' => 'scored', 'turns_left' => 0, 'outcome' => 'won', 'engine' => 'script'])
            ->and($done['turns'])->toHaveCount(9)
            ->and($done['turns'][8]['text'])->toBe(Scenarios::line('interviewing_seller', 'won', 'en'))
            ->and($done['score'])->toBe(array_sum($done['scores']))->toBeGreaterThanOrEqual(80)
            ->and($done['scores']['ask'])->toBe(20)
            ->and($done['fixes'])->toHaveCount(3)
            ->and($done['fixes'][0])->toHaveKeys(['dimension', 'text', 'example', 'lesson']);

        // Answering again isn't possible; the best score shows on the overview.
        $this->postJson("/api/v1/role-play/{$session['id']}/turns", ['text' => 'One more thing.'])->assertUnprocessable();
        $this->getJson('/api/v1/role-play')->assertJsonPath('data.types.0.best', $done['score'])->assertJsonPath('data.recent.0.id', $session['id']);
    });

    it('scores weak answers low, with fixes for the weakest parts, and the persona declines', function () {
        $id = $this->actingAs($this->maria)->postJson('/api/v1/role-play', ['type' => 'objection', 'persona' => 'rate_worried_buyer'])->json('data.id');
        $this->postJson("/api/v1/role-play/{$id}/turns", ['text' => 'um rates are fine'])->assertOk();

        // Finishing early scores what was said; the persona didn't agree to anything.
        $done = $this->postJson("/api/v1/role-play/{$id}/finish")->assertOk()->json('data');
        expect($done['score'])->toBeLessThan(40)
            ->and($done['outcome'])->toBe('lost')
            ->and(array_column($done['fixes'], 'dimension'))->toBe(['structure', 'questions', 'ask'])
            ->and($done['fixes'][2])->toBe(['dimension' => 'ask', 'text' => __('roleplay.fixes.ask.text'), 'example' => __('roleplay.fixes.ask.example'), 'lesson' => 'asking_for_business']);

        // Nothing said: there's nothing to score.
        $empty = $this->postJson('/api/v1/role-play', ['type' => 'objection', 'persona' => 'rate_worried_buyer'])->json('data.id');
        $this->postJson("/api/v1/role-play/{$empty}/finish")->assertUnprocessable()->assertJsonValidationErrors('text');
    });

    it('plays and scores in Spanish', function () {
        $this->maria->forceFill(['locale' => 'es'])->save();
        $session = $this->actingAs($this->maria)->postJson('/api/v1/role-play', ['type' => 'prospecting', 'persona' => 'fsbo'])->json('data');
        expect($session['turns'][0]['text'])->toBe('¿Bueno? Si es agente, estoy vendiendo la casa yo misma, gracias.');
        $done = $this->postJson("/api/v1/role-play/{$session['id']}/turns", ['text' => 'Hola Linda, entiendo. ¿Qué le parece si nos reunimos mañana para mostrarle cómo llegar a más compradores?'])->json('data');
        $done = $this->postJson("/api/v1/role-play/{$session['id']}/finish")->json('data');
        expect($done['scores']['ask'])->toBe(20)->and($done['scores']['questions'])->toBeGreaterThanOrEqual(5)
            ->and($done['fixes'][0]['text'])->toBe(__('roleplay.fixes.'.$done['fixes'][0]['dimension'].'.text', [], 'es'));
    });

    it('lets Claude play the persona and score it, and falls back to REOS if Claude fails', function () {
        $mock = new MockHandler;
        rolePlayClaude($mock);
        $mock->append(rolePlayTool('reply', ['text' => 'Thirty thousand more? Show me why your number is right.']));
        $mock->append(rolePlayTool('review', [
            'scores' => ['structure' => 15, 'questions' => 10, 'objections' => 12, 'clarity' => 18, 'ask' => 5],
            'fixes' => [
                ['dimension' => 'ask', 'text' => 'Close with a time to meet.', 'example' => 'Can we sit down Thursday at 4?'],
                ['dimension' => 'questions', 'text' => 'Ask about their timing first.', 'example' => 'When would you like to be moved?'],
                ['dimension' => 'objections', 'text' => 'Name the worry before you answer.', 'example' => 'I hear you, price matters most.'],
            ],
        ]));

        $id = $this->actingAs($this->maria)->postJson('/api/v1/role-play', ['type' => 'listing', 'persona' => 'interviewing_seller'])->json('data.id');
        $this->postJson("/api/v1/role-play/{$id}/turns", ['text' => STRONG[0]])->assertJsonPath('data.turns.2.text', 'Thirty thousand more? Show me why your number is right.');
        $done = $this->postJson("/api/v1/role-play/{$id}/finish")->json('data');
        expect($done)->toMatchArray(['score' => 60, 'engine' => 'claude', 'outcome' => 'lost'])
            ->and($done['fixes'][0])->toBe(['dimension' => 'ask', 'text' => 'Close with a time to meet.', 'example' => 'Can we sit down Thursday at 4?', 'lesson' => 'asking_for_business']);

        // Claude gives two fixes instead of three: REOS scores it.
        $mock->append(rolePlayTool('reply', ['text' => 'Hmm.']));
        $mock->append(rolePlayTool('review', ['scores' => ['structure' => 1, 'questions' => 1, 'objections' => 1, 'clarity' => 1, 'ask' => 1], 'fixes' => [['dimension' => 'ask', 'text' => 'a', 'example' => 'b']]]));
        $id = $this->postJson('/api/v1/role-play', ['type' => 'listing', 'persona' => 'interviewing_seller'])->json('data.id');
        $this->postJson("/api/v1/role-play/{$id}/turns", ['text' => STRONG[3]])->assertOk();
        $this->postJson("/api/v1/role-play/{$id}/finish")->assertJsonPath('data.engine', 'script')->assertJsonCount(3, 'data.fixes');
    });

    it('keeps practice private to the agent and open only to agents', function () {
        $id = $this->actingAs($this->maria)->postJson('/api/v1/role-play', ['type' => 'follow_up', 'persona' => 'cold_lead'])->json('data.id');
        $james = User::factory()->withRole(Role::Agent, $this->org)->create();
        $this->actingAs($james)->getJson("/api/v1/role-play/{$id}")->assertNotFound();
        $this->postJson("/api/v1/role-play/{$id}/turns", ['text' => 'Hello'])->assertNotFound();
        $this->getJson('/api/v1/role-play')->assertJsonPath('data.recent', []);

        $candidate = User::factory()->create();
        $this->actingAs($candidate)->getJson('/api/v1/role-play')->assertNotFound();

        // A persona from another type isn't allowed; nor is an empty answer.
        $this->actingAs($this->maria)->postJson('/api/v1/role-play', ['type' => 'listing', 'persona' => 'fsbo'])->assertUnprocessable()->assertJsonValidationErrors('persona');
        $this->postJson("/api/v1/role-play/{$id}/turns", ['text' => ''])->assertUnprocessable();
    });
});

describe('skills assessment (A14.1a, screen 7)', function () {
    it('runs five short rounds, finds the weakest skills and seeds the 90-day plan', function () {
        $answers = [
            'listing' => STRONG,
            'objection' => ['I understand, rates matter. What payment would feel comfortable? A lender can show options.', 'Can we meet this week with a lender?'],
            'prospecting' => ['ok', 'fine'],
            'appraisal_gap' => ['Hi Sara, I understand. The comparable sales support us, so we can ask for a reconsideration.', 'Can we meet tomorrow to go over your options?'],
            'follow_up' => ['Hi Kevin, thanks for picking up. What would make spring work for you?', 'Can we talk next Tuesday?'],
        ];

        foreach (Scenarios::TYPES as $n => $type) {
            $round = $this->actingAs($this->maria)->postJson('/api/v1/role-play/assessment')->assertOk()->json('data');
            expect($round)->toMatchArray(['type' => $type, 'kind' => 'assessment', 'round' => $n + 1, 'turns_left' => 2]);
            // Asking again returns the same open round.
            $this->postJson('/api/v1/role-play/assessment')->assertJsonPath('data.id', $round['id']);

            foreach (array_slice($answers[$type], 0, 2) as $answer) {
                $last = $this->postJson("/api/v1/role-play/{$round['id']}/turns", ['text' => $answer])->assertOk()->json('data');
            }

            expect($last['status'])->toBe('scored');
        }

        $assessment = $this->getJson('/api/v1/role-play')->json('data.assessment');
        expect($assessment)->toMatchArray(['done' => 5, 'total' => 5, 'next' => null, 'completed' => true])
            ->and(array_column($assessment['results'], 'type'))->toBe(Scenarios::TYPES)
            ->and($assessment['weakest'][0])->toBe('prospecting');

        // The plan now has a weekly practice of the weakest skill, ticked by doing one.
        $task = collect($this->getJson('/api/v1/agent/plan')->assertOk()->json('data.tasks'))->firstWhere('code', 'practice_prospecting');
        expect($task)->toMatchArray(['done' => false, 'auto' => true, 'link' => 'role_play']);
        $id = $this->postJson('/api/v1/role-play', ['type' => 'prospecting', 'persona' => 'expired'])->json('data.id');
        $this->postJson("/api/v1/role-play/{$id}/turns", ['text' => 'Hi Greg, I understand. Why do you think it did not sell? Can we meet tomorrow?'])->assertOk();
        $this->postJson("/api/v1/role-play/{$id}/finish")->assertOk();
        expect(collect($this->getJson('/api/v1/agent/plan')->json('data.tasks'))->firstWhere('code', 'practice_prospecting')['done'])->toBeTrue();

        // A finished assessment starts over.
        $this->postJson('/api/v1/role-play/assessment')->assertJsonPath('data.round', 1)->assertJsonPath('data.type', 'listing');
    });
});

describe('role-play on the web (A14.1a)', function () {
    it('shows practice, starts a role-play and scores it', function () {
        $this->actingAs($this->maria)->get('/role-play')->assertOk()->assertInertia(fn (Assert $page) => $page->component('role-play/index')->has('types', 5)->where('assessment', null));

        $this->post('/role-play', ['type' => 'appraisal_gap', 'persona' => 'low_appraisal'])->assertRedirect();
        $play = RolePlay::query()->sole();
        $this->get("/role-play/{$play->uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('role-play/show')->where('session.status', 'active')->has('session.turns', 1));
        $this->from("/role-play/{$play->uuid}")->post("/role-play/{$play->uuid}/turns", ['text' => 'I understand. The comparable sales support your price.'])->assertRedirect("/role-play/{$play->uuid}");
        $this->post("/role-play/{$play->uuid}/finish")->assertRedirect();
        expect($play->fresh()?->status)->toBe('scored')->and($play->fresh()?->fixes)->toHaveCount(3);

        $this->post('/role-play/assessment')->assertRedirect();
        expect(RolePlay::query()->whereNotNull('skill_assessment_id')->count())->toBe(1);
    });
});

it('has a fix and a lesson for every part of the score', function () {
    foreach (RolePlayScorer::DIMENSIONS as $dimension) {
        expect(RolePlayScorer::fix($dimension, 'en')['text'])->not->toBe('')->and(RolePlayScorer::LESSONS)->toHaveKey($dimension);
    }

    foreach (Scenarios::PERSONAS as $personas) {
        foreach ($personas as $persona) {
            foreach ([0, 1, 2, 3, 'won', 'lost'] as $line) {
                expect(Scenarios::line($persona, $line, 'es'))->not->toBe('')->not->toStartWith('roleplay.');
            }
        }
    }
});
