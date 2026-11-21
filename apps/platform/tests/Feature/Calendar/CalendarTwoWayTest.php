<?php

declare(strict_types=1);

use App\Connections\ConnectionStatus;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarSimulator;
use App\Integrations\IntegrationMode;
use App\Jobs\PushCalendarEvent;
use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Models\SimulatorRecord;
use App\Models\User;
use App\Services\Calendar\CalendarEvents;
use App\Services\Privacy\DataExport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
 * The REOS calendar, two-way with Google Calendar (B4.1, ADR 0017), end to
 * end on the Simulator: an event made in REOS appears in "Google", and an
 * edit in "Google" comes back. Contract tests against Google's recorded
 * answers are in GoogleCalendarContractTest.
 */

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-21T15:00:00Z')));

/** Maria, a Lone Star agent, with Google Calendar connected on demo data. */
function mariaWithCalendar(object $test): User
{
    $maria = User::factory()->create(['name' => 'Maria Lopez', 'email' => 'maria@example.com']);
    connectOnWeb($test, $maria);

    return $maria;
}

/** @return array<string, mixed> */
function appointment(array $overrides = []): array
{
    return [
        'title' => 'Buyer consultation — the Nguyens',
        'kind' => 'appointment',
        'starts_at' => '2026-11-23T10:00:00-06:00',
        'ends_at' => '2026-11-23T11:00:00-06:00',
        'location' => 'Lone Star Realty, Katy office',
        'attendees' => ['nguyen.family@example.com'],
        ...$overrides,
    ];
}

describe('done when: on the Simulator, end to end', function () {
    it('puts an event made in REOS into Google, and brings an edit made in Google back', function () {
        $maria = mariaWithCalendar($this);
        $connection = Connection::query()->sole();

        $id = $this->actingAs($maria)->postJson('/api/v1/calendar/events', appointment())
            ->assertCreated()
            ->assertJsonPath('data.source', 'reos')
            ->assertJsonPath('data.tool', 'Google Calendar')
            ->assertJsonPath('data.calendar', 'maria@example.com')
            ->json('data.id');

        $event = CalendarEvent::query()->findOrFail($id);
        $inGoogle = SimulatorRecord::query()->where('connection_id', $connection->id)->where('external_id', $event->external_id)->sole();
        expect($event->push_state)->toBeNull()
            ->and($inGoogle->container)->toBe('primary')
            ->and($inGoogle->data)->toMatchArray([
                'title' => 'Buyer consultation — the Nguyens',
                'starts_at' => '2026-11-23T16:00:00+00:00',
                'attendees' => ['nguyen.family@example.com'],
                'reos_id' => $event->id,
            ]);

        // Maria renames it in Google; Google's push brings it back.
        $this->travel(10)->minutes();
        $this->artisan('reos:connections:simulate', ['connection' => $connection->id, '--change-event' => 'Buyer consultation — moved to Zoom', '--event' => $event->external_id])
            ->assertSuccessful();

        expect($event->fresh())->title->toBe('Buyer consultation — moved to Zoom')->source->toBe('reos')->kind->toBe('appointment')
            ->and(CalendarConflict::query()->exists())->toBeFalse();

        // Deleted in Google: gone from REOS too.
        $this->artisan('reos:connections:simulate', ['connection' => $connection->id, '--delete' => true, '--event' => $event->external_id])->assertSuccessful();
        expect(CalendarEvent::withTrashed()->find($event->id))->toBeNull();
    });

    it('keeps the losing version when both sides changed, and Undo brings it back', function () {
        $maria = mariaWithCalendar($this);
        $connection = Connection::query()->sole();
        $event = app(CalendarEvents::class)->create($maria, [...appointment(), 'starts_at' => CarbonImmutable::parse('2026-11-23T16:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-23T17:00:00Z')])->fresh();

        // Maria edits it in REOS while Google can't be reached: the change waits...
        $this->travel(5)->minutes();
        $connection->forceFill(['status' => ConnectionStatus::ReconnectNeeded])->save();
        app(CalendarEvents::class)->update($event, ['title' => 'Consultation (REOS edit)']);
        expect($event->fresh()?->push_state)->toBe('pending');
        // ...and a minute later renames it in Google.
        $this->travel(1)->minute();
        GoogleCalendarSimulator::changeInTool($connection, ['title' => 'Consultation (Google edit)'], $event->external_id);

        // Back online, the sync arrives first: Google's change is newer and wins.
        $connection->forceFill(['status' => ConnectionStatus::Active])->save();
        $this->actingAs($maria)->postJson("/api/v1/connections/{$connection->id}/sync")->assertStatus(202);

        $conflict = CalendarConflict::query()->sole();
        expect($event->fresh())->title->toBe('Consultation (Google edit)')->push_state->toBeNull()
            ->and($conflict)->kept->toBe('tool')
            ->and($conflict->losing['title'])->toBe('Consultation (REOS edit)');

        $this->actingAs($maria)->postJson("/api/v1/calendar/conflicts/{$conflict->id}/undo")->assertOk();
        expect(SimulatorRecord::query()->where('external_id', $event->external_id)->sole()->data['title'])->toBe('Consultation (REOS edit)')
            ->and($event->fresh()?->title)->toBe('Consultation (REOS edit)');
        $this->actingAs($maria)->postJson("/api/v1/calendar/conflicts/{$conflict->id}/undo")->assertUnprocessable()
            ->assertJsonValidationErrors(['event' => 'This can no longer be undone.']);
    });
});

describe('the calendar API', function () {
    it('lists a window of your own events, with where new ones go', function () {
        $maria = mariaWithCalendar($this);
        $other = User::factory()->create();
        CalendarEvent::query()->create(['user_id' => $other->id, 'title' => 'Not Maria\'s', 'starts_at' => '2026-11-21T16:00:00Z', 'ends_at' => '2026-11-21T17:00:00Z']);

        $response = $this->actingAs($maria)->getJson('/api/v1/calendar/events?'.http_build_query(['from' => '2026-11-21T00:00:00-06:00', 'to' => '2026-11-22T00:00:00-06:00']))
            ->assertOk()
            ->assertJsonPath('write_to.tool', 'Google Calendar')
            ->assertJsonPath('write_to.calendar', 'maria@example.com')
            ->assertJsonPath('removed', []);

        // The demo fortnight has a showing and a consultation today (Houston time).
        expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Showing — 2218 Elm St', 'Buyer consultation — the Nguyens'])
            ->and($response->json('data.0'))->toMatchArray(['source' => 'google-calendar', 'kind' => 'event', 'editable' => true, 'deletable' => false, 'sync' => 'synced', 'busy' => false]);

        $this->actingAs($maria)->getJson('/api/v1/calendar/events?from=2026-11-21T00:00:00Z&to=2027-01-31T00:00:00Z')->assertUnprocessable()->assertJsonValidationErrors('to');
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/calendar/events?from=2026-11-21&to=2026-11-22')->assertUnauthorized();
    });

    it('checks what you enter, and saves all-day events by date', function () {
        $maria = User::factory()->create();

        $this->actingAs($maria)->postJson('/api/v1/calendar/events', appointment(['title' => '', 'ends_at' => '2026-11-23T09:00:00-06:00', 'attendees' => ['not an email'], 'kind' => 'event']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'ends_at', 'attendees.0', 'kind']);

        $this->actingAs($maria)->postJson('/api/v1/calendar/events', ['title' => 'Option period ends', 'kind' => 'deadline', 'all_day' => true, 'start_date' => '2026-11-27', 'end_date' => '2026-11-27'])
            ->assertCreated()
            ->assertJsonPath('data.all_day', true)
            ->assertJsonPath('data.start_date', '2026-11-27')
            ->assertJsonPath('data.end_date', '2026-11-27')
            ->assertJsonPath('data.ends_at', '2026-11-28T00:00:00+00:00')
            // No calendar connected: it stays in REOS.
            ->assertJsonPath('data.sync', 'local')
            ->assertJsonPath('data.tool', null);
    });

    it('keeps other people\'s events out of reach', function () {
        $maria = User::factory()->create();
        $event = app(CalendarEvents::class)->create($maria, ['title' => 'Mine', 'starts_at' => now(), 'ends_at' => now()->addHour()]);
        $conflict = CalendarConflict::query()->create(['user_id' => $maria->id, 'calendar_event_id' => $event->id, 'kept' => 'tool', 'reason' => 'edited_both', 'losing' => ['title' => 'x']]);
        $pat = User::factory()->create();

        $this->actingAs($pat)->putJson("/api/v1/calendar/events/{$event->id}", appointment())->assertNotFound();
        $this->actingAs($pat)->deleteJson("/api/v1/calendar/events/{$event->id}")->assertNotFound();
        $this->actingAs($pat)->postJson("/api/v1/calendar/events/{$event->id}/retry")->assertNotFound();
        $this->actingAs($pat)->postJson("/api/v1/calendar/conflicts/{$conflict->id}/undo")->assertNotFound();
        expect($event->fresh()?->title)->toBe('Mine');
    });

    it('shows a failed write with Try again', function () {
        $maria = mariaWithCalendar($this);
        $event = app(CalendarEvents::class)->create($maria, ['title' => 'Inspection', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $event->refresh()->forceFill(['push_state' => 'pending'])->save();
        (new PushCalendarEvent($event->id))->failed(null);

        $listed = $this->actingAs($maria)->getJson('/api/v1/calendar/events?'.http_build_query(['from' => now()->toIso8601String(), 'to' => now()->addDays(2)->toIso8601String()]))->json('data');
        expect(collect($listed)->firstWhere('id', $event->id))->toMatchArray(['sync' => 'failed', 'sync_error' => 'calendar.errors.notSaved']);

        $this->actingAs($maria)->postJson("/api/v1/calendar/events/{$event->id}/retry")->assertStatus(202);
        expect($event->fresh()?->push_state)->toBeNull();
    });
});

describe('choosing calendars (screen 16; web Connections › Manage)', function () {
    it('reads the main calendar by default; others are chosen, busy only keeps just the times', function () {
        $maria = mariaWithCalendar($this);
        $connection = Connection::query()->sole();

        $this->actingAs($maria)->getJson('/api/v1/connections')->assertJsonPath('data.0.calendars', [
            ['id' => 'primary', 'name' => 'maria@example.com', 'primary' => true, 'writable' => true, 'mode' => 'full', 'write' => true],
            ['id' => 'family', 'name' => 'Family', 'primary' => false, 'writable' => true, 'mode' => 'off', 'write' => false],
            ['id' => 'personal', 'name' => 'Personal', 'primary' => false, 'writable' => true, 'mode' => 'off', 'write' => false],
            ['id' => 'holidays', 'name' => 'Holidays in United States', 'primary' => false, 'writable' => false, 'mode' => 'off', 'write' => false],
        ]);

        $this->actingAs($maria)->patchJson("/api/v1/connections/{$connection->id}", ['calendars' => ['personal' => 'busy', 'family' => 'full', 'holidays' => 'full']])
            ->assertOk()
            ->assertJsonPath('data.calendars.2.mode', 'busy');

        $personal = CalendarEvent::query()->where('calendar_id', 'personal')->get();
        expect($personal)->toHaveCount(2)
            ->and($personal->pluck('title')->filter()->all())->toBe([])
            ->and($personal->every->busy)->toBeTrue()
            ->and(CalendarEvent::query()->where('calendar_id', 'family')->value('title'))->toBe('Soccer practice')
            ->and(CalendarEvent::query()->where('calendar_id', 'holidays')->value('title'))->toBe('Thanksgiving Day');

        // Switched off: its events leave REOS.
        $this->actingAs($maria)->patchJson("/api/v1/connections/{$connection->id}", ['calendars' => ['family' => 'off']])->assertOk();
        expect(CalendarEvent::query()->where('calendar_id', 'family')->exists())->toBeFalse();
    });

    it('sends REOS events only to a calendar you can write to and read in full', function () {
        $maria = mariaWithCalendar($this);
        $connection = Connection::query()->sole();

        $this->actingAs($maria)->patchJson("/api/v1/connections/{$connection->id}", ['calendars' => ['holidays' => 'full'], 'write_calendar' => 'holidays'])
            ->assertUnprocessable()->assertJsonValidationErrors(['write_calendar' => 'You can\'t add events to that calendar.']);
        $this->actingAs($maria)->patchJson("/api/v1/connections/{$connection->id}", ['calendars' => ['personal' => 'busy'], 'write_calendar' => 'personal'])
            ->assertUnprocessable()->assertJsonValidationErrors(['write_calendar' => 'set it to Read + write']);
        $this->actingAs($maria)->patchJson("/api/v1/connections/{$connection->id}", ['calendars' => ['nope' => 'full']])
            ->assertUnprocessable()->assertJsonValidationErrors('calendars');

        // From the website: REOS events now go to Family.
        $this->actingAs($maria)->patch("/connections/{$connection->id}", ['calendars' => ['family' => 'full'], 'write_calendar' => 'family'])
            ->assertRedirect();
        $event = app(CalendarEvents::class)->create($maria, ['title' => 'Open house', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);
        expect($event->fresh()?->calendar_id)->toBe('family')
            ->and(SimulatorRecord::query()->where('container', 'family')->where('external_id', $event->fresh()?->external_id)->exists())->toBeTrue();

        // "Nowhere": new events stay in REOS.
        $this->actingAs($maria)->patchJson("/api/v1/connections/{$connection->id}", ['write_calendar' => ''])->assertOk()->assertJsonPath('data.calendars.1.write', false);
        expect(app(CalendarEvents::class)->target($maria))->toBeNull();
    });
});

describe('disconnecting and housekeeping', function () {
    it('keeps REOS\'s own events when Google is disconnected, and writes them to the next calendar connected', function () {
        $maria = mariaWithCalendar($this);
        $connection = Connection::query()->sole();
        $mine = app(CalendarEvents::class)->create($maria, ['title' => 'Showing — 77 Memorial Dr', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);

        $this->actingAs($maria)->deleteJson("/api/v1/connections/{$connection->id}")->assertNoContent();

        expect(CalendarEvent::query()->where('user_id', $maria->id)->pluck('title')->all())->toBe(['Showing — 77 Memorial Dr'])
            ->and($mine->fresh())->connection_id->toBeNull()->external_id->toBeNull();

        connectOnWeb($this, $maria);
        $again = Connection::query()->sole();
        expect($mine->fresh())->connection_id->toBe($again->id)->push_state->toBeNull()
            ->and(SimulatorRecord::query()->where('connection_id', $again->id)->where('external_id', $mine->fresh()?->external_id)->exists())->toBeTrue();
    });

    it('writes waiting changes every 15 minutes, and forgets old versions and deleted events after 30 days', function () {
        $maria = mariaWithCalendar($this);
        Queue::fake();
        $waiting = app(CalendarEvents::class)->create($maria, ['title' => 'Waiting', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $gone = app(CalendarEvents::class)->create($maria, ['title' => 'Gone', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $gone->forceFill(['push_state' => null])->save();
        $gone->delete();
        CalendarConflict::query()->create(['user_id' => $maria->id, 'calendar_event_id' => $waiting->id, 'kept' => 'tool', 'reason' => 'edited_both', 'losing' => ['title' => 'Old']]);

        $this->travel(6)->minutes();
        Queue::fake();
        $this->artisan('reos:calendar:catch-up')->expectsOutput('Queued 1 calendar changes.')->assertSuccessful();
        Queue::assertPushed(PushCalendarEvent::class, fn (PushCalendarEvent $job) => $job->eventId === $waiting->id);

        $this->travel(31)->days();
        $this->artisan('reos:calendar:prune')->expectsOutput('Removed 1 old versions and 1 deleted events.')->assertSuccessful();
        expect(CalendarEvent::withTrashed()->find($gone->id))->toBeNull()->and(CalendarConflict::query()->exists())->toBeFalse();
    });

    it('includes the calendar in "Download my data"', function () {
        $maria = mariaWithCalendar($this);
        app(CalendarEvents::class)->create($maria, ['title' => 'Showing — 2218 Elm St', 'kind' => 'showing', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);

        $export = app(DataExport::class)->for($maria);

        expect(collect($export['calendar']['events'])->pluck('title'))->toContain('Showing — 2218 Elm St', 'Closing — 2218 Elm St')
            ->and($export['connections'][0])->not->toHaveKey('calendar_events');
    });

    it('waits while the tool is off, then writes when it is back', function () {
        $maria = mariaWithCalendar($this);
        $connection = Connection::query()->sole();
        $connection->forceFill(['status' => ConnectionStatus::ReconnectNeeded])->save();

        $event = app(CalendarEvents::class)->create($maria, ['title' => 'Team meeting', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        expect($event->fresh()?->push_state)->toBe('pending');

        $connection->forceFill(['status' => ConnectionStatus::Active])->save();
        $this->travel(6)->minutes();
        $this->artisan('reos:calendar:catch-up')->assertSuccessful();
        expect($event->fresh())->push_state->toBeNull()->external_id->not->toBeNull()
            ->and($connection->mode)->toBe(IntegrationMode::Simulator);
    });
});
