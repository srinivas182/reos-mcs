<?php

declare(strict_types=1);

/*
| Sprint 6 definition of done, security tests that span sub-sprints:
| live channels authorise per conversation, blocked people are blocked
| everywhere, and flags can't change without two-step. (Room tokens,
| recording consent and SMS consent are in Video and Notifications.)
*/

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Features\RolloutStage;
use App\Messaging\Conversations;
use App\Messaging\Safety;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoSession;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $flags = app(Flags::class);

    foreach (['messages', 'video_rooms'] as $key) {
        $flag = $flags->flag($key);
        $flag->forceFill(['stage' => 'everyone'])->save();
        $flags->changed($flag);
    }

    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $school)->create(['name' => 'Rachel Hughes']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $school)->create(['name' => 'Sofia Reyes']);
    $this->marco = User::factory()->withRole(Role::Candidate, $school)->create(['name' => 'Marco Diaz']);
});

/** Reverb-style channel auth, as in production (tests broadcast to null by default). */
function useSignedChannels(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'reos-key',
        'broadcasting.connections.reverb.secret' => 'reos-secret',
        'broadcasting.connections.reverb.app_id' => 'reos',
    ]);
    app(BroadcastManager::class)->purge();
    require base_path('routes/channels.php');
}

describe('live channels', function () {
    it('authorise each conversation for its participants only, on the web and the phone', function () {
        useSignedChannels();
        $conversation = app(Conversations::class)->start($this->rachel, [$this->sofia->id], 'Welcome to the class.');
        $channel = ['socket_id' => '1234.5678', 'channel_name' => "private-conversations.{$conversation->uuid}"];

        $this->actingAs($this->sofia)->post('/broadcasting/auth', $channel)->assertOk()->assertJsonStructure(['auth']);
        $this->actingAs($this->marco)->post('/broadcasting/auth', $channel)->assertForbidden();
        $this->actingAs($this->marco, 'sanctum')->postJson('/api/v1/broadcasting/auth', $channel)->assertForbidden();
        $this->actingAs($this->sofia, 'sanctum')->postJson('/api/v1/broadcasting/auth', $channel)->assertOk();

        // A person's own channel is theirs alone.
        $this->actingAs($this->marco)->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-users.{$this->sofia->id}"])->assertForbidden();
        $this->actingAs($this->sofia)->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-users.{$this->sofia->id}"])->assertOk();
    });

    it('close when Messages is switched off for the person', function () {
        useSignedChannels();
        $conversation = app(Conversations::class)->start($this->rachel, [$this->sofia->id], 'Welcome to the class.');
        $flags = app(Flags::class);
        $flag = $flags->flag('messages');
        $flag->forceFill(['stage' => 'off'])->save();
        $flags->changed($flag);
        app()->forgetScopedInstances();

        $this->actingAs($this->sofia)->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-conversations.{$conversation->uuid}"])->assertForbidden();
    });
});

describe('blocking', function () {
    it('blocks everywhere: messages, contacts and sessions', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 14:00', 'America/Chicago'));
        app(Safety::class)->block($this->sofia, $this->rachel);

        // Not offered as a contact either way, can't message, can't be booked.
        $this->actingAs($this->rachel)->post('/messages', ['people' => [$this->sofia->id], 'body' => 'Hi'])->assertSessionHasErrors('people');
        $contacts = fn (User $u) => collect($this->actingAs($u, 'sanctum')->getJson('/api/v1/messages/contacts')->assertOk()->json())->flatten(1)->pluck('id');
        expect($contacts($this->rachel))->not->toContain($this->sofia->id)->toContain($this->marco->id)
            ->and($contacts($this->sofia))->not->toContain($this->rachel->id);

        expect(fn () => app(VideoSessions::class)->schedule($this->rachel, [
            'kind' => VideoSession::ONE_ON_ONE, 'title' => 'Contract review', 'agenda' => null,
            'starts_at' => CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago'), 'minutes' => 45, 'guests' => [$this->sofia->id],
        ]))->toThrow(ValidationException::class);

        // Marco is unaffected.
        expect(app(VideoSessions::class)->schedule($this->rachel, [
            'kind' => VideoSession::ONE_ON_ONE, 'title' => 'Contract review', 'agenda' => null,
            'starts_at' => CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago'), 'minutes' => 45, 'guests' => [$this->marco->id],
        ]))->toBeInstanceOf(VideoSession::class);
    });
});

describe('flags', function () {
    it('can\'t be changed without two-step', function () {
        $noTwoStep = User::factory()->withRole(Role::SuperAdmin)->create();
        $payload = ['stage' => 'everyone', 'tenant_ids' => [], 'states' => [], 'percent' => 0, 'roles' => [], 'error_threshold' => 5];

        $this->actingAs($noTwoStep)->put('/admin/features/messages/rollout', [...$payload, 'stage' => 'off'])->assertRedirect(route('security.edit'));
        $this->actingAs($noTwoStep)->post('/admin/features/messages/kill')->assertRedirect(route('security.edit'));
        expect(app(Flags::class)->flag('messages')->stage)->toBe(RolloutStage::Everyone);
    });
});
