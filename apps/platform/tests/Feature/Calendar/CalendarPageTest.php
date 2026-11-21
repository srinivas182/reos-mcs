<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Calendar\CalendarEvents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/* The calendar on the agent dashboard (web journey screen 12; B4.1b). */

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-21T15:00:00Z')));

function dashboardAgent(): User
{
    return User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
}

it('shows an agent today in their own time zone, all-day events by date', function () {
    $james = dashboardAgent();
    $events = app(CalendarEvents::class);
    $events->create($james, ['title' => 'Showing — 2218 Elm St', 'kind' => 'showing', 'starts_at' => CarbonImmutable::parse('2026-11-21T20:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-21T20:45:00Z')]);
    $events->create($james, ['title' => 'Option period ends', 'kind' => 'deadline', 'all_day' => true, 'starts_at' => CarbonImmutable::parse('2026-11-21'), 'ends_at' => CarbonImmutable::parse('2026-11-22')]);
    // Tomorrow's all-day event starts before Houston's midnight in UTC terms, but isn't today.
    $events->create($james, ['title' => 'Closing day', 'kind' => 'deadline', 'all_day' => true, 'starts_at' => CarbonImmutable::parse('2026-11-22'), 'ends_at' => CarbonImmutable::parse('2026-11-23')]);
    // 11 PM in Houston: today there, tomorrow in UTC.
    $events->create($james, ['title' => 'Late call', 'starts_at' => CarbonImmutable::parse('2026-11-22T05:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-22T05:30:00Z')]);

    $this->actingAs($james)->withUnencryptedCookie('reos_tz', 'America/Chicago')->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('today.date', '2026-11-21')
            ->where('today.timezone', 'America/Chicago')
            ->where('today.data', fn ($data) => collect($data)->pluck('title')->all() === ['Option period ends', 'Showing — 2218 Elm St', 'Late call'])
            ->where('today.write_to', null));

    // A nonsense zone falls back to Texas time.
    $this->actingAs($james)->withUnencryptedCookie('reos_tz', 'Mars/Olympus')->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('today.timezone', 'America/Chicago'));
});

it('shows today only to agents', function () {
    $this->actingAs(User::factory()->withRole(Role::Candidate)->create())->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('today', null));
});

it('adds, changes, deletes and restores appointments from the website', function () {
    $james = dashboardAgent();

    $this->actingAs($james)->from('/dashboard')->post('/calendar/events', [
        'title' => 'Buyer consultation — Ana Torres', 'kind' => 'appointment',
        'starts_at' => '2026-11-23T16:00:00.000Z', 'ends_at' => '2026-11-23T17:00:00.000Z',
        'attendees' => ['ana.torres@example.com'], 'notify_guests' => false,
    ])->assertRedirect('/dashboard')->assertInertiaFlash('toast.message', 'Saved in REOS.');

    $event = CalendarEvent::query()->sole();
    expect($event)->title->toBe('Buyer consultation — Ana Torres')->attendees->toBe(['ana.torres@example.com'])->source->toBe('reos');

    $this->actingAs($james)->from('/dashboard')->put("/calendar/events/{$event->id}", [
        'title' => 'Buyer consultation — the Torres family', 'starts_at' => '2026-11-23T16:00:00Z', 'ends_at' => '2026-11-23T17:30:00Z',
    ])->assertRedirect('/dashboard')->assertSessionHasNoErrors();
    expect($event->fresh())->title->toBe('Buyer consultation — the Torres family')->kind->toBe('appointment');

    $this->actingAs($james)->from('/dashboard')->post('/calendar/events', ['title' => '', 'starts_at' => '2026-11-23T16:00:00Z', 'ends_at' => '2026-11-23T15:00:00Z'])
        ->assertSessionHasErrors(['title', 'ends_at']);

    $this->actingAs($james)->delete("/calendar/events/{$event->id}")->assertRedirect();
    expect(CalendarEvent::query()->exists())->toBeFalse();
    $this->actingAs($james)->post("/calendar/events/{$event->id}/restore")->assertRedirect();
    expect(CalendarEvent::query()->sole()->id)->toBe($event->id);
});

it('refuses what REOS never does, and other people\'s events', function () {
    $james = dashboardAgent();
    $google = CalendarEvent::query()->create(['user_id' => $james->id, 'source' => 'google-calendar', 'title' => 'Team meeting', 'starts_at' => now(), 'ends_at' => now()->addHour()]);
    $busy = CalendarEvent::query()->create(['user_id' => $james->id, 'source' => 'google-calendar', 'busy' => true, 'starts_at' => now(), 'ends_at' => now()->addHour()]);
    $conflict = CalendarConflict::query()->create(['user_id' => $james->id, 'calendar_event_id' => $google->id, 'kept' => 'tool', 'reason' => 'edited_both', 'losing' => ['title' => 'Old']]);

    $this->actingAs($james)->from('/dashboard')->delete("/calendar/events/{$google->id}")->assertSessionHasErrors(['event' => __('calendar.errors.notOurs')]);
    $this->actingAs($james)->from('/dashboard')->put("/calendar/events/{$busy->id}", ['title' => 'Peek', 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String()])
        ->assertSessionHasErrors(['event' => __('calendar.errors.busyOnly')]);

    $pat = dashboardAgent();
    $this->actingAs($pat)->put("/calendar/events/{$google->id}", ['title' => 'x', 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String()])->assertNotFound();
    $this->actingAs($pat)->post("/calendar/conflicts/{$conflict->id}/undo")->assertNotFound();

    // Undo on the website brings the other version back.
    $this->actingAs($james)->from('/dashboard')->post("/calendar/conflicts/{$conflict->id}/undo")
        ->assertRedirect('/dashboard')->assertInertiaFlash('toast.message', 'Done. The other version is back.');
    expect($google->fresh()?->title)->toBe('Old');
});
