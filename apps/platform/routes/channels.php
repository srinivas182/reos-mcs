<?php

declare(strict_types=1);

use App\Features\Flags;
use App\Messaging\Conversations;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Private Reverb channels (B6.1, ADR 0023). The web signs in with its
| session at /broadcasting/auth; the phone with its token at
| /api/v1/broadcasting/auth.
*/
Broadcast::routes(['prefix' => 'api/v1', 'middleware' => ['auth:sanctum']]);

// A person's own inbox: new messages in any of their conversations.
Broadcast::channel('users.{id}', static fn (User $user, string $id): bool => (string) $user->id === $id);

// One conversation: only its participants, while Messages is rolled out to them.
Broadcast::channel('conversations.{uuid}', static function (User $user, string $uuid): bool {
    $conversation = Conversation::query()->where('uuid', $uuid)->first();

    return $conversation !== null
        && app(Flags::class)->forUser($user, 'messages')
        && app(Conversations::class)->isParticipant($user, $conversation);
});
