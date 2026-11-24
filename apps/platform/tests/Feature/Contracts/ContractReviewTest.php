<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\ContractReview\SampleContract;
use App\Enums\OrganisationType;
use App\Models\BrokerQuestion;
use App\Models\ContractReview;
use App\Models\Deal;
use App\Models\DealFile;
use App\Models\Organisation;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\ContractReviewReady;
use App\Notifications\PolicyQuestionEscalated;
use App\Services\Ai\AiCredits;
use App\Support\SimplePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-24 15:00:00');
    Storage::fake(config()->string('reos.content.disk'));
    Notification::fake();
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->deal = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'reos', 'property' => '1420 Pecan St, Austin', 'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);
});

function contractPdf(array $over = []): UploadedFile
{
    return UploadedFile::fake()->createWithContent('Pecan contract.pdf', SampleContract::pdf($over));
}

describe('contract review (A12.2)', function () {
    it('reviews a TREC 20-18: key terms with pages, dated deadlines and ranked findings, for 20 credits', function () {
        $before = app(AiCredits::class)->left($this->james);
        $review = $this->actingAs($this->james)->postJson('/api/v1/contracts', ['sample' => true, 'deal_id' => $this->deal->id])->assertCreated()->json('data');

        expect($review)->toMatchArray(['status' => 'done', 'step' => 'done', 'form' => '20-18', 'pages' => 5, 'credits' => 20, 'compared' => false])
            ->and($review['form_name'])->toBe('One to Four Family Residential Contract (Resale), TREC 20-18')
            ->and(collect($review['terms'])->keyBy('code')['sales_price'])->toMatchArray(['label' => 'Sales price', 'value' => '440,000.00', 'page' => 1])
            ->and(collect($review['terms'])->keyBy('code')['option_days'])->toMatchArray(['value' => '7', 'page' => 2])
            ->and(array_map(static fn (array $d): string => $d['code'].' '.$d['due_on'], $review['deadlines']))->toBe([
                'earnest_money 2026-11-27', 'option_period 2026-12-01', 'survey 2026-12-04', 'financing 2026-12-15', 'closing 2027-01-09',
            ])
            ->and($review['addenda'])->toBe(['third_party_financing']);

        // The done-when: dated findings, ranked, with pages and the rule.
        $findings = collect($review['findings'])->keyBy('code');
        expect($findings['weekend'])->toMatchArray(['severity' => 'check', 'date' => '2027-01-09', 'page' => 3, 'title' => 'Closing falls on a Saturday'])
            ->and($findings['special_provisions'])->toMatchArray(['severity' => 'check', 'page' => 3, 'citation' => '22 TAC §537.11'])
            ->and(app(AiCredits::class)->left($this->james))->toBe($before - 20);
        Notification::assertSentTo($this->james, ContractReviewReady::class, fn (ContractReviewReady $n): bool => $n->inbox($this->james)['body'] === 'Sample TREC 20-18.pdf: nothing High.');
    });

    it('keeps a new contract with the deal and shows what changed since the earlier version', function () {
        $this->actingAs($this->james)->post('/api/v1/contracts', ['file' => contractPdf(), 'deal_id' => $this->deal->id], ['Accept' => 'application/json'])->assertCreated();
        expect(DealFile::query()->where('deal_id', $this->deal->id)->count())->toBe(1);

        $second = $this->post('/api/v1/contracts', ['file' => contractPdf(['price' => '435,000.00', 'cash' => '83,000.00', 'closing' => 'January 8, 2027']), 'deal_id' => $this->deal->id], ['Accept' => 'application/json'])
            ->assertCreated()->json('data');
        $terms = collect($second['terms'])->keyBy('code');

        expect($second['compared'])->toBeTrue()
            ->and($terms['sales_price'])->toMatchArray(['value' => '435,000.00', 'previous' => '440,000.00', 'changed' => true])
            ->and($terms['buyer']['changed'])->toBeFalse()
            ->and(collect($second['findings'])->where('code', 'changed')->pluck('title')->all())->toBe(['Sales price changed', 'Cash portion changed', 'Closing date changed'])
            ->and(collect($second['findings'])->where('code', 'weekend')->all())->toBe([]);
    });

    it('flags money that doesn\'t add up, missing financing addendum and no Effective Date as High', function () {
        $pages = SampleContract::pages(['price' => '450,000.00']);
        unset($pages[3], $pages[4]);
        $file = UploadedFile::fake()->createWithContent('Broken.pdf', SimplePdf::pages(array_values($pages)));
        $review = $this->actingAs($this->james)->post('/api/v1/contracts', ['file' => $file], ['Accept' => 'application/json'])->assertCreated()->json('data');

        expect(collect($review['findings'])->where('severity', 'high')->pluck('code')->all())->toBe(['price_math', 'no_financing_addendum', 'no_effective_date'])
            ->and(collect($review['findings'])->firstWhere('code', 'price_math')['detail'])->toBe("Cash \$88,000.00 plus financing \$352,000.00 isn't the sales price of \$450,000.00.")
            ->and($review['deadlines'])->toHaveCount(1);
    });

    it('adds the deadlines to the deal once, and to-dos without a deal', function () {
        $review = $this->actingAs($this->james)->postJson('/api/v1/contracts', ['sample' => true, 'deal_id' => $this->deal->id])->json('data');
        $this->postJson("/api/v1/contracts/{$review['id']}/deadlines")->assertOk()->assertJsonPath('data.deadlines_added', true);
        $this->postJson("/api/v1/contracts/{$review['id']}/deadlines")->assertOk();

        expect($this->deal->dates()->orderBy('due_on')->get()->map(fn ($d) => $d->kind.' '.$d->due_on->toDateString())->all())->toBe([
            'other 2026-11-27', 'option_period 2026-12-01', 'survey 2026-12-04', 'financing 2026-12-15', 'closing 2027-01-09',
        ])->and(Todo::query()->where('user_id', $this->james->id)->count())->toBe(5);

        $loose = $this->postJson('/api/v1/contracts', ['sample' => true])->json('data');
        $this->postJson("/api/v1/contracts/{$loose['id']}/deadlines")->assertOk();
        expect(Todo::query()->where('user_id', $this->james->id)->where('source', 'contract')->orderBy('due_date')->value('title'))->toBe('Earnest money and option fee due · 1420 Pecan St, Austin, TX 78704');
    });

    it('sends the summary to the broker and answers questions about the contract with pages', function () {
        $review = $this->actingAs($this->james)->postJson('/api/v1/contracts', ['sample' => true, 'deal_id' => $this->deal->id])->json('data');
        $sent = $this->postJson("/api/v1/contracts/{$review['id']}/broker")->assertOk()->json('data');

        $question = BrokerQuestion::query()->where('uuid', $sent['broker_question'])->sole();
        expect($question)->status->toBe('waiting')->reason->toBe('asked')->deal_id->toBe($this->deal->id)
            ->and($question->question)->toBe('Please review the One to Four Family Residential Contract (Resale), TREC 20-18 for 1420 Pecan St, Austin before I respond.')
            ->and($question->answer)->toContain('Check: Closing falls on a Saturday (page 3)');
        Notification::assertSentTo($this->linda, PolicyQuestionEscalated::class);

        $left = app(AiCredits::class)->left($this->james);
        $asked = $this->postJson("/api/v1/contracts/{$review['id']}/questions", ['question' => 'When must the buyer give notice of termination in the option period?'])->assertOk()->json('data.questions.0');
        expect($asked['pages'])->toBe([2])
            ->and($asked['answer'])->toStartWith('Page 2 says:')
            ->and($asked['note'])->toStartWith('Hi Ana Lopez,')
            ->and(app(AiCredits::class)->left($this->james))->toBe($left - 1);
    });

    it('keeps reviews private, charges nothing when it can\'t read the file, and needs the credits', function () {
        $review = $this->actingAs($this->james)->postJson('/api/v1/contracts', ['sample' => true])->json('data');
        $this->actingAs(User::factory()->withRole(Role::Agent, $this->lone)->create())->getJson("/api/v1/contracts/{$review['id']}")->assertNotFound();
        $this->get("/contracts/{$review['id']}/file")->assertNotFound();
        $this->postJson('/api/v1/contracts', ['sample' => true, 'deal_id' => $this->deal->id])->assertUnprocessable()->assertJsonValidationErrors('deal_id');

        $this->actingAs($this->james);
        $left = app(AiCredits::class)->left($this->james);
        $blank = UploadedFile::fake()->createWithContent('Scan.pdf', SimplePdf::pages(['']));
        $failed = $this->post('/api/v1/contracts', ['file' => $blank], ['Accept' => 'application/json'])->assertCreated()->json('data');
        expect($failed['status'])->toBe('failed')->and($failed['error'])->toContain('no text REOS can read')
            ->and(app(AiCredits::class)->left($this->james))->toBe($left);

        config(['reos.ai.monthly_credits' => 10]);
        $poor = User::factory()->withRole(Role::Agent, $this->lone)->create();
        $this->actingAs($poor)->postJson('/api/v1/contracts', ['sample' => true])->assertUnprocessable()->assertJsonValidationErrors('file');
        expect(ContractReview::query()->where('user_id', $poor->id)->count())->toBe(0);
    });

    it('shows contract review on the web, with the contract beside it', function () {
        $review = $this->actingAs($this->james)->postJson('/contracts', ['sample' => true])->assertCreated()->json('data');
        $this->get('/contracts')->assertInertia(fn (Assert $page) => $page->component('contracts/index')->has('contracts.reviews', 1)->where('contracts.cost', 20)->where('contracts.sample', true));
        $this->get("/contracts/{$review['id']}")->assertInertia(fn (Assert $page) => $page->component('contracts/show')->where('review.form', '20-18'));
        $this->get("/contracts/{$review['id']}/file")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });
});
