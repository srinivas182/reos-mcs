<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Events\Messaging\ConversationRead;
use App\Events\Messaging\MessageChanged;
use App\Events\Messaging\MessagePosted;
use App\Features\Flags;
use App\Messaging\Conversations;
use App\Messaging\Relationships;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Messages rolled out to everyone for these tests (it starts with REOS staff).
    $flags = app(Flags::class);
    $flag = $flags->flag('messages');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);

    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->team = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->ana = User::factory()->withRole(Role::Agent, $this->team)->create(['name' => 'Ana Okoye']);
    $this->ben = User::factory()->withRole(Role::Agent, $this->team)->create(['name' => 'Ben Okoye']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Rachel Hughes']);
});

describe('who can message whom', function () {
    it('follows working relationships', function () {
        $rules = app(Relationships::class);

        expect($rules->canMessage($this->james, $this->linda))->toBeTrue()
            // The broker reaches agents in the brokerage's teams too.
            ->and($rules->canMessage($this->linda, $this->ana))->toBeTrue()
            // Agents talk to each other only inside their team.
            ->and($rules->canMessage($this->ana, $this->ben))->toBeTrue()
            ->and($rules->canMessage($this->james, $this->ana))->toBeFalse()
            ->and($rules->canMessage($this->rachel, $this->sofia))->toBeTrue()
            ->and($rules->canMessage($this->sofia, $this->james))->toBeFalse()
            ->and($rules->canMessage($this->james, $this->james))->toBeFalse();

        expect(array_map(fn ($c) => $c['user']->name, $rules->contacts($this->linda)))->toBe(['Ana Okoye', 'Ben Okoye', 'James Miller']);
    });

    it('refuses to start a conversation with someone outside them', function () {
        $this->actingAs($this->james)->post('/messages', ['people' => [$this->sofia->id], 'body' => 'Hi'])->assertSessionHasErrors('people');
        expect(Conversation::query()->count())->toBe(0);
    });
});

describe('conversations', function () {
    it('done when: a message arrives live on the web and in the app', function () {
        Event::fake([MessagePosted::class, ConversationRead::class]);

        $this->actingAs($this->james)->post('/messages', ['people' => [$this->linda->id], 'body' => 'Can you look at the Elm St offer?'])->assertRedirect();
        $conversation = Conversation::query()->firstOrFail();

        Event::assertDispatched(MessagePosted::class, function (MessagePosted $event) use ($conversation): bool {
            $channels = array_map(fn ($c) => $c->name, $event->broadcastOn());

            return $event->broadcastAs() === 'message.posted'
                && in_array('private-conversations.'.$conversation->uuid, $channels, true)
                && in_array('private-users.'.$this->linda->id, $channels, true)
                && $event->broadcastWith()['message']['body'] === 'Can you look at the Elm St offer?';
        });

        // Linda's app sees it, unread.
        $this->actingAs($this->linda, 'sanctum')->getJson('/api/v1/messages')->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('conversations.0.title', 'James Miller')
            ->assertJsonPath('conversations.0.last_message.body', 'Can you look at the Elm St offer?');

        // She replies from the app; James sees it on the web.
        $first = Message::query()->firstOrFail();
        $this->actingAs($this->linda, 'sanctum')->postJson("/api/v1/messages/{$conversation->uuid}", ['body' => 'On it.', 'reply_to' => $first->uuid])
            ->assertCreated()
            ->assertJsonPath('reply_to.body', 'Can you look at the Elm St offer?');

        $this->actingAs($this->james)->get("/messages?c={$conversation->uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('messages/index')
            ->has('inbox', 1)
            ->where('inbox.0.unread', 1)
            ->where('conversation.messages.1.body', 'On it.')
            ->where('conversation.messages.1.reply_to.sender', 'James Miller')
            ->where('messages.unread', 1));

        $this->actingAs($this->james)->postJson("/messages/{$conversation->uuid}/read")->assertOk()->assertJsonPath('unread', 0);
        Event::assertDispatched(ConversationRead::class);
    });

    it('reuses a one-to-one and makes groups of up to 20', function () {
        $service = app(Conversations::class);
        $a = $service->start($this->james, [$this->linda->id], 'One');
        $b = $service->start($this->james, [$this->linda->id], 'Two');
        $group = $service->start($this->linda, [$this->james->id, $this->ana->id], 'Team meeting at 4', 'Friday huddle');

        expect($a->id)->toBe($b->id)
            ->and($group->kind)->toBe('group')
            ->and($group->title)->toBe('Friday huddle')
            ->and($service->inbox($this->ana)[0]['title'])->toBe('Friday huddle');

        $this->actingAs($this->linda)->post('/messages', ['people' => range(1, 20), 'body' => 'Too many'])->assertSessionHasErrors('people');
    });

    it('lets the sender edit or delete for everyone within 5 minutes', function () {
        Event::fake([MessageChanged::class]);
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Typo hree');
        $message = Message::query()->firstOrFail();

        $this->actingAs($this->linda)->patch("/messages/items/{$message->uuid}", ['body' => 'Not mine'])->assertForbidden();
        $this->actingAs($this->james)->patch("/messages/items/{$message->uuid}", ['body' => 'Typo here'])->assertRedirect();
        expect($message->fresh()?->body)->toBe('Typo here')->and($message->fresh()?->edited_at)->not->toBeNull();

        $this->actingAs($this->james, 'sanctum')->deleteJson("/api/v1/messages/items/{$message->uuid}")->assertOk()
            ->assertJsonPath('deleted', true)->assertJsonPath('body', null);
        Event::assertDispatchedTimes(MessageChanged::class, 2);

        $late = app(Conversations::class)->post($this->james, $conversation, 'Late one');
        $this->travel(6)->minutes();
        $this->actingAs($this->james)->patch("/messages/items/{$late->uuid}", ['body' => 'Changed'])->assertSessionHasErrors('body');
    });

    it('keeps conversations to their participants', function () {
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Private');

        $this->actingAs($this->ana, 'sanctum')->getJson("/api/v1/messages/{$conversation->uuid}")->assertNotFound();
        $this->actingAs($this->ana, 'sanctum')->postJson("/api/v1/messages/{$conversation->uuid}", ['body' => 'Hi'])->assertNotFound();
        $this->actingAs($this->ana)->get("/messages?c={$conversation->uuid}")->assertInertia(fn (Assert $page) => $page->where('conversation', null));

        // The live channel too.
        $channel = Broadcast::getChannels()->get('conversations.{uuid}');
        expect($channel($this->linda, $conversation->uuid))->toBeTrue()
            ->and($channel($this->ana, $conversation->uuid))->toBeFalse()
            ->and(Broadcast::getChannels()->get('users.{id}')($this->ana, (string) $this->ana->id))->toBeTrue();
    });

    it('shows "Former member" when a sender\'s account is gone', function () {
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Bye');
        $this->james->delete();

        $this->actingAs($this->linda, 'sanctum')->getJson("/api/v1/messages/{$conversation->uuid}")->assertOk()
            ->assertJsonPath('messages.0.sender', null)
            ->assertJsonPath('messages.0.body', 'Bye');
    });
});

describe('the rollout', function () {
    it('is hidden until the messages flag reaches the person', function () {
        $flags = app(Flags::class);
        $flag = $flags->flag('messages');
        $flag->forceFill(['stage' => 'tenants', 'tenant_ids' => [$this->lone->id]])->save();
        $flags->changed($flag);

        $this->actingAs($this->james)->get('/messages')->assertOk();
        $this->actingAs($this->sofia)->get('/messages')->assertNotFound();
        $this->actingAs($this->sofia, 'sanctum')->getJson('/api/v1/messages')->assertNotFound();
        $this->actingAs($this->sofia)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('messages', null));
    });
});
