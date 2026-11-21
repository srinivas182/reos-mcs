<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\StateStatus;
use App\Identity\DeviceInfo;
use App\Models\AuditEvent;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\State;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Identity\DeviceSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Signs a person in on a phone (a real device token) and sends it with later requests. */
function signInOnPhone(object $test, User $user): void
{
    $token = app(DeviceSessions::class)->issue($user, new DeviceInfo((string) Str::uuid(), 'Pixel 9', 'android'), 'phone', false)['token'];
    $test->withToken($token);
}

describe('states and the waitlist (journey screens 2–3)', function () {
    it('lists every state with Texas live first', function () {
        $response = $this->getJson('/api/v1/states')->assertOk();

        expect($response->json('data'))->toHaveCount(51)
            ->and($response->json('data.0'))->toBe(['code' => 'TX', 'name' => 'Texas', 'status' => 'live', 'live' => true])
            ->and($response->json('data.1.live'))->toBeFalse();
    });

    it('joins a waitlist once per device, then once per person', function () {
        $device = (string) Str::uuid();

        $this->postJson('/api/v1/states/la/waitlist', ['device_id' => $device])->assertOk()->assertJson(['waiting' => 1]);
        $this->postJson('/api/v1/states/LA/waitlist', ['device_id' => $device])->assertJson(['waiting' => 1]);
        $this->postJson('/api/v1/states/LA/waitlist', ['device_id' => (string) Str::uuid()])->assertJson(['waiting' => 2]);

        signInOnPhone($this, User::factory()->create());
        $this->postJson('/api/v1/states/LA/waitlist', ['email' => 'maria@example.com'])->assertJson(['waiting' => 3]);
        $this->postJson('/api/v1/states/LA/waitlist', ['email' => 'maria@example.com'])->assertJson(['waiting' => 3]);
    });

    it('needs a device or an email, and a real state', function () {
        $this->postJson('/api/v1/states/LA/waitlist', [])->assertUnprocessable()->assertJsonValidationErrors('device_id');
        $this->postJson('/api/v1/states/ZZ/waitlist', ['device_id' => (string) Str::uuid()])->assertNotFound();
    });
});

describe('the free quiz (journey screens 6–7)', function () {
    it('gives ten sample questions with answers, in English or Spanish', function () {
        $english = $this->getJson('/api/v1/states/TX/free-quiz')->assertOk();

        expect($english->json('data'))->toHaveCount(10)
            ->and($english->json('data.0'))->toHaveKeys(['id', 'topic', 'question', 'options', 'correct_option', 'explanation', 'source', 'sample'])
            ->and($english->json('data.0.options'))->toHaveCount(4)
            ->and($english->json('data.0.sample'))->toBeTrue();

        foreach (['?locale=es', ''] as $query) {
            $first = $this->withHeader('Accept-Language', 'es-MX')->getJson('/api/v1/states/TX/free-quiz'.$query)->json('data.0');
            $question = QuizQuestion::query()->findOrFail($first['id']);

            expect($first['question'])->toBe($question->in('es')['question'])
                ->and($first['options'])->toBe($question->in('es')['options']);
        }
    });

    it('saves the answers given before sign-up and scores them on the server', function () {
        $user = User::factory()->create();
        signInOnPhone($this, $user);
        $questions = QuizQuestion::query()->take(4)->get();

        $answers = $questions->map(fn (QuizQuestion $q, int $i): array => [
            'question_id' => $q->id,
            'choice' => $i < 3 ? $q->correct_option : ($q->correct_option + 1) % 4,
        ])->all();

        $this->postJson('/api/v1/me/quiz-attempts', ['state' => 'TX', 'answers' => $answers])
            ->assertCreated()
            ->assertJsonPath('correct', 3)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('percent', 75)
            ->assertJsonStructure(['topics' => [['topic', 'level']]]);

        expect($user->refresh()->id)->toBe(QuizAttempt::query()->sole()->user_id);
    });
});

describe('path, state and the 60-second setup (journey screens 1, 2 and 8)', function () {
    it('makes someone who wants to become an agent a candidate, and builds the plan', function () {
        $user = User::factory()->create();
        signInOnPhone($this, $user);
        $month = now()->addMonths(3)->format('Y-m');

        $this->putJson('/api/v1/me/onboarding', ['path' => 'become_agent', 'state' => 'TX'])
            ->assertOk()
            ->assertJsonPath('path', 'become_agent')
            ->assertJsonPath('completed', false);

        $this->putJson('/api/v1/me/onboarding', [
            'exam_month' => $month,
            'minutes_per_day' => 20,
            'locale' => 'es',
            'reminder_time' => '19:30',
            'complete' => true,
        ])
            ->assertOk()
            ->assertJsonPath('exam_month', $month)
            ->assertJsonPath('minutes_per_day', 20)
            ->assertJsonPath('reminder_time', '19:30')
            ->assertJsonPath('completed', true)
            ->assertJsonPath('plan.qualifying_hours', 180);

        $this->getJson('/api/v1/me')
            ->assertJsonPath('onboarding.completed', true)
            ->assertJsonPath('active_role.role', 'candidate')
            ->assertJsonPath('locale', 'es');

        expect(app(Memberships::class)->of($user->refresh()))->toHaveCount(1)
            ->and(AuditEvent::query()->where('action', 'role.granted')->value('actor_id'))->toBe($user->id);
    });

    it('accepts "Not sure" and refuses states that are not live', function () {
        signInOnPhone($this, User::factory()->create());

        $this->putJson('/api/v1/me/onboarding', ['exam_month' => null])->assertOk()->assertJsonPath('plan.days_to_exam', null);
        $this->putJson('/api/v1/me/onboarding', ['state' => 'LA'])->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->putJson('/api/v1/me/onboarding', ['minutes_per_day' => 7])->assertJsonValidationErrors('minutes_per_day');
        $this->putJson('/api/v1/me/onboarding', ['exam_month' => '2020-01'])->assertJsonValidationErrors('exam_month');
    });

    it('does not make other paths candidates', function () {
        $user = User::factory()->create();
        signInOnPhone($this, $user);

        $this->putJson('/api/v1/me/onboarding', ['path' => 'licensed_agent', 'state' => 'TX'])->assertOk();

        expect(app(Memberships::class)->of($user))->toBe([]);
    });
});

describe('States in the back office (web journey screen 61)', function () {
    it('shows status, Texas details and waiting counts to super admins', function () {
        $this->postJson('/api/v1/states/LA/waitlist', ['device_id' => (string) Str::uuid()]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/states')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/states/index')
                ->has('states', 51)
                ->where('states.0.code', 'TX')
                ->where('states.0.regulator', 'TREC')
                ->where('states.0.qualifying_hours', 180)
                ->where('states.1.code', 'LA')
                ->where('statuses', ['live', 'in_preparation', 'waitlist', 'not_planned'])
                ->where('states', fn ($states) => collect($states)->firstWhere('code', 'LA')['waiting'] === 1));
    });

    it('changes a status, audited', function () {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch('/admin/states/LA', ['status' => 'in_preparation'])->assertRedirect();

        expect(State::query()->findOrFail('LA')->status)->toBe(StateStatus::InPreparation)
            ->and(AuditEvent::query()->where('action', 'state.status_changed')->sole()->properties)
            ->toBe(['from' => 'waitlist', 'to' => 'in_preparation']);
    });

    it('is for super admins only', function () {
        $this->actingAs(User::factory()->withTwoFactor()->withRole(Role::Broker)->create())
            ->get('/admin/states')->assertForbidden();
    });
});
