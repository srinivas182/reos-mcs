<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Requests\Messaging\StartConversationRequest;
use App\Messaging\Contacts;
use App\Messaging\Conversations;
use App\Messaging\MessagePresenter;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessagesController extends Controller
{
    /**
     * My conversations (B6.1).
     *
     * Latest first, each with the other people, the last message and how
     * many are unread; `unread` is the total for the Messages badge. Live
     * updates arrive on the private Reverb channel `users.{id}`
     * (`message.posted`). 404 when Messages isn't rolled out to the person.
     *
     * @response array{unread: int, conversations: list<array{id: string, kind: 'direct'|'group', title: string, people: list<array{id: int, name: string}>, last_message: array{body: string|null, sender: string|null, deleted: bool, at: string}|null, unread: int, updated_at: string|null}>}
     */
    public function index(#[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $inbox = $conversations->inbox($user);

        return response()->json(['unread' => array_sum(array_column($inbox, 'unread')), 'conversations' => $inbox]);
    }

    /**
     * People I may message (B6.1).
     *
     * Only people with a working relationship: brokers and their agents,
     * school staff and their students, mentors and mentees.
     *
     * @response array{contacts: list<array{id: int, name: string, role: string, organisation: string}>}
     */
    public function contacts(#[CurrentUser] User $user, Contacts $contacts): JsonResponse
    {
        return response()->json(['contacts' => $contacts->for($user)]);
    }

    /**
     * Start a conversation (B6.1).
     *
     * With one person an existing conversation is reused; with several (up
     * to 19) a group starts, optionally named. Returns the conversation.
     *
     * @status 201
     *
     * @response array{id: string, kind: 'direct'|'group', title: string, people: list<array{id: int, name: string}>, reads: list<array{user: int, read_at: string|null}>, messages: list<array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null}>, older: bool}
     */
    public function store(StartConversationRequest $request, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $conversation = $conversations->start($user, $request->people(), $request->string('body')->toString(), $request->string('title')->toString());

        return response()->json($conversations->show($user, $conversation), 201);
    }

    /**
     * One conversation (B6.1).
     *
     * Its people, how far each has read (`reads`, for "Read" under your
     * messages) and the latest 50 messages, oldest first; `before` (a message
     * id) pages back. Live on the private channel `conversations.{id}`:
     * `message.posted`, `message.changed`, `conversation.read`.
     *
     * @response array{id: string, kind: 'direct'|'group', title: string, people: list<array{id: int, name: string}>, reads: list<array{user: int, read_at: string|null}>, messages: list<array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null}>, older: bool}
     */
    public function show(Request $request, Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        return response()->json($conversations->show($user, $conversation, $request->string('before')->toString() ?: null));
    }

    /**
     * Send a message (B6.1), optionally replying to one.
     *
     * @status 201
     *
     * @response array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null}
     */
    public function send(SendMessageRequest $request, Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $message = $conversations->post($user, $conversation, $request->string('body')->toString(), $request->replyTo());

        return response()->json(MessagePresenter::message($message->load(['sender', 'replyTo.sender'])), 201);
    }

    /**
     * Edit my message (B6.1), within 5 minutes of sending.
     *
     * @response array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null}
     */
    public function update(SendMessageRequest $request, Message $message, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        return response()->json(MessagePresenter::message($conversations->edit($user, $message, $request->string('body')->toString())));
    }

    /**
     * Delete my message for everyone (B6.1), within 5 minutes of sending.
     *
     * @response array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null}
     */
    public function destroy(Message $message, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        return response()->json(MessagePresenter::message($conversations->delete($user, $message)));
    }

    /**
     * Mark a conversation read (B6.1).
     *
     * @response array{unread: int}
     */
    public function read(Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $conversations->read($user, $conversation);

        return response()->json(['unread' => $conversations->unread($user)]);
    }
}
