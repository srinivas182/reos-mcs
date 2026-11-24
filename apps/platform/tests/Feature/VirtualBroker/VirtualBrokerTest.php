<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\BrokerQuestion;
use App\Models\Organisation;
use App\Models\PolicyManual;
use App\Models\PolicyPassage;
use App\Models\User;
use App\Notifications\PolicyQuestionAnswered;
use App\Notifications\PolicyQuestionEscalated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const LONE_STAR_MANUAL = <<<'TEXT'
# 4. Advertising
Every ad shows Lone Star Realty's name and logo. Agents send new ad templates to the office for approval before their first use.

# 6. Earnest money
Earnest money checks go to the title company named in the contract within three business days.

# 7. Commission splits
The standard split is 70/30 until the annual cap of $18,000.

# 9. Showings
Agents confirm showings through ShowingTime and leave a business card on the kitchen counter after every showing.
TEXT;

beforeEach(function () {
    $this->travelTo('2026-11-24 15:00:00');
    Storage::fake(config()->string('reos.content.disk'));
    Notification::fake();
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
});

function uploadManual(object $test, string $text = LONE_STAR_MANUAL): void
{
    $test->actingAs($test->linda)->post('/organisation/virtual-broker/manual', ['file' => UploadedFile::fake()->createWithContent('Lone Star policy manual.txt', $text)])
        ->assertRedirect()->assertSessionHasNoErrors();
}

function askBroker(object $test, string $question): array
{
    return $test->actingAs($test->james)->postJson('/api/v1/virtual-broker/questions', ['question' => $question])->assertCreated()->json('data');
}

describe('the virtual broker (A12.1)', function () {
    it('reads the policy manual into passages and topics, flags TREC conflicts, and keeps every version', function () {
        uploadManual($this);
        $manual = PolicyManual::query()->sole();

        expect($manual->version)->toBe(1)
            ->and($manual->passages)->toBe(4)
            ->and($manual->topics)->toContain('advertising', 'escrow', 'splits')
            ->and($manual->conflicts)->toHaveCount(1)
            ->and($manual->conflicts[0])->toMatchArray(['rule' => 'brokerage.trust_money', 'citation' => '22 TAC §535.146'])
            ->and($manual->conflicts[0]['excerpt'])->toContain('within three business days')
            ->and(PolicyPassage::query()->where('heading', '9. Showings')->value('content'))->toContain('ShowingTime');

        uploadManual($this, str_replace('within three business days', 'within two business days', LONE_STAR_MANUAL));
        expect(PolicyManual::query()->orderBy('version')->pluck('status', 'version')->all())->toBe([1 => 'replaced', 2 => 'current'])
            ->and(PolicyManual::query()->where('version', 2)->sole()->conflicts)->toBe([]);

        $this->get('/organisation/virtual-broker')->assertInertia(fn (Assert $page) => $page->component('organisation/virtual-broker')
            ->where('broker.manual.version', 2)->has('broker.versions', 2)->where('broker.conflicts', []));
    });

    it('answers from the manual with its page and heading, free and without bothering the broker', function () {
        uploadManual($this);
        $q = askBroker($this, 'Do I leave a business card after a showing?');

        expect($q)->toMatchArray(['status' => 'answered', 'confidence' => 'high', 'reason' => null])
            ->and($q['sources'][0])->toMatchArray(['kind' => 'manual', 'title' => '9. Showings'])
            ->and($q['answer'])->toContain('business card');
        Notification::assertNothingSent();
    });

    it('sends unclear money and legal questions to the broker with the time they aim to reply by', function () {
        uploadManual($this);

        // Money: the manual says three business days, TREC's rule wins and goes first.
        $money = askBroker($this, 'How many days do I have to deposit the earnest money?');
        expect($money)->toMatchArray(['status' => 'waiting', 'reason' => 'money', 'due_at' => '2026-11-24T19:00:00+00:00'])
            ->and($money['sources'][0])->toMatchArray(['kind' => 'rule', 'citation' => '22 TAC §535.146'])
            ->and($money['answer'])->toContain("TREC's rule wins");

        $legal = askBroker($this, "The buyer's attorney says they'll sue us over the inspection. What should I tell them?");
        expect($legal)->toMatchArray(['status' => 'waiting', 'reason' => 'legal']);

        $unclear = askBroker($this, 'Can I bring my puppy into the office?');
        expect($unclear)->toMatchArray(['status' => 'waiting', 'reason' => 'unclear', 'confidence' => 'low', 'sources' => []])
            ->and($unclear['answer'])->toContain("Lone Star Realty's policy manual");

        Notification::assertSentToTimes($this->linda, PolicyQuestionEscalated::class, 3);
        $this->actingAs($this->linda)->get('/organisation/virtual-broker')->assertInertia(fn (Assert $page) => $page->has('broker.waiting', 3)
            ->where('broker.stats.sent_to_broker', 3));
    });

    it('lets the agent send any answer to the broker', function () {
        uploadManual($this);
        $q = askBroker($this, 'Do I leave a business card after a showing?');

        $this->postJson("/api/v1/virtual-broker/questions/{$q['id']}/escalate")->assertOk()->assertJsonPath('data.status', 'waiting')->assertJsonPath('data.reason', 'asked');
        $this->postJson("/api/v1/virtual-broker/questions/{$q['id']}/escalate")->assertOk()->assertJsonPath('data.reason', 'asked');
        Notification::assertSentToTimes($this->linda, PolicyQuestionEscalated::class, 1);
    });

    it('takes the broker\'s answer as Confirmed or Corrected, and teaches the FAQ', function () {
        uploadManual($this);
        $q = askBroker($this, 'Who approves my yard sign design?');
        $draft = BrokerQuestion::query()->where('uuid', $q['id'])->value('answer');

        $this->actingAs($this->linda)->post("/organisation/virtual-broker/questions/{$q['id']}/reply", ['answer' => 'Send yard sign designs to Linda for approval; signs use the office template.', 'add_to_knowledge' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $row = $this->actingAs($this->james)->getJson("/api/v1/virtual-broker/questions/{$q['id']}")->json('data');
        expect($row)->toMatchArray(['status' => 'corrected', 'broker' => 'Linda Park', 'added_to_knowledge' => true])
            ->and($row['answer'])->toBe($draft);
        Notification::assertSentTo($this->james, PolicyQuestionAnswered::class, fn (PolicyQuestionAnswered $n): bool => $n->inbox($this->james)['title'] === 'Your broker corrected the answer');

        // The next agent gets the broker's answer straight away.
        $next = askBroker($this, 'Who approves a yard sign design?');
        expect($next['sources'][0]['kind'])->toBe('faq')->and($next['answer'])->toContain('office template');

        // Sent as drafted: Confirmed.
        $money = askBroker($this, 'Is the commission split 70/30?');
        $draft = BrokerQuestion::query()->where('uuid', $money['id'])->value('answer');
        $this->actingAs($this->linda)->post("/organisation/virtual-broker/questions/{$money['id']}/reply", ['answer' => $draft, 'add_to_knowledge' => false])->assertSessionHasNoErrors();
        expect(BrokerQuestion::query()->where('uuid', $money['id'])->value('status'))->toBe('confirmed');
        $this->post("/organisation/virtual-broker/questions/{$money['id']}/reply", ['answer' => 'Again', 'add_to_knowledge' => false])->assertSessionHasErrors('answer');

        // The FAQ entry can be removed, with Undo.
        $faq = PolicyPassage::query()->where('kind', 'faq')->sole();
        $this->delete("/organisation/virtual-broker/faq/{$faq->id}")->assertRedirect();
        expect(PolicyPassage::query()->where('kind', 'faq')->count())->toBe(0);
        $this->post("/organisation/virtual-broker/faq/{$faq->id}/restore")->assertRedirect();
        expect(PolicyPassage::query()->where('kind', 'faq')->count())->toBe(1);
    });

    it('sends escalations to the chosen broker, as fast as the brokerage says', function () {
        $dana = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Dana Cole']);
        $this->actingAs($this->linda)->put('/organisation/virtual-broker/settings', ['answerer_id' => $this->james->id, 'reply_hours' => 2])->assertSessionHasErrors('answerer_id');
        $this->put('/organisation/virtual-broker/settings', ['answerer_id' => $dana->id, 'reply_hours' => 2])->assertSessionHasNoErrors();

        $q = askBroker($this, 'Can I pay a referral fee to my cousin?');
        expect($q['due_at'])->toBe('2026-11-24T17:00:00+00:00');
        Notification::assertSentTo($dana, PolicyQuestionEscalated::class);
        Notification::assertNotSentTo($this->linda, PolicyQuestionEscalated::class);
        $this->getJson('/api/v1/virtual-broker')->assertOk()->assertJsonPath('data.reply_hours', 2)->assertJsonPath('data.manual', false);
    });

    it('keeps questions and knowledge inside the brokerage', function () {
        uploadManual($this);
        $q = askBroker($this, 'Do I leave a business card after a showing?');
        $other = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Hill Country Homes', 'states' => ['TX']]);
        $ana = User::factory()->withRole(Role::Agent, $other)->create();
        $bob = User::factory()->withRole(Role::Broker, $other)->withTwoFactor()->create();

        $this->actingAs($ana)->getJson("/api/v1/virtual-broker/questions/{$q['id']}")->assertNotFound();
        $answer = $this->postJson('/api/v1/virtual-broker/questions', ['question' => 'Do I leave a business card after a showing?'])->assertCreated()->json('data');
        expect($answer['sources'])->toBe([])->and($answer['status'])->toBe('waiting');
        $this->actingAs($bob)->post("/organisation/virtual-broker/questions/{$q['id']}/reply", ['answer' => 'Hijack', 'add_to_knowledge' => true])->assertNotFound();
        $this->actingAs(User::factory()->withRole(Role::Candidate)->create())->getJson('/api/v1/virtual-broker')->assertNotFound();
        $this->actingAs($this->james)->postJson('/api/v1/virtual-broker/questions', ['question' => 'About my deal', 'deal_id' => 999])->assertUnprocessable()->assertJsonValidationErrors('deal_id');
    });

    it('shows the virtual broker on the web', function () {
        uploadManual($this);
        $q = askBroker($this, 'Do I leave a business card after a showing?');
        $this->get('/virtual-broker?question='.$q['id'])->assertInertia(fn (Assert $page) => $page->component('virtual-broker/index')
            ->where('broker.brokerage', 'Lone Star Realty')->where('broker.manual', true)->has('broker.questions', 1)->where('open', $q['id']));
        $this->postJson('/virtual-broker/questions', ['question' => 'Where do showings get confirmed?'])->assertCreated();
    });
});
