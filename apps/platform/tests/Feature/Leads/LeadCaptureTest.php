<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\AgentPage;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\OpenHouseVisit;
use App\Models\Organisation;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\NewLeadReceived;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Lead capture (B14.3a, ADR 0066): open house sign-in (QR or kiosk, offline
 * kiosk sign-ins once each) and the question form on the agent's page,
 * each into the agent's CRM with a follow-up and a suggested message.
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-28 13:30', 'America/Chicago'));
    $this->org = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->org)->create(['name' => 'Maria Garcia']);
});

function startOpenHouse(object $test): array
{
    return $test->actingAs($test->maria)->postJson('/api/v1/open-houses', ['address' => '12 Willow Bend', 'city' => 'Katy', 'starts_at' => '2026-11-28T13:00:00-06:00', 'ends_at' => '2026-11-28T16:00:00-06:00'])->assertOk()->json('data');
}

describe('open house sign-in (B14.3a)', function () {
    it('lands a QR sign-in in the CRM with a follow-up and a suggested message (the done-when)', function () {
        $house = startOpenHouse($this);
        expect($house)->toMatchArray(['address' => '12 Willow Bend', 'city' => 'Katy', 'open' => true, 'visits' => 0])
            ->and($house['url'])->toMatch('#/open-house/[A-Z2-9]{8}$#');
        $code = substr($house['url'], -8);
        auth()->forgetGuards();

        $this->get("/open-house/{$code}")->assertOk()->assertSee('12 Willow Bend, Katy')->assertSee('Hosted by Maria Garcia')->assertSee('Lone Star Realty')->assertSee('<meta name="robots" content="noindex">', false);
        $this->get("/open-house/{$code}/qr.svg")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $this->post("/open-house/{$code}", ['name' => 'Rosa Lima', 'email' => 'Rosa.Lima@example.com', 'phone' => '832 555 0177', 'timeline' => '3_months', 'pre_approved' => '1', 'has_agent' => '0', 'consent' => '1'])
            ->assertRedirect("/open-house/{$code}");
        $this->get("/open-house/{$code}")->assertSee('Thank you for signing in.');

        $contact = Contact::query()->where('owner_type', 'user')->where('owner_id', $this->maria->id)->sole();
        expect($contact->first_name)->toBe('Rosa')->and($contact->last_name)->toBe('Lima')->and(array_column($contact->emails, 'value'))->toBe(['rosa.lima@example.com'])
            ->and(ContactNote::query()->where('contact_id', $contact->id)->pluck('body')->all())->toBe([
                'Signed in at the open house at 12 Willow Bend on Nov 28, 2026. Not working with an agent. Pre-approved for a loan. Timeline: In 3 months.',
                'Suggested first message: Hi Rosa, thanks for visiting 12 Willow Bend today. Do you have any questions about the home, or would you like the details of similar homes nearby? Maria Garcia',
            ])
            ->and(Todo::query()->where('user_id', $this->maria->id)->sole())->title->toBe('Follow up with Rosa')
            ->and(OpenHouseVisit::query()->sole())->source->toBe('qr')->contact_id->toBe($contact->id);
        Notification::assertSentTo($this->maria, NewLeadReceived::class, fn (NewLeadReceived $n) => $n->inbox($this->maria)['title'] === 'Rosa Lima signed in at your open house');

        $this->actingAs($this->maria)->getJson("/api/v1/open-houses/{$house['id']}/visits")->assertOk()
            ->assertJsonPath('data.0.name', 'Rosa Lima')->assertJsonPath('data.0.contact', $contact->uuid)->assertJsonPath('data.0.timeline', '3_months');
        $this->getJson('/api/v1/open-houses')->assertJsonPath('data.0.visits', 1);
    });

    it('adds kiosk sign-ins made offline once each, and doesn\'t sell to someone with an agent', function () {
        $house = startOpenHouse($this);
        $visits = [
            ['client_id' => 'phone-1', 'name' => 'Ravi Patel', 'phone' => '(713) 555-0101', 'has_agent' => true, 'consent' => true, 'signed_in_at' => '2026-11-28T13:10:00-06:00'],
            ['client_id' => 'phone-2', 'name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'consent' => true, 'signed_in_at' => null],
        ];

        $this->postJson("/api/v1/open-houses/{$house['id']}/visits", ['visits' => $visits])->assertOk()->assertJsonPath('data', ['added' => 2, 'duplicates' => 0]);
        // The phone sends them again after a dropped connection: nothing doubles.
        $this->postJson("/api/v1/open-houses/{$house['id']}/visits", ['visits' => $visits])->assertJsonPath('data', ['added' => 0, 'duplicates' => 2]);

        expect(OpenHouseVisit::query()->count())->toBe(2)->and(Contact::query()->count())->toBe(2)
            ->and(Todo::query()->where('user_id', $this->maria->id)->pluck('title')->sort()->values()->all())->toBe(['Follow up with Ana', 'Send Ravi what they asked for'])
            ->and(ContactNote::query()->where('body', 'like', 'They\'re working with another agent%')->count())->toBe(1);

        // Consent and a way to reach them are required.
        $this->postJson("/api/v1/open-houses/{$house['id']}/visits", ['visits' => [['client_id' => 'x', 'name' => 'No Way', 'consent' => true]]])->assertUnprocessable();
        $this->postJson("/api/v1/open-houses/{$house['id']}/visits", ['visits' => [['client_id' => 'y', 'name' => 'No Consent', 'email' => 'n@example.com']]])->assertUnprocessable();
    });

    it('closes sign-in outside its hours, refuses bots, and keeps open houses to their agent', function () {
        $house = startOpenHouse($this);
        $code = substr($house['url'], -8);
        $james = User::factory()->withRole(Role::Agent, $this->org)->create();
        $this->actingAs($james)->getJson("/api/v1/open-houses/{$house['id']}/visits")->assertNotFound();
        auth()->forgetGuards();

        $this->from("/open-house/{$code}")->post("/open-house/{$code}", ['name' => 'Bot', 'email' => 'bot@example.com', 'consent' => '1', 'website' => 'http://spam.example'])->assertSessionHasErrors('website');
        $this->travelTo(CarbonImmutable::parse('2026-11-29 09:00', 'America/Chicago'));
        $this->get("/open-house/{$code}")->assertNotFound();
        expect(OpenHouseVisit::query()->count())->toBe(0);
    });

    it('shows open houses and visitors on the web', function () {
        startOpenHouse($this);
        $this->get('/open-houses')->assertOk()->assertInertia(fn (Assert $page) => $page->component('open-houses/index')->has('houses', 1)->where('visits', []));
    });
});

describe('the question form on the agent page (B14.3a)', function () {
    it('lands a message in the CRM with a follow-up', function () {
        (new AgentPage)->forceFill(['user_id' => $this->maria->id, 'handle' => 'maria-garcia', 'published' => true])->save();

        $this->get('/agent/maria-garcia')->assertOk()->assertSee('Ask Maria Garcia a question');
        $this->post('/agent/maria-garcia/contact', ['name' => 'Kevin Brooks', 'email' => 'kevin@example.com', 'message' => 'Is 12 Willow Bend still available?', 'consent' => '1'])
            ->assertRedirect(url('/agent/maria-garcia').'#contact');
        $this->get('/agent/maria-garcia')->assertSee('Thank you. Maria Garcia will get back to you soon.');

        $contact = Contact::query()->sole();
        expect(ContactNote::query()->where('contact_id', $contact->id)->first()?->body)->toBe('Wrote from your page: “Is 12 Willow Bend still available?”');
        Notification::assertSentTo($this->maria, NewLeadReceived::class, fn (NewLeadReceived $n) => $n->inbox($this->maria)['title'] === 'Kevin Brooks wrote from your page');

        $this->post('/agent/maria-garcia/contact', ['name' => 'Kevin', 'message' => 'Hi', 'consent' => '1'])->assertSessionHasErrors(['email', 'phone']);
        $this->post('/agent/nobody/contact', ['name' => 'Kevin', 'email' => 'k@example.com', 'message' => 'Hi', 'consent' => '1'])->assertNotFound();
    });
});
