<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Requests\Messaging\StartConversationRequest;
use App\Messaging\Contacts;
use App\Messaging\Conversations;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Messages on the web (B6.1; each role's Messages entry, provider screen
 * 26): conversations on the left with unread counts, the open conversation
 * on the right, New message with the people you may message. Live through
 * Reverb; the page reloads its lists when an update arrives.
 */
final class MessagesController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, Conversations $conversations, Contacts $contacts): Response
    {
        $inbox = $conversations->inbox($user);
        $open = $request->string('c')->toString();
        $selected = $open !== '' ? Conversation::query()->where('uuid', $open)->first() : null;

        if ($selected !== null && ! $conversations->isParticipant($user, $selected)) {
            $selected = null;
        }

        return Inertia::render('messages/index', [
            'inbox' => $inbox,
            'conversation' => $selected === null ? null : $conversations->show($user, $selected),
            'contacts' => Inertia::defer(static fn (): array => $contacts->for($user)),
        ]);
    }

    public function store(StartConversationRequest $request, #[CurrentUser] User $user, Conversations $conversations): RedirectResponse
    {
        $conversation = $conversations->start($user, $request->people(), $request->string('body')->toString(), $request->string('title')->toString());

        return redirect()->route('messages.index', ['c' => $conversation->uuid]);
    }

    public function send(SendMessageRequest $request, Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): RedirectResponse
    {
        $conversations->post($user, $conversation, $request->string('body')->toString(), $request->replyTo());

        return redirect()->route('messages.index', ['c' => $conversation->uuid]);
    }

    public function update(SendMessageRequest $request, Message $message, #[CurrentUser] User $user, Conversations $conversations): RedirectResponse
    {
        $conversations->edit($user, $message, $request->string('body')->toString());

        return back();
    }

    public function destroy(Message $message, #[CurrentUser] User $user, Conversations $conversations): RedirectResponse
    {
        $conversations->delete($user, $message);

        return back();
    }

    public function read(Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $conversations->read($user, $conversation);

        return response()->json(['unread' => $conversations->unread($user)]);
    }

    /** Older messages, a page at a time, as the person scrolls up. */
    public function older(Request $request, Conversation $conversation, #[CurrentUser] User $user, Conversations $conversations): JsonResponse
    {
        $page = $conversations->show($user, $conversation, $request->string('before')->toString() ?: null);

        return response()->json(['messages' => $page['messages'], 'older' => $page['older']]);
    }
}
