<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\ReportRequest;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Requests\Messaging\StartConversationRequest;
use App\Messaging\Contacts;
use App\Messaging\Conversations;
use App\Messaging\MessagePresenter;
use App\Messaging\Safety;
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
     * @response array{unread: int, conversations: list<array{id: string, kind: 'direct'|'group', title: string, people: list<array{id: int, name: string}>, last_message: array{body: string|null, sender: string|null, deleted: bool, files: int, at: string}|null, unread: int, blocked: 'by_me'|'by_them'|null, updated_at: string|null}>}
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
     * to 19) a group starts, optionally named. Up to 5 `files[]` (images and
     * PDFs, 20 MB each; multipart) open once virus-scanned. Returns the
     * conversation.
     *
     * @status 201
     *
     * @response array{id: string, kind: 'direct'|'group', title: string, people: list<array{id: int, name: string}>, reads: list<array{user: int, read_at: string|null}>, messages: list<array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null, attachments: list<array{id: string, name: string, mime: string, size: int, image: bool, status: 'pending'|'clean'|'infected', url: string|null}>}>, older: bool, blocked: 'by_me'|'by_them'|null}
     */
    public function store(StartConversationRequest $request, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $conversation = $conversations->start($user, $request->people(), $request->string('body')->toString(), $request->string('title')->toString(), $request->attachments());

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
     * @response array{id: string, kind: 'direct'|'group', title: string, people: list<array{id: int, name: string}>, reads: list<array{user: int, read_at: string|null}>, messages: list<array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null, attachments: list<array{id: string, name: string, mime: string, size: int, image: bool, status: 'pending'|'clean'|'infected', url: string|null}>}>, older: bool, blocked: 'by_me'|'by_them'|null}
     */
    public function show(Request $request, Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        return response()->json($conversations->show($user, $conversation, $request->string('before')->toString() ?: null));
    }

    /**
     * Send a message (B6.1), optionally replying to one.
     *
     * Words, files or both: up to 5 `files[]` (images and PDFs, 20 MB each;
     * multipart). Files show `pending` until scanned, then a short-lived `url`.
     *
     * @status 201
     *
     * @response array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null, attachments: list<array{id: string, name: string, mime: string, size: int, image: bool, status: 'pending'|'clean'|'infected', url: string|null}>}
     */
    public function send(SendMessageRequest $request, Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $message = $conversations->post($user, $conversation, $request->string('body')->toString(), $request->replyTo(), $request->attachments());

        return response()->json(MessagePresenter::message($message->load(['sender', 'replyTo.sender'])), 201);
    }

    /**
     * Edit my message (B6.1), within 5 minutes of sending.
     *
     * @response array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null, attachments: list<array{id: string, name: string, mime: string, size: int, image: bool, status: 'pending'|'clean'|'infected', url: string|null}>}
     */
    public function update(SendMessageRequest $request, Message $message, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        return response()->json(MessagePresenter::message($conversations->edit($user, $message, $request->string('body')->toString())));
    }

    /**
     * Delete my message for everyone (B6.1), within 5 minutes of sending.
     *
     * @response array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null, attachments: list<array{id: string, name: string, mime: string, size: int, image: bool, status: 'pending'|'clean'|'infected', url: string|null}>}
     */
    public function destroy(Message $message, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        return response()->json(MessagePresenter::message($conversations->delete($user, $message)));
    }

    /**
     * Report a conversation (B6.1b).
     *
     * Copies the reported person's recent messages (the other person in a
     * one-to-one, or `user` in a group) and sends them to the tenant's
     * admins and REOS. `block` also blocks them.
     *
     * @response array{reported: bool}
     */
    public function report(ReportRequest $request, Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations, Safety $safety): JsonResponse
    {
        abort_unless($conversations->isParticipant($user, $conversation), 404);
        $safety->report($user, $conversation, $request->string('reason')->toString(), $request->string('details')->toString() ?: null, $request->filled('user') ? $request->integer('user') : null, $request->boolean('block'));

        return response()->json(['reported' => true]);
    }

    /**
     * Block someone (B6.1b).
     *
     * Neither of you can message the other one to one, and they no longer
     * see when you've read their messages.
     *
     * @response array{blocked: bool}
     */
    public function block(Request $request, #[CurrentUser] User $user, Safety $safety): JsonResponse
    {
        $request->validate(['user' => ['required', 'integer']]);
        $safety->block($user, User::query()->findOrFail($request->integer('user')));

        return response()->json(['blocked' => true]);
    }

    /**
     * Unblock someone (B6.1b).
     *
     * @response array{blocked: bool}
     */
    public function unblock(User $person, #[CurrentUser] User $user, Safety $safety): JsonResponse
    {
        $safety->unblock($user, $person);

        return response()->json(['blocked' => false]);
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
