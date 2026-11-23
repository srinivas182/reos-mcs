<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\StudyGroup;
use App\Models\User;
use App\Notifications\BrokerOfferNotice;
use App\Notifications\PassNews;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->ana = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'phone' => '+17135550123']);
    $this->ben = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ben Ortiz']);
    $this->paul = User::factory()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
});

/** Ana passes both portions today. */
function anaPasses(object $test): void
{
    $test->actingAs($test->ana)->putJson('/api/v1/licence-journey/exam', ['exam_on' => '2026-11-23'])->assertOk();
    $test->postJson('/api/v1/licence-journey/results', ['national' => true, 'state' => true, 'weak_topics' => []])->assertOk()
        ->assertJsonPath('data.results.passed_both', true)->assertJsonPath('data.news', 'prompt');
}

describe('sharing the news (A10.3b: f05 and f06)', function () {
    it('lets a candidate who passed choose who hears, never with scores, and classmates send congrats once', function () {
        $group = StudyGroup::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'Katy evening class', 'owner_id' => $this->ana->id]);
        $group->members()->attach([$this->ana->id => ['joined_at' => now()], $this->ben->id => ['joined_at' => now()]]);

        $this->actingAs($this->ana)->postJson('/api/v1/licence-journey/news', ['to_group' => true, 'to_school' => true, 'to_state' => true])->assertUnprocessable();
        anaPasses($this);
        $this->postJson('/api/v1/licence-journey/news', ['to_group' => false, 'to_school' => false, 'to_state' => false])->assertUnprocessable()->assertJsonValidationErrors('to_state');
        $this->postJson('/api/v1/licence-journey/news', ['to_group' => true, 'to_school' => true, 'to_state' => true, 'city' => 'Katy', 'tip' => 'Do the math lab every day.'])->assertOk();
        $this->getJson('/api/v1/licence-journey')->assertJsonPath('data.news', 'shared');

        Notification::assertSentTo($this->ben, PassNews::class, fn (PassNews $n) => $n->change === PassNews::PASSED && $n->inbox($this->ben)['title'] === 'Ana from Katy passed the Texas exam!');
        Notification::assertSentTo($this->paul, PassNews::class);
        Notification::assertNotSentTo($this->ana, PassNews::class);

        // Another Texas candidate sees it in Passes, with the week's count; never a surname or a score.
        $cara = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Cara Diaz']);
        $feed = $this->actingAs($cara)->getJson('/api/v1/licence-journey/passes')->assertOk()
            ->assertJsonPath('data.week', 1)->assertJsonPath('data.state', 'Texas')
            ->assertJsonPath('data.passes.0.first_name', 'Ana')->assertJsonPath('data.passes.0.city', 'Katy')->assertJsonPath('data.passes.0.tip', 'Do the math lab every day.')
            ->json('data.passes.0');
        expect(json_encode($feed))->not->toContain('Ruiz');

        $this->postJson("/api/v1/licence-journey/passes/{$feed['id']}/congrats", ['message' => 'Congrats, Ana!'])->assertOk()->assertJsonPath('data.passes.0.congrats', 1)->assertJsonPath('data.passes.0.sent', true);
        $this->postJson("/api/v1/licence-journey/passes/{$feed['id']}/congrats", ['message' => 'Again!'])->assertUnprocessable();
        Notification::assertSentTo($this->ana, PassNews::class, fn (PassNews $n) => $n->change === PassNews::CONGRATS && $n->from === 'Cara');
        $this->actingAs($this->ana)->postJson("/api/v1/licence-journey/passes/{$feed['id']}/congrats", ['message' => 'Me!'])->assertNotFound();
    });

    it('shares nothing on Not now', function () {
        anaPasses($this);
        $this->postJson('/api/v1/licence-journey/news/skip')->assertOk();
        $this->getJson('/api/v1/licence-journey')->assertJsonPath('data.news', 'skipped');
        $this->actingAs($this->ben)->getJson('/api/v1/licence-journey/passes')->assertJsonPath('data.passes', [])->assertJsonPath('data.week', 1);
        Notification::assertNothingSent();
    });
});

describe('broker offers (A10.3b: screen 17, decision 5)', function () {
    beforeEach(function () {
        $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
        $this->linda = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park']);
        $this->gulf = Organisation::factory()->create(['name' => 'Gulf Coast Homes', 'states' => ['TX']]);
        $this->gary = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->gulf)->create(['name' => 'Gary Moss']);
    });

    it('shows only opted-in candidates to brokerages in their state, without contact details, until one is accepted', function () {
        $this->actingAs($this->linda)->get('/recruiting')->assertOk()->assertInertia(fn (Assert $page) => $page->component('recruiting/index')->has('candidates', 0));

        $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/offers/opt-in', ['on' => true])->assertUnprocessable()->assertJsonValidationErrors('city');
        $this->putJson('/api/v1/licence-journey/offers/opt-in', ['on' => true, 'city' => 'Katy'])->assertOk()->assertJsonPath('data.opted_in', true);

        $this->actingAs($this->linda)->get('/recruiting')->assertInertia(fn (Assert $page) => $page
            ->where('candidates.0.first_name', 'Ana')->where('candidates.0.city', 'Katy')->where('candidates.0.stage', 'studying')->where('candidates.0.offer', null)
            ->missing('candidates.0.email'));

        $terms = ['office_city' => 'Houston', 'split_percent' => 70, 'monthly_fee_cents' => 9900, 'fees' => '$500 a year E&O', 'training' => 'Weekly contract classes', 'mentoring' => true, 'message' => 'We would love to have you.'];
        $this->put("/recruiting/candidates/{$this->ana->id}/offer", $terms)->assertSessionHasNoErrors()->assertRedirect();
        Notification::assertSentTo($this->ana, BrokerOfferNotice::class, fn (BrokerOfferNotice $n) => $n->change === BrokerOfferNotice::OFFER);
        expect(AuditEvent::query()->where('action', 'recruiting.offer_made')->sole()->properties)->toMatchArray(['candidate_id' => $this->ana->id, 'split_percent' => 70]);

        // Gulf Coast's office is in Katy: the better fit ranks first even on a lower split.
        $this->actingAs($this->gary)->put("/recruiting/candidates/{$this->ana->id}/offer", [...$terms, 'office_city' => 'Katy', 'split_percent' => 60])->assertSessionHasNoErrors();

        $offers = $this->actingAs($this->ana)->getJson('/api/v1/licence-journey/offers')->assertOk()
            ->assertJsonPath('data.offers.0.brokerage', 'Gulf Coast Homes')->assertJsonPath('data.offers.0.fit', true)
            ->assertJsonPath('data.offers.1.brokerage', 'Lone Star Realty')->assertJsonPath('data.offers.1.contact', null)
            ->json('data.offers');
        $this->getJson('/api/v1/licence-journey')->assertJsonPath('data.offers.open', 2);

        $this->postJson("/api/v1/licence-journey/offers/{$offers[1]['id']}/accept")->assertOk()->assertJsonPath('data.offers.0.status', 'accepted');
        Notification::assertSentTo($this->linda, BrokerOfferNotice::class, fn (BrokerOfferNotice $n) => $n->change === BrokerOfferNotice::ACCEPTED
            && str_contains((string) $n->inbox($this->linda)['body'], 'ana@example.com') && str_contains((string) $n->inbox($this->linda)['body'], '+17135550123'));
        $this->postJson("/api/v1/licence-journey/offers/{$offers[0]['id']}/decline")->assertOk()->assertJsonCount(1, 'data.offers');

        $this->actingAs($this->linda)->get('/recruiting')->assertInertia(fn (Assert $page) => $page->where('candidates.0.offer.contact.email', 'ana@example.com'));
        $this->actingAs($this->gary)->get('/recruiting')->assertInertia(fn (Assert $page) => $page->where('candidates.0.offer.status', 'declined')->where('candidates.0.offer.contact', null));
        $this->actingAs($this->linda)->post("/recruiting/offers/{$offers[1]['id']}/withdraw")->assertSessionHasErrors('offer');
    });

    it('keeps candidates from brokerages outside their state and from people who opted out', function () {
        $florida = Organisation::factory()->create(['name' => 'Sunshine Realty', 'states' => ['FL']]);
        $sam = User::factory()->withTwoFactor()->withRole(Role::Broker, $florida)->create();
        $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/offers/opt-in', ['on' => true, 'city' => 'Katy'])->assertOk();

        $this->actingAs($sam)->get('/recruiting')->assertInertia(fn (Assert $page) => $page->has('candidates', 0));
        $this->put("/recruiting/candidates/{$this->ana->id}/offer", ['office_city' => 'Miami', 'split_percent' => 80, 'monthly_fee_cents' => 0, 'mentoring' => false])->assertNotFound();

        $this->actingAs($this->ana)->putJson('/api/v1/licence-journey/offers/opt-in', ['on' => false])->assertOk()->assertJsonPath('data.opted_in', false);
        $this->actingAs($this->linda)->get('/recruiting')->assertInertia(fn (Assert $page) => $page->has('candidates', 0));
    });
});
