<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Onboarding\WebDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Ten answers to the free quiz: the first `$right` correct, the rest wrong. */
function webAnswers(int $right): array
{
    return QuizQuestion::query()->whereNull('state_code')->orWhere('state_code', 'TX')->limit(10)->get()
        ->values()
        ->map(fn (QuizQuestion $q, int $i): array => [
            'question_id' => $q->id,
            'choice' => $i < $right ? $q->correct_option : ($q->correct_option + 1) % 4,
        ])->all();
}

describe('the public site (web journey screen 1)', function () {
    it('shows the home page with the live states', function () {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('liveStates', ['Texas']));

        $this->get('/get-started')->assertInertia(fn (Assert $page) => $page->where('liveStates', ['Texas']));
    });

    it('has a landing page for each audience, and no others', function (string $audience) {
        $this->get("/for/{$audience}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/audience')->where('audience', $audience));
    })->with(['candidates', 'agents', 'brokerages', 'schools', 'associations', 'service-providers']);

    it('refuses unknown audiences', function () {
        $this->get('/for/aliens')->assertNotFound();
    });
});

describe('becoming an agent on the web (candidate journey screens 2–7)', function () {
    it('starts with the states, Texas live', function () {
        $this->get('/get-started')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('get-started/state')
            ->has('states', 51)
            ->where('states.0.code', 'TX')
            ->where('states.0.live', true)
            ->where('chosen', null));
    });

    it('goes to the quiz for a live state and to the waitlist otherwise', function () {
        $this->post('/get-started/state', ['state' => 'la'])->assertRedirect('/get-started/waitlist/LA');
        $this->post('/get-started/state', ['state' => 'tx'])->assertRedirect('/get-started/quiz');
        $this->post('/get-started/state', ['state' => 'ZZ'])->assertSessionHasErrors('state');
    });

    it('joins the waitlist with an email, once', function () {
        $this->get('/get-started/waitlist/LA')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('get-started/waitlist')
            ->where('state.code', 'LA')
            ->where('state.waiting', 0)
            ->where('live.0.code', 'TX'));

        $this->post('/get-started/waitlist/LA', [])->assertSessionHasErrors('email');
        $this->from('/get-started/waitlist/LA')->post('/get-started/waitlist/LA', ['email' => 'maria@example.com'])
            ->assertRedirect('/get-started/waitlist/LA')->assertSessionHas('joined', true);
        $this->post('/get-started/waitlist/LA', ['email' => 'maria@example.com']);

        $this->get('/get-started/waitlist/LA')->assertInertia(fn (Assert $page) => $page->where('state.waiting', 1));
        $this->get('/get-started/waitlist/TX')->assertRedirect('/get-started');
    });

    it('serves ten questions with no account, then scores them', function () {
        $this->get('/get-started/quiz')->assertRedirect('/get-started');
        $this->get('/get-started/score')->assertRedirect('/get-started/quiz');

        $this->post('/get-started/state', ['state' => 'TX']);
        $this->get('/get-started/quiz')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('get-started/quiz')
            ->where('state', 'Texas')
            ->has('questions', 10)
            ->has('questions.0', fn (Assert $q) => $q->hasAll(['id', 'topic', 'question', 'options', 'correct_option', 'explanation', 'source', 'sample'])));

        $this->post('/get-started/quiz', ['answers' => webAnswers(4)])->assertRedirect('/get-started/score');
        $this->get('/get-started/score')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('get-started/score')
            ->where('correct', 4)
            ->where('total', 10)
            ->has('topics')
            ->has('signInOptions'));
    });

    it('refuses answers that are not real questions or options', function () {
        $this->post('/get-started/quiz', ['answers' => [['question_id' => 999999, 'choice' => 0]]])->assertSessionHasErrors('answers.0.question_id');
        $this->post('/get-started/quiz', ['answers' => [['question_id' => QuizQuestion::query()->value('id'), 'choice' => 7]]])->assertSessionHasErrors('answers.0.choice');
    });

    it('saves the draft to the new account at sign-up, then asks the three quick questions', function () {
        $this->post('/get-started/state', ['state' => 'TX']);
        $this->post('/get-started/quiz', ['answers' => webAnswers(7)]);

        $this->post('/register', [
            'name' => 'Maria Lopez',
            'email' => 'maria@example.com',
            'password' => 'a-Strong-passw0rd-2026',
            'password_confirmation' => 'a-Strong-passw0rd-2026',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        expect($user->onboarding_path)->toBe('become_agent')
            ->and($user->target_state)->toBe('TX')
            ->and(QuizAttempt::query()->where('user_id', $user->id)->value('correct'))->toBe(7)
            ->and(collect(app(Memberships::class)->of($user))->pluck('role')->all())->toContain(Role::Candidate)
            ->and(session()->has(WebDraft::KEY))->toBeFalse();

        $user->markEmailAsVerified();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/get-started/setup');
    });

    it('builds the plan, then the dashboard opens', function () {
        $user = User::factory()->create();
        $user->forceFill(['onboarding_path' => 'become_agent', 'target_state' => 'TX'])->save();
        $this->actingAs($user);

        $this->get('/get-started/plan')->assertRedirect('/get-started/setup');
        $this->get('/get-started/setup')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('get-started/setup')
            ->where('choices.minutes_per_day', null));

        $month = now()->addMonths(2)->format('Y-m');
        $this->post('/get-started/setup', ['exam_month' => $month, 'minutes_per_day' => 25, 'locale' => 'en', 'reminder_time' => '19:30'])
            ->assertRedirect('/get-started/plan');

        $fresh = $user->fresh();
        expect($fresh?->onboarded_at)->not->toBeNull()
            ->and($fresh?->minutes_per_day)->toBe(25)
            ->and($fresh?->exam_month?->format('Y-m'))->toBe($month);

        $this->get('/get-started/plan')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('get-started/plan')
            ->where('onboarding.exam_month', $month)
            ->where('onboarding.minutes_per_day', 25)
            ->where('onboarding.plan.qualifying_hours', 180)
            ->where('state', 'Texas'));
        $this->get('/dashboard')->assertOk();
    });

    it('checks the three quick questions', function () {
        $this->actingAs(User::factory()->create());

        $this->post('/get-started/setup', ['minutes_per_day' => 7, 'exam_month' => '2020-01', 'reminder_time' => '25:00'])
            ->assertSessionHasErrors(['minutes_per_day', 'exam_month', 'reminder_time']);
    });

    it('sends other audiences to sign-up with their path remembered', function () {
        $this->get('/sign-up/licensed_agent')->assertRedirect('/register');
        expect(session(WebDraft::KEY.'.path'))->toBe('licensed_agent');
        $this->get('/sign-up/become_agent')->assertRedirect('/get-started');
        $this->get('/sign-up/astronaut')->assertNotFound();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/sign-up/provider')->assertRedirect('/dashboard');
        expect($user->fresh()?->onboarding_path)->toBe('provider');
    });

    it('keeps the choices of someone who has already set up', function () {
        $user = User::factory()->create();
        $user->forceFill(['onboarding_path' => 'licensed_agent', 'target_state' => 'TX'])->save();

        $this->get('/get-started');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        expect($user->fresh()?->onboarding_path)->toBe('licensed_agent');
    });
});

it('keeps the language a visitor was using when they sign up', function () {
    $this->withUnencryptedCookie('locale', 'es')->post('/register', [
        'name' => 'Maria Lopez',
        'email' => 'maria.es@example.com',
        'password' => 'a-Strong-passw0rd-2026',
        'password_confirmation' => 'a-Strong-passw0rd-2026',
    ]);

    expect(User::query()->where('email', 'maria.es@example.com')->firstOrFail()->locale->value)->toBe('es');
});
