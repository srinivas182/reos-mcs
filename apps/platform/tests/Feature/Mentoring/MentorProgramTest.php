<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Points;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Models\AgentProfile;
use App\Models\AuditEvent;
use App\Models\MentorAssignment;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\PayoutLine;
use App\Models\SessionBooking;
use App\Models\SessionOffer;
use App\Models\User;
use App\Notifications\MentorAssigned;
use App\Notifications\MentorChangeRequested;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $flags = app(Flags::class);
    $flag = $flags->flag('video_rooms');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);
    // Monday 23 November 2026, 9:00 in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));

    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->sarah = User::factory()->withRole(Role::Mentor, $this->lone)->create(['name' => 'Sarah Kim']);
    $this->dana = User::factory()->withRole(Role::Mentor, $this->lone)->create(['name' => 'Dana Brooks']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Priya Shah', 'locale' => 'es']);
    AgentProfile::query()->create(['user_id' => $this->priya->id, 'goal' => 'launch']);

    // The mentors set their own hours (and try to set a price: the brokerage's wins).
    foreach ([[$this->sarah, ['en', 'es']], [$this->dana, ['en']]] as [$mentor, $languages]) {
        $this->actingAs($mentor)->putJson('/api/v1/bookings/offer', [
            'bio' => 'Twelve years in Katy.', 'languages' => $languages, 'topics' => [], 'hours' => ['mon' => [['13:00', '17:00']], 'tue' => [['13:00', '17:00']]],
            'timezone' => 'America/Chicago', 'price_30_cents' => 9900, 'price_60_cents' => 9900, 'active' => true,
        ])->assertOk();
    }
});

describe('the mentor program (A13.1a)', function () {
    it('lets the broker price mentors, assign the best match free, and an agent pay another mentor with points (the done-when)', function () {
        $this->actingAs($this->linda)->get('/organisation/mentors')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('organisation/mentors')->has('mentors', 2)->where('agents.0.name', 'Priya Shah')->where('agents.0.mentor', null));

        // Broker screen 22: Dana charges $40 / $75 and accepts points; Sarah is free and focuses on first deals.
        $this->put("/organisation/mentors/{$this->dana->id}", ['topics' => ['listings', 'negotiation'], 'price_30_cents' => 4000, 'price_60_cents' => 7500, 'points' => true, 'max_mentees' => 4, 'who' => 'everyone'])->assertSessionHasNoErrors();
        $this->put("/organisation/mentors/{$this->sarah->id}", ['topics' => ['first_deals', 'contracts'], 'price_30_cents' => 0, 'price_60_cents' => 0, 'points' => true, 'max_mentees' => 5, 'who' => 'everyone'])->assertSessionHasNoErrors();
        expect(SessionOffer::query()->where('user_id', $this->dana->id)->sole()->price_60_cents)->toBe(7500)
            ->and(AuditEvent::query()->where('action', 'mentor_program.updated')->count())->toBe(2);

        // Screen 23: Sarah fits Priya (Spanish, first deals); assigning tells both.
        $matches = $this->getJson("/organisation/mentors/agents/{$this->priya->id}/matches")->assertOk()->json('data');
        expect($matches[0])->toMatchArray(['name' => 'Sarah Kim', 'reasons' => ['language', 'focus', 'capacity']])->and($matches[1]['name'])->toBe('Dana Brooks');
        $this->post('/organisation/mentors/assign', ['agent' => $this->priya->id, 'mentor' => $this->sarah->id])->assertSessionHasNoErrors();
        Notification::assertSentTo($this->priya, MentorAssigned::class);
        Notification::assertSentTo($this->sarah, MentorAssigned::class);
        expect(AuditEvent::query()->where('action', 'mentor.assigned')->sole()->properties)->toMatchArray(['after' => $this->sarah->id]);

        // Screens 114 and 116: her mentor first and free, then the others.
        $list = $this->actingAs($this->priya)->getJson('/api/v1/mentors')->assertOk()->json('data');
        expect($list['assigned'])->toBe($this->sarah->id)
            ->and(array_column($list['mentors'], 'name'))->toBe(['Sarah Kim', 'Dana Brooks'])
            ->and($list['mentors'][0])->toMatchArray(['assigned' => true, 'free' => true])
            ->and($list['mentors'][1])->toMatchArray(['assigned' => false, 'points' => true, 'price_60' => 7500]);

        // Sessions with her assigned mentor are free.
        $free = $this->postJson('/api/v1/bookings', ['instructor' => $this->sarah->id, 'starts_at' => '2026-11-23T19:00:00Z', 'minutes' => 60, 'topic' => 'My first offer'])->assertCreated()->json('data');
        expect($free)->toMatchArray(['status' => 'confirmed', 'price' => 0, 'paid_with' => 'free']);

        // Not enough points yet; then 10,000 points ($100 inside REOS) pay Dana's $75.
        $this->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-23T20:00:00Z', 'minutes' => 60, 'topic' => 'Winning a listing', 'pay_with' => 'points'])->assertUnprocessable()->assertJsonValidationErrors('points');
        app(Points::class)->award($this->priya, 20000, 'test:grant');
        $paid = $this->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-23T20:00:00Z', 'minutes' => 60, 'topic' => 'Winning a listing', 'pay_with' => 'points'])
            ->assertCreated()->assertJsonPath('checkout_url', null)->json('data');
        expect($paid)->toMatchArray(['status' => 'confirmed', 'price' => 7500, 'paid_with' => 'points', 'points' => 7500])
            ->and(app(Points::class)->balance($this->priya))->toBe(12500);

        // Dana earns her share as for a card payment (REOS funds the points).
        $order = Order::query()->where('gateway', 'points')->sole();
        expect($order->total_cents)->toBe(7500)
            ->and(PayoutLine::query()->where('order_id', $order->id)->where('payee_id', $this->dana->id)->sole()->amount_cents)->toBe(6750);

        // Cancelled more than 24 hours ahead: the points come back and Dana's share is cancelled.
        $later = $this->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-24T21:00:00Z', 'minutes' => 30, 'topic' => 'Pricing a listing', 'pay_with' => 'points'])->assertCreated()->json('data');
        expect(app(Points::class)->balance($this->priya))->toBe(8500);
        $this->postJson("/api/v1/bookings/{$later['uuid']}/cancel")->assertOk()->assertJsonPath('data.status', 'refunded');
        $refunded = SessionBooking::query()->where('uuid', $later['uuid'])->sole();
        expect(app(Points::class)->balance($this->priya))->toBe(12500)
            ->and(PayoutLine::query()->where('order_id', $refunded->order_id)->where('payee_id', $this->dana->id)->sole()->status->value)->toBe('cancelled')
            ->and(Order::query()->findOrFail($refunded->order_id)->status->value)->toBe('refunded');
    });

    it('keeps mentors to their brokerage, honours "assigned only", and takes change requests', function () {
        $this->actingAs($this->linda)->put("/organisation/mentors/{$this->dana->id}", ['topics' => [], 'price_30_cents' => 0, 'price_60_cents' => 0, 'points' => false, 'max_mentees' => 1, 'who' => 'assigned'])->assertSessionHasNoErrors();

        // Dana takes assigned mentees only: Priya can't see or book her.
        $this->actingAs($this->priya)->getJson('/api/v1/mentors')->assertOk()->assertJsonCount(1, 'data.mentors')->assertJsonPath('data.mentors.0.name', 'Sarah Kim');
        $this->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-23T19:00:00Z', 'minutes' => 30, 'topic' => 'Help please'])->assertUnprocessable()->assertJsonValidationErrors('instructor');

        // Assigned to Dana, Priya sees her first; Dana now has no room for another mentee.
        $this->actingAs($this->linda)->post('/organisation/mentors/assign', ['agent' => $this->priya->id, 'mentor' => $this->dana->id])->assertSessionHasNoErrors();
        $tom = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Tom Reyes']);
        expect(array_column($this->getJson("/organisation/mentors/agents/{$tom->id}/matches")->json('data'), 'name'))->toBe(['Sarah Kim']);
        $this->post('/organisation/mentors/assign', ['agent' => $tom->id, 'mentor' => $this->dana->id])->assertSessionHasErrors('mentor');
        $this->actingAs($this->priya)->getJson('/api/v1/mentors')->assertJsonPath('data.mentors.0.name', 'Dana Brooks')->assertJsonPath('data.mentors.0.free', true);

        // Points aren't accepted by a mentor that doesn't take them... and her sessions are free anyway.
        $this->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-23T19:00:00Z', 'minutes' => 30, 'topic' => 'Help please'])->assertCreated()->assertJsonPath('data.paid_with', 'free');

        // Asking for another mentor tells the broker.
        $this->postJson('/api/v1/mentors/change')->assertOk()->assertJsonPath('data.change_requested', true);
        Notification::assertSentTo($this->linda, MentorChangeRequested::class);
        $this->actingAs($this->linda)->get('/organisation/mentors')->assertInertia(fn (Assert $page) => $page->where('agents.1.change_requested', true));

        // Reassigning ends the old assignment.
        $this->post('/organisation/mentors/assign', ['agent' => $this->priya->id, 'mentor' => $this->sarah->id])->assertSessionHasNoErrors();
        expect(MentorAssignment::query()->where('agent_id', $this->priya->id)->whereNull('ended_at')->sole()->mentor_id)->toBe($this->sarah->id);

        // Another brokerage's broker sees none of it; an agent can't open the program.
        $other = Organisation::factory()->create(['name' => 'Gulf Coast Homes']);
        $stranger = User::factory()->withRole(Role::Broker, $other)->withTwoFactor()->create();
        $this->actingAs($stranger)->put("/organisation/mentors/{$this->dana->id}", ['topics' => [], 'price_30_cents' => 0, 'price_60_cents' => 0, 'points' => true, 'max_mentees' => 3, 'who' => 'everyone'])->assertNotFound();
        $this->actingAs($stranger)->getJson("/organisation/mentors/agents/{$this->priya->id}/matches")->assertNotFound();
        $this->actingAs($this->priya)->get('/organisation/mentors')->assertForbidden();
        expect(Organisation::query()->where('type', OrganisationType::Brokerage)->count())->toBeGreaterThanOrEqual(2);
    });
});
