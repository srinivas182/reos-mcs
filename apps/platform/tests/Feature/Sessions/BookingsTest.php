<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\PayoutLine;
use App\Models\SessionBooking;
use App\Models\Todo;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Notifications\SessionRefunded;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $flags = app(Flags::class);
    $flag = $flags->flag('video_rooms');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);

    // Monday 23 November 2026, 9:00 in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->nelle = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Nelle Hart']);
    $this->ana = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ana Ruiz']);
});

/** Nelle's hours: weekdays 1 to 5 pm in Chicago; 30 minutes $35, 60 minutes $60 (or included). */
function nelleOffers(object $test, int $price30 = 3500, int $price60 = 6000): void
{
    $test->actingAs($test->nelle)->putJson('/api/v1/bookings/offer', [
        'bio' => 'Texas contracts and real estate math.', 'languages' => ['en', 'es'], 'topics' => ['Contracts', 'Math'],
        'hours' => ['mon' => [['13:00', '17:00']], 'tue' => [['13:00', '17:00']], 'wed' => [['13:00', '17:00']]],
        'timezone' => 'America/Chicago', 'price_30_cents' => $price30, 'price_60_cents' => $price60, 'active' => true,
    ])->assertOk()->assertJsonPath('data.price_60_cents', $price60);
}

describe('booking a session (A10.2a, the done-when)', function () {
    it('books an included session at once into a video session that ends on time, then the notes reach the student\'s to-dos', function () {
        nelleOffers($this, 0, 0);

        $slots = $this->actingAs($this->ana)->getJson("/api/v1/bookings/instructors/{$this->nelle->id}")->assertOk()
            ->assertJsonPath('data.name', 'Nelle Hart')->assertJsonPath('data.school', 'Texas Realty School')->json('slots_60');
        // At least 2 hours ahead, on the half hour, inside her hours.
        expect($slots[0])->toBe('2026-11-23T19:00:00Z')->and($slots)->toContain('2026-11-23T22:00:00Z')->not->toContain('2026-11-23T22:30:00Z');

        $booked = $this->postJson('/api/v1/bookings', ['instructor' => $this->nelle->id, 'starts_at' => '2026-11-23T19:00:00Z', 'minutes' => 60, 'topic' => 'Proration math'])
            ->assertCreated()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('checkout_url', null)->json('data');
        $session = VideoSession::query()->where('uuid', $booked['session'])->sole();
        expect($session->ends_at->toIso8601ZuluString())->toBe('2026-11-23T20:00:00Z');

        // The slot is gone, for everyone.
        $this->getJson("/api/v1/bookings/instructors/{$this->nelle->id}")->assertJsonMissing(['2026-11-23T19:00:00Z']);
        $this->postJson('/api/v1/bookings', ['instructor' => $this->nelle->id, 'starts_at' => '2026-11-23T19:00:00Z', 'minutes' => 60, 'topic' => 'Again'])->assertUnprocessable();

        // Nelle teaches; the session ends at the booked time (B6.3) and becomes complete.
        $session->participants()->where('role', VideoSessionParticipant::HOST)->update(['first_joined_at' => now()]);
        $session->forceFill(['notes' => "Good session.\n- Redo the proration drill\n- Review IABS timing"])->save();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 14:05', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        $this->artisan('reos:sessions:bookings')->assertSuccessful();
        expect(SessionBooking::query()->sole()->status)->toBe('completed');

        $this->actingAs($this->nelle)->getJson("/api/v1/bookings/{$booked['uuid']}/tasks")->assertOk()->assertJsonPath('data', ['Redo the proration drill', 'Review IABS timing']);
        $this->postJson("/api/v1/bookings/{$booked['uuid']}/tasks", ['tasks' => [['title' => 'Redo the proration drill', 'due_date' => '2026-11-24'], ['title' => 'Review IABS timing', 'due_date' => null]]])
            ->assertOk()->assertJsonPath('data.sent', 2);
        expect(Todo::query()->where('user_id', $this->ana->id)->where('source', 'session')->pluck('title')->all())->toBe(['Redo the proration drill', 'Review IABS timing']);

        // Ana rates it; the profile shows it after 24 hours.
        $this->actingAs($this->ana)->postJson("/api/v1/bookings/{$booked['uuid']}/rating", ['stars' => 5, 'tags' => ['clear', 'patient'], 'comment' => 'Finally get proration.'])->assertOk()->assertJsonPath('data.rating', 5);
        $this->postJson("/api/v1/bookings/{$booked['uuid']}/rating", ['stars' => 1])->assertUnprocessable();
        $this->getJson('/api/v1/bookings/instructors')->assertJsonPath('data.0.ratings', 0);
        $this->travel(25)->hours();
        $this->getJson('/api/v1/bookings/instructors')->assertJsonPath('data.0.ratings', 1)->assertJsonPath('data.0.rating', 5);
    });

    it('holds a paid session while the student pays at checkout, confirms it once paid, and shares the price with the instructor', function () {
        nelleOffers($this);

        $booked = $this->actingAs($this->ana)->postJson('/api/v1/bookings', ['instructor' => $this->nelle->id, 'starts_at' => '2026-11-24T19:00:00Z', 'minutes' => 30, 'topic' => 'Contract review'])
            ->assertCreated()->assertJsonPath('data.status', 'held')->json();
        expect($booked['checkout_url'])->toContain('/checkout/')->toContain('booking='.$booked['data']['uuid']);
        $price = (int) str($booked['checkout_url'])->after('/checkout/')->before('?')->toString();

        $this->get($booked['checkout_url'])->assertOk();
        $order = $this->postJson("/checkout/{$price}", ['booking' => $booked['data']['uuid']])->assertCreated()->json('order');
        // A session price can't be bought without its booking.
        $this->postJson("/checkout/{$price}")->assertUnprocessable();
        $this->post("/checkout/orders/{$order}/simulate")->assertRedirect();
        $this->get("/checkout/orders/{$order}/return");

        $booking = SessionBooking::query()->sole();
        expect($booking->status)->toBe('confirmed')->and($booking->video_session_id)->not->toBeNull()
            ->and(Order::query()->sole()->amount_cents)->toBe(3500)
            ->and(PayoutLine::query()->where('payee_id', $this->nelle->id)->value('amount_cents'))->toBe(3150);
    });

    it('releases a hold whose payment never came', function () {
        nelleOffers($this);
        $this->actingAs($this->ana)->postJson('/api/v1/bookings', ['instructor' => $this->nelle->id, 'starts_at' => '2026-11-24T19:00:00Z', 'minutes' => 30, 'topic' => 'Contract review'])->assertCreated();

        $this->travel(31)->minutes();
        $this->artisan('reos:sessions:bookings')->assertSuccessful();

        expect(SessionBooking::query()->sole()->status)->toBe('cancelled');
        $this->getJson("/api/v1/bookings/instructors/{$this->nelle->id}")->assertJsonFragment(['2026-11-24T19:00:00Z']);
    });

    it('cancels free of charge until 24 hours before, and refunds automatically when the instructor doesn\'t join', function () {
        nelleOffers($this, 0, 0);
        $early = $this->actingAs($this->ana)->postJson('/api/v1/bookings', ['instructor' => $this->nelle->id, 'starts_at' => '2026-11-25T19:00:00Z', 'minutes' => 30, 'topic' => 'Contracts'])->json('data');
        $soon = $this->postJson('/api/v1/bookings', ['instructor' => $this->nelle->id, 'starts_at' => '2026-11-23T20:00:00Z', 'minutes' => 30, 'topic' => 'Math'])->json('data');

        $this->getJson('/api/v1/bookings')->assertJsonPath('data.0.uuid', $soon['uuid'])->assertJsonPath('data.0.can_cancel', true);
        $this->postJson("/api/v1/bookings/{$early['uuid']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        expect(VideoSession::query()->where('uuid', $early['session'])->value('status'))->toBe('cancelled');

        // Nelle never joins: 10 minutes in, it's called off and Ana is told.
        $this->travelTo(CarbonImmutable::parse('2026-11-23T20:11:00Z'));
        $this->artisan('reos:sessions:bookings')->assertSuccessful();
        expect(SessionBooking::query()->where('uuid', $soon['uuid'])->value('status'))->toBe('no_show')
            ->and(VideoSession::query()->where('uuid', $soon['session'])->value('status'))->toBe('cancelled');
        Notification::assertSentTo($this->ana, SessionRefunded::class);

        // Someone else's booking: not found.
        $other = User::factory()->withRole(Role::Candidate, Organisation::platform())->create();
        $this->actingAs($other)->postJson("/api/v1/bookings/{$early['uuid']}/cancel")->assertNotFound();
    });

    it('lets only instructors and mentors set hours, and checks them', function () {
        $this->actingAs($this->ana)->putJson('/api/v1/bookings/offer', ['languages' => ['en'], 'topics' => [], 'hours' => [], 'timezone' => 'America/Chicago', 'price_30_cents' => 0, 'price_60_cents' => 0, 'active' => true])->assertForbidden();
        $this->actingAs($this->nelle)->putJson('/api/v1/bookings/offer', ['languages' => ['en'], 'topics' => [], 'hours' => ['mon' => [['13:15', '12:00']]], 'timezone' => 'America/Chicago', 'price_30_cents' => 0, 'price_60_cents' => 0, 'active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('hours');
    });
});
