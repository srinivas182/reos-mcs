<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\ComplianceEvent;
use App\Models\Connection;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\SocialPost;
use App\Models\TestAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00', 'America/Chicago'));
    Storage::fake('content');
    $this->school = Organisation::factory()->create(['name' => 'Texas Realty School', 'type' => OrganisationType::School]);
    $this->jordan = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Jordan Reyes']);
});

describe('the candidate studio (B13.3)', function () {
    it('posts a pass announcement safely (the done-when)', function () {
        $this->actingAs($this->jordan)->getJson('/api/v1/candidate-studio')->assertOk()->assertJsonPath('data', []);
        LicenceJourney::query()->create(['user_id' => $this->jordan->id, 'state_code' => 'TX', 'steps' => [], 'kit' => [], 'attempts' => [], 'weak_topics' => [], 'reminded' => [], 'passed_on' => '2026-11-26']);

        $this->getJson('/api/v1/candidate-studio')->assertJsonPath('data.0', ['milestone' => 'exam_pass', 'label' => 'Texas real estate exam', 'made' => null]);
        $made = $this->postJson('/api/v1/candidate-studio/moments/exam_pass')->assertOk()->json('data');
        expect($made['captions'])->toHaveCount(3)->and($made['captions'][0]['text'])->toContain('I passed the Texas real estate exam!')->toContain('#FutureAgent')
            ->and($made['designs'])->toHaveCount(3)->and(collect($made['checks'])->every(fn (array $c): bool => $c['ok']))->toBeTrue();
        Storage::disk('content')->assertExists($made['designs'][0]['path']);
        // Made once.
        expect($this->postJson('/api/v1/candidate-studio/moments/exam_pass')->json('data.id'))->toBe($made['id']);

        connectOnWeb($this, $this->jordan, tool: 'meta')->assertRedirect();
        $page = ['connection' => Connection::query()->where('integration_id', 'meta')->sole()->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];
        $posted = $this->postJson("/api/v1/candidate-studio/posts/{$made['id']}", ['caption' => 0, 'design' => 1, 'targets' => [$page]])->assertOk()->json('data');
        expect(SocialPost::query()->where('uuid', $posted['id'])->sole()->body)->toContain('I passed the Texas real estate exam!');
        $this->getJson('/api/v1/candidate-studio')->assertJsonPath('data.0.made', $made['id']);
    });

    it('blocks service advertising before the license is active', function () {
        $this->actingAs($this->jordan);
        $blocked = $this->postJson('/api/v1/candidate-studio/check', ['text' => 'Passed! Call me to buy or sell your home. Homes from $250,000. Join my team!'])->assertOk()->json('data');
        expect($blocked['ok'])->toBeFalse()
            ->and(array_column($blocked['issues'], 'phrase'))->toContain('call me', 'buy or sell', 'join my team', '$250,000')
            ->and($blocked['issues'][0]['citation'])->toBe('Tex. Occ. Code §1101.351');
        // "Future agent" wording is fine, and a candidate needs no broker's name.
        expect($this->postJson('/api/v1/candidate-studio/check', ['text' => 'Future agent in the making: I passed my exam!'])->json('data.ok'))->toBeTrue()
            ->and(ComplianceEvent::query()->where('user_id', $this->jordan->id)->pluck('action')->all())->toBe(['blocked', 'checked']);

        // The same rule holds when they post an edited caption.
        LicenceJourney::query()->create(['user_id' => $this->jordan->id, 'state_code' => 'TX', 'steps' => [], 'kit' => [], 'attempts' => [], 'weak_topics' => [], 'reminded' => [], 'passed_on' => '2026-11-26']);
        $made = $this->postJson('/api/v1/candidate-studio/moments/exam_pass')->json('data');
        connectOnWeb($this, $this->jordan, tool: 'meta')->assertRedirect();
        $page = ['connection' => Connection::query()->where('integration_id', 'meta')->sole()->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];
        $this->postJson("/api/v1/candidate-studio/posts/{$made['id']}", ['caption' => 0, 'design' => 0, 'text' => 'Passed! DM me to list your home.', 'targets' => [$page]])->assertUnprocessable();
    });

    it('finds study streaks and practice scores, and is for candidates only', function () {
        $course = DB::table('courses')->insertGetId(['title' => 'Principles', 'slug' => 'principles', 'type' => 'qualifying', 'created_at' => now(), 'updated_at' => now()]);
        foreach (range(0, 7) as $back) {
            DB::table('seat_time_days')->insert(['user_id' => $this->jordan->id, 'course_id' => $course, 'day' => now('America/Chicago')->subDays($back)->toDateString(), 'seconds' => 1800, 'created_at' => now(), 'updated_at' => now()]);
        }
        TestAttempt::query()->create(['user_id' => $this->jordan->id, 'kind' => 'simulation', 'mode' => 'exam', 'state_code' => 'TX', 'question_ids' => [], 'correct' => 86, 'total' => 100, 'started_at' => now()->subHours(3), 'submitted_at' => now()->subHours(2)]);

        $moments = collect($this->actingAs($this->jordan)->getJson('/api/v1/candidate-studio')->json('data'))->keyBy('milestone');
        expect($moments['study_streak']['label'])->toBe('7-day study streak')->and($moments['practice_score']['label'])->toBe('86% on a practice exam');
        $this->get('/candidate-studio')->assertOk()->assertInertia(fn (Assert $page) => $page->component('candidate-studio/index')->has('moments', 2));

        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->getJson('/api/v1/candidate-studio')->assertNotFound();
        $this->get('/candidate-studio')->assertNotFound();
    });
});
