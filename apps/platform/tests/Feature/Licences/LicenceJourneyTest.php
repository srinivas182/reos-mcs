<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\StudyPlan;
use App\Models\User;
use App\Notifications\ExamReminder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    // Monday 23 November 2026, 9:00 in Texas.
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->ana = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ana Ruiz']);
});

describe('the licence journey (A10.3a, screen 16)', function () {
    it('shows the state\'s steps from its rules, and the one-year window from the application', function () {
        $journey = $this->actingAs($this->ana)->getJson('/api/v1/licence-journey')->assertOk()
            ->assertJsonPath('data.state', ['code' => 'TX', 'name' => 'Texas'])
            ->assertJsonPath('data.window.filed_on', null)->assertJsonPath('data.window.months', 12)
            ->assertJsonPath('data.exam.vendor', 'Pearson VUE')->assertJsonPath('data.results.attempts_allowed', 3)
            ->json('data');
        expect(array_column($journey['steps'], 'code'))->toBe(['education', 'application', 'fingerprints', 'exam', 'sponsor', 'active'])
            ->and($journey['steps'][1]['url'])->toStartWith('https://www.trec.texas.gov/')
            ->and($journey['kit']['rules'])->toContain('two current IDs');

        $this->putJson('/api/v1/licence-journey/steps/education', ['done' => true, 'on' => '2026-10-02'])->assertOk()->assertJsonPath('data.steps.0.done_on', '2026-10-02');
        $this->putJson('/api/v1/licence-journey/steps/application', ['done' => true, 'on' => '2026-11-02'])->assertOk()
            ->assertJsonPath('data.window', ['filed_on' => '2026-11-02', 'ends_on' => '2027-11-02', 'days_left' => 344, 'months' => 12]);
        $this->putJson('/api/v1/licence-journey/steps/application', ['done' => true, 'on' => '2026-12-01'])->assertUnprocessable()->assertJsonValidationErrors('on');
        $this->putJson('/api/v1/licence-journey/steps/nope', ['done' => true])->assertUnprocessable()->assertJsonValidationErrors('step');

        // Un-checking the application closes the window again.
        $this->putJson('/api/v1/licence-journey/steps/application', ['done' => false])->assertOk()->assertJsonPath('data.window.ends_on', null);
    });

    it('sets the exam date, which the study plan follows, and refuses one after the window closes', function () {
        $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/steps/application', ['done' => true, 'on' => '2026-01-10'])->assertOk();
        $this->getJson('/api/v1/licence-journey')->assertJsonPath('data.readiness.ready', false)->assertJsonPath('data.readiness.line', 90);

        $this->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2026-11-20'])->assertUnprocessable()->assertJsonValidationErrors('exam_on');
        $this->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2027-02-01'])->assertUnprocessable()->assertJsonValidationErrors('exam_on');
        $this->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2026-12-14', 'time' => '08:30', 'centre' => 'Pearson VUE, 9800 Richmond Ave, Houston'])->assertOk()
            ->assertJsonPath('data.exam.on', '2026-12-14')->assertJsonPath('data.exam.days_to', 21)
            ->assertJsonPath('data.exam.directions_url', 'https://www.google.com/maps/search/?api=1&query=Pearson%20VUE%2C%209800%20Richmond%20Ave%2C%20Houston');
        expect(StudyPlan::query()->where('user_id', $this->ana->id)->where('active', true)->sole())
            ->exam_on->toDateString()->toBe('2026-12-14')->reason->toBe('exam_booked');

        $this->putJson('/api/v1/licence-journey/kit/id', ['done' => true])->assertOk()->assertJsonPath('data.kit.items.0', ['key' => 'id', 'done' => true]);
        $this->putJson('/api/v1/licence-journey/kit/snacks', ['done' => true])->assertUnprocessable();
    });

    it('reminds a week and a day before, then asks for the result, each once', function () {
        $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2026-11-30', 'centre' => 'Pearson VUE Houston'])->assertOk();

        $this->artisan('reos:licence:remind')->assertSuccessful();
        $this->artisan('reos:licence:remind')->assertSuccessful();
        Notification::assertSentToTimes($this->ana, ExamReminder::class, 1);
        Notification::assertSentTo($this->ana, ExamReminder::class, fn (ExamReminder $n) => $n->when === ExamReminder::WEEK);

        $this->travelTo(CarbonImmutable::parse('2026-11-29 08:15', 'America/Chicago'));
        $this->artisan('reos:licence:remind')->assertSuccessful();
        Notification::assertSentTo($this->ana, ExamReminder::class, fn (ExamReminder $n) => $n->when === ExamReminder::DAY && $n->appPath() === '/licence-journey/exam-day');

        $this->travelTo(CarbonImmutable::parse('2026-12-01 08:15', 'America/Chicago'));
        $this->artisan('reos:licence:remind')->assertSuccessful();
        $this->artisan('reos:licence:remind')->assertSuccessful();
        Notification::assertSentToTimes($this->ana, ExamReminder::class, 3);
        $this->getJson('/api/v1/licence-journey')->assertJsonPath('data.results.can_enter', true);
    });
});

describe('the result and the retake plan (A10.3a, screens 15 and 19)', function () {
    it('keeps a passed portion, plans the retake of a missed one from the score report, then checks the exam off', function () {
        $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2026-11-30'])->assertOk();
        $this->postJson('/api/v1/licence-journey/results', ['national' => true, 'state' => false, 'weak_topics' => ['state_rules']])->assertUnprocessable()->assertJsonValidationErrors('result');

        $this->travelTo(CarbonImmutable::parse('2026-11-30 17:00', 'America/Chicago'));
        $data = $this->postJson('/api/v1/licence-journey/results', ['national' => true, 'state' => false, 'weak_topics' => ['state_rules', 'agency']])->assertOk()
            ->assertJsonPath('data.results.national', ['passed' => true, 'attempts' => 1, 'failed' => 0, 'on' => '2026-11-30'])
            ->assertJsonPath('data.results.state.passed', false)
            ->assertJsonPath('data.exam.on', null)
            ->assertJsonPath('data.retake.portions', ['state'])->assertJsonPath('data.retake.attempts_left', ['state' => 2])->assertJsonPath('data.retake.more_education', false)
            ->json('data');
        // Only the missed portion's areas: the state portion is state rules.
        expect($data['retake']['topics'])->toBe(['state_rules']);
        expect(StudyPlan::query()->where('user_id', $this->ana->id)->where('active', true)->sole()->reason)->toBe('retake');

        // The retake: the passed national portion is kept and can't be entered again.
        $this->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2026-12-07'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-12-07 17:00', 'America/Chicago'));
        $this->postJson('/api/v1/licence-journey/results', ['national' => false, 'state' => null, 'weak_topics' => []])->assertUnprocessable();
        $this->postJson('/api/v1/licence-journey/results', ['national' => null, 'state' => true, 'weak_topics' => []])->assertOk()
            ->assertJsonPath('data.results.passed_both', true)->assertJsonPath('data.results.can_enter', false)->assertJsonPath('data.retake', null)
            ->assertJsonPath('data.steps.3.done_on', '2026-12-07')->assertJsonPath('data.steps.3.auto', true);
    });

    it('says more education is needed after the last attempt', function () {
        foreach (['2026-11-24', '2026-11-25', '2026-11-26'] as $day) {
            $this->travelTo(CarbonImmutable::parse("{$day} 08:00", 'America/Chicago'));
            $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/exam', ['exam_on' => $day])->assertOk();
            $this->postJson('/api/v1/licence-journey/results', ['national' => false, 'state' => true, 'weak_topics' => []])->assertOk();
        }

        $this->getJson('/api/v1/licence-journey')->assertJsonPath('data.retake.attempts_left', ['national' => 0])->assertJsonPath('data.retake.more_education', true)
            ->assertJsonPath('data.results.national.failed', 3)->assertJsonPath('data.results.state.attempts', 1);
        expect(LicenceJourney::query()->sole()->attempts)->toHaveCount(4);
    });

    it('serves the web pages', function () {
        $this->actingAs($this->ana)->get('/licence-journey')->assertOk()->assertInertia(fn (Assert $page) => $page->component('licence/index')->where('journey.state.code', 'TX'));
        $this->get('/licence-journey/exam-day')->assertOk()->assertInertia(fn (Assert $page) => $page->component('licence/exam-day'));
        $this->get('/licence-journey/result')->assertOk()->assertInertia(fn (Assert $page) => $page->component('licence/result'));
    });
});

it('is only for people studying for a licence', function () {
    $broker = User::factory()->withRole(Role::Broker, Organisation::factory()->create())->create();
    $this->actingAs($broker)->getJson('/api/v1/licence-journey')->assertForbidden();
    $this->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2026-12-14'])->assertForbidden();
});
