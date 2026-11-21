<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\SyncTrigger;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use App\Models\IntegrationInterest;
use App\Models\ToolRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Connections on the website (B3.2c, web journey screen 19). */
function webAgent(): User
{
    return User::factory()->withRole(Role::Agent)->create(['onboarded_at' => now()]);
}

/** A connected Google Calendar for this person, through the simulator. */
function webCalendar(object $test, User $user): Connection
{
    connectOnWeb($test, $user);

    return Connection::query()->where('owner_id', $user->id)->sole();
}

describe('the Connections page', function () {
    it('shows the tools this agent may use, with statuses and their connections', function () {
        $maria = webAgent();
        webCalendar($this, $maria);

        $this->actingAs($maria)->get('/connections')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('connections/index')
            ->where('tools', fn ($tools) => collect($tools)->contains(fn ($t) => $t['id'] === 'google-workspace' && $t['connected'] === 1 && $t['category'] === 'calendar_email')
                && collect($tools)->contains(fn ($t) => $t['id'] === 'follow-up-boss' && $t['action'] === 'notify'))
            ->has('connections', 1)
            ->where('connections.0.integration', 'google-workspace'));
    });

    it('lands here after connecting on the website', function () {
        connectOnWeb($this, webAgent())->assertRedirect('/connections');
    });

    it('needs sign-in', function () {
        $this->get('/connections')->assertRedirect('/login');
    });
});

describe('what you can do there', function () {
    it('counts Notify me, and undoes it', function () {
        $maria = webAgent();

        $this->actingAs($maria)->from('/connections')->post('/connections/tools/follow-up-boss/interest')->assertRedirect('/connections');
        expect(IntegrationInterest::query()->where(['user_id' => $maria->id, 'integration_id' => 'follow-up-boss', 'kind' => IntegrationInterest::NOTIFY])->exists())->toBeTrue();

        $this->actingAs($maria)->from('/connections')->delete('/connections/tools/follow-up-boss/interest')->assertRedirect('/connections');
        expect(IntegrationInterest::query()->where('user_id', $maria->id)->exists())->toBeFalse();
    });

    it('says no to Notify me for a tool you can already connect', function () {
        $maria = webAgent();

        $this->actingAs($maria)->from('/connections')->post('/connections/tools/google-workspace/interest')
            ->assertRedirect('/connections')->assertInertiaFlash('toast.type', 'error');
        expect(IntegrationInterest::query()->exists())->toBeFalse();
    });

    it('takes a tool request', function () {
        $maria = webAgent();

        $this->actingAs($maria)->from('/connections')->post('/connections/requests', ['name' => '  Sierra Interactive ', 'note' => 'Team CRM'])
            ->assertRedirect('/connections');
        expect(ToolRequest::query()->sole()->only(['user_id', 'name', 'note']))->toBe(['user_id' => $maria->id, 'name' => 'Sierra Interactive', 'note' => 'Team CRM']);

        $this->actingAs($maria)->post('/connections/requests', ['name' => 'x'])->assertSessionHasErrors('name');
    });

    it('syncs now, changes how far back, and disconnects', function () {
        $maria = webAgent();
        $calendar = webCalendar($this, $maria);

        Queue::fake();
        $this->actingAs($maria)->from('/connections')->post("/connections/{$calendar->id}/sync")->assertRedirect('/connections');
        Queue::assertPushed(SyncConnection::class, fn (SyncConnection $job) => $job->trigger === SyncTrigger::Manual);

        $this->actingAs($maria)->from('/connections')->patch("/connections/{$calendar->id}", ['history' => 'all'])->assertRedirect('/connections');
        expect($calendar->fresh()?->history()->value)->toBe('all');
        $this->actingAs($maria)->patch("/connections/{$calendar->id}", ['history' => '5y'])->assertSessionHasErrors('history');

        $this->actingAs($maria)->from('/connections')->delete("/connections/{$calendar->id}")->assertRedirect('/connections');
        expect(Connection::query()->exists())->toBeFalse();
    });

    it('never lets someone else manage your connection', function () {
        $calendar = webCalendar($this, webAgent());
        $other = webAgent();

        $this->actingAs($other)->post("/connections/{$calendar->id}/sync")->assertNotFound();
        $this->actingAs($other)->patch("/connections/{$calendar->id}", ['history' => 'all'])->assertNotFound();
        $this->actingAs($other)->delete("/connections/{$calendar->id}")->assertNotFound();
        expect(Connection::query()->count())->toBe(1);
    });
});
